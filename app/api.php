<?php
namespace App;
require '../vendor/autoload.php';
use App\Models\Name as Name;
use App\Models\Game as Game;
use App\Models\Edit as Edit;
use App\Models\EditAction as EditAction;
use App\Models\EditActionKind as EditActionKind;
use App\Models\Score as Score;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
/*datensatz
liste, id => {name, wert}, wert numerisch, null erlaubt (für "nicht teilgenommen"), id eindeutig, namensduplikate erlaubt

(ein datensatz ist einfach die strichliste eines abends, ein spiel quasi, optional mit datum und ort)
datensatz hinzufügen
datensatz bearbeiten (korrektur?) (werte ändern, personen hinzufügen und entfernen, personen immer für alle datensätze anzeigen, egal wann hinzugefügt)
datensatz löschen
person hinzufügen, bearbeiten (name), löschen

tabelle(n?):

names: id => name
games: id => datum, ort
scores: [gameId, nameId] => wert
transitions: id => editorname, content, datum kommentar?

Transitions schreibt alle aktionen mit die an die api gehen, um sie rückgängig machen zu können. Rückgängigmachen wird hier auch festgehalten
types sind alle aktionen die ausgeführt werden können (hinzufügen, bearbeiten und löschen von datensätzen bzw personen)
content kann einfach das json sein, das für diese aktion zur api geschickt wurde. oder vllt lieber: nur bei hinzufügen das aktions-json. für bearbeiten das aktions-json UND die daten *vor* ihrer bearbeitung als json? bei löschen das gleiche?
nachtrag: type brauchen wir nicht, im content steht ja alles drin was wir brauchen (oder? letzten endes wird im content einfach der type stehen)

rückgängigmachen:
-entweder type "undo" und die id der transition die undone werden soll, und anzeige der aktuellen werte immer als ergebnis einer art "commit-history" anzeigen. dann müsste man von hinten nach vorne alle transitionen durchlaufen und gucken, welche wirklich undone werden sollen (undo kann ja selbst undone werden). dann von vorne nach hinten alle commits/transitions durchlaufen und transitions auf der undo-liste einfach ignorieren. wird bspw. das hinzufügen eines benutzers rückgängig gemacht und gibt es eine darauffolgende transition die dem benutzer werte zuweist, wird dieser teil der transition einfach nicht durchgeführt.

performance und integrität: eigentlich bräuchte man nur die transitionen, tabellen personen und datensätze bilden sich ja durch die transitionen "selbst". aber: wenn man diese tabellen zusätzlich "rumliegen" hat, geht das anzeigen schneller. findet eine bearbeitung statt, muss nur die eine transition auf die beiden anderen tabellen "committet" werden. allerdings muss irgendwo hinterlegt sein, auf welcher transitions-id der aktuelle datenstand basiert. vielleicht öffnen zwei personen mal gleichzeitig den bearbeitungsmodus. der eine schickt seine daten vor dem anderen ab. der andere muss dann eine fehlermeldung bekommen, die ihm sagt dass sich inzwischen die daten geändert haben. schreibzugriffe auf die datenbank müssen synchronisiert werden - zwei personen können ihre daten ja fast zeitgleich abschicken, sodass keiner eine fehlermeldung bekommt.*/

define('GAMES_TABLE', './data/games.json');
define('SESSIONS_TABLE', './data/sessions.json');
define('PLAYERS_TABLE', './data/players.json');
define('SCORES_TABLE', './data/scores.json');
define('RAND_CHARSET', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

function getRandomString($length){
    $result = '';
    $charsetLength = strlen(RAND_CHARSET);
    for ($i = 0; $i < $length; $i++)
        $result .= RAND_CHARSET[rand(0, $charsetLength - 1)];
    return $result;
}

//Eloquent
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'schocken',
    'username' => 'scoretracker',
    'password' => 'scoretracker',
    'collation' => 'utf8_general_ci',
    'charset' => 'utf8',
    'prefix' => ''
]);

$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->bootEloquent();

$app = new \Slim\Slim();
$name = new Name();

$app->get("/names", function(){
    echo Name::all()->toJson();
});

function getTable($tableFile){
    $table = false;
    if (file_exists($tableFile))        
        $table = file_get_contents($tableFile);

    if ($table !== false)
        $table = json_decode($table, true);
    
    return $table;
}

function getRequestBodyArray($app){
    $bodyStr = $app->request->getBody();    
    $bodyArr = json_decode($bodyStr, true);
    return $bodyArr;
}

function getIdForNewArrayElement($array){
    $newId = getRandomString(5);
    while (array_key_exists($newId, $array)){
        $newId = getRandomString(5);
    }
    return $newId;
}

function addNewEntryToSubGameTable($app, $gameId, $tableFile, $name){
    $gamesTable = getTable(GAMES_TABLE);
    if (($gamesTable === false) || !array_key_exists($gameId, $gamesTable))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Game not found.');
        return;
    }
    
    $tableArray = getTable($tableFile);
    if ($tableArray === false)
        $tableArray = [];
    if (!array_key_exists($gameId, $tableArray))
        $tableArray[$gameId] = [];
    
    $newId = getIdForNewArrayElement($tableArray[$gameId]);
    $tableArray[$gameId][$newId] = $name;
    $writeResult = file_put_contents($tableFile, json_encode($tableArray));
    if ($writeResult === false){
        $app->response->setStatus(501);
        $app->response->setBody('Could not write new data to file. No idea why.');
        return;
    }
    $result['id'] = $newId;
    $app->response->setBody(json_encode($result));
}

function updateEntryInSubGameTable($app, $gameId, $tableFile, $subId, $name){
    $gamesTable = getTable(GAMES_TABLE);
    if (($gamesTable === false) || !array_key_exists($gameId, $gamesTable))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Game not found.');
        return;
    }
    
    $subTable = getTable($tableFile);
    if (($subTable === false) || !array_key_exists($gameId, $subTable) || 
            !array_key_exists($subId, $subTable[$gameId]))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Dataset not found.');
        return;
    }    
    
    $subTable[$gameId][$subId] = $name;
    
    $writeResult = file_put_contents($tableFile, json_encode($subTable));
    if ($writeResult === false){
        $app->response->setStatus(501);
        $app->response->setBody('Could not write new data to file. No idea why.');
        return;
    }
}

function setScore($app, $gameId, $sessionId, $playerId, $score){
    $gamesTable = getTable(GAMES_TABLE);
    if (($gamesTable === false) || !array_key_exists($gameId, $gamesTable))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Game not found.');
        return;
    }
    
    $sessionsTable = getTable(SESSIONS_TABLE);
    if (($sessionsTable === false) || !array_key_exists($gameId, $sessionsTable) || 
            !array_key_exists($sessionId, $sessionsTable[$gameId]))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Session not found.');
        return;
    }
    
    $playersTable = getTable(PLAYERS_TABLE);
    if (($playersTable === false) || !array_key_exists($gameId, $playersTable) ||
            !array_key_exists($playerId, $playersTable[$gameId]))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Player not found.');
        return;
    }
    
    $scoresArray = getTable(SCORES_TABLE);
    if ($scoresArray === false)
        $scoresArray = [];
    if (!array_key_exists($gameId, $scoresArray))
        $scoresArray[$gameId] = [];    
    if (!array_key_exists($sessionId, $scoresArray[$gameId]))
        $scoresArray[$gameId][$sessionId] = [];    
    
    $scoresArray[$gameId][$sessionId][$playerId] = $score;
    
    $writeResult = file_put_contents(SCORES_TABLE, json_encode($scoresArray));
    if ($writeResult === false){
        $app->response->setStatus(501);
        $app->response->setBody('Could not write score data to file. No idea why.');
        return;
    }
}

$app->post("/games", function() use ($app){
    $gamesTable = getTable(GAMES_TABLE);
    if ($gamesTable === false)
        $gamesTable = [];
    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        $gameId = getIdForNewArrayElement($gamesTable);
        $gamesTable[$gameId] = $bodyArr['name'];
        $writeResult = file_put_contents(GAMES_TABLE, json_encode($gamesTable));
        if ($writeResult === false){
            $app->response->setStatus(501);
            $app->response->setBody('Could not write new data to file. No idea why.');
            return;
        }
        $result['id'] = $gameId;
        $app->response->setBody(json_encode($result));
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }
});

$app->put("/games/:gameId", function($gameId) use ($app){
    $gamesTable = getTable(GAMES_TABLE);
    if (($gamesTable === false) || !array_key_exists($gameId, $gamesTable)){
        $app->response->setStatus(404);
        $app->response->setBody('Game not found.');
        return;
    }
    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        $gamesTable[$gameId] = $bodyArr['name'];
        $writeResult = file_put_contents(GAMES_TABLE, json_encode($gamesTable));
        if ($writeResult === false){
            $app->response->setStatus(501);
            $app->response->setBody('Could not write new data to file. No idea why.');
            return;
        }
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }
});

$app->post("/games/:gameId/sessions", function($gameId) use ($app){    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        addNewEntryToSubGameTable($app, $gameId, SESSIONS_TABLE, $bodyArr['name']);
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }    
});

$app->put("/games/:gameId/sessions/:sessionId", function($gameId, $sessionId) use ($app){    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        updateEntryInSubGameTable($app, $gameId, SESSIONS_TABLE, $sessionId, $bodyArr['name']);
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }    
});

$app->post("/games/:gameId/players", function($gameId) use ($app){    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        addNewEntryToSubGameTable($app, $gameId, PLAYERS_TABLE, $bodyArr['name']);
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }    
});

$app->put("/games/:gameId/players/:playerId", function($gameId, $playerId) use ($app){    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('name', $bodyArr) && (strlen($bodyArr['name']) > 0)) {
        updateEntryInSubGameTable($app, $gameId, PLAYERS_TABLE, $playerId, $bodyArr['name']);
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "name" and a nonempty string assigned to it.');
    }    
});

$app->put("/scores/game/:gameId/session/:sessionId/player/:playerId", function($gameId, $sessionId, $playerId) use ($app){    
    $bodyArr = getRequestBodyArray($app);
    if ($bodyArr && array_key_exists('score', $bodyArr) && is_int($bodyArr['score']) && ($bodyArr['score'] >= 0)) {
        $app->response->setBody('Setting score: $gameId, $sessionId, $playerId, ' . $bodyArr['score']);
        setScore($app, $gameId, $sessionId, $playerId, $bodyArr['score']);
    }
    else{
        $app->response->setStatus(400);
        $app->response->setBody('You need to pass an object with the key "score" and a positive integer assigned to it.');
    }    
});

function DictionaryToObjectArray($dictionary, $keyPropName, $valuePropName){
    $result = array();
    foreach ($dictionary as $id => $name){
        $obj[$keyPropName] = $id;
        $obj[$valuePropName] = $name;
        $result[] = $obj;
    }
    return $result;
}

$app->get("/games/", function() use ($app){
    $gamesTable = getTable(GAMES_TABLE);
    if ($gamesTable === false)
        $result = [];
    else
        $result = DictionaryToObjectArray($gamesTable, 'id', 'name');
    
    $app->response->setBody(json_encode($result));
});

$app->get("/games/:gameId", function($gameId) use ($app){
    $gamesTable = getTable(GAMES_TABLE);
    if (($gamesTable === false) || !array_key_exists($gameId, $gamesTable))
    {
        $app->response->setStatus(404);
        $app->response->setBody('Game not found.');
        return;
    }
    
    $sessionsTable = getTable(SESSIONS_TABLE);
    $sessions = [];
    if (($sessionsTable !== false) && array_key_exists($gameId, $sessionsTable))
        $sessions = $sessionsTable[$gameId];
    
    $playersTable = getTable(PLAYERS_TABLE);
    $players = [];
    if (($playersTable !== false) && array_key_exists($gameId, $playersTable))
        $players = $playersTable[$gameId];
    
    $scoresTable = getTable(SCORES_TABLE);
    $scores = [];
    if (($scoresTable !== false) && array_key_exists($gameId, $scoresTable))
        $scores = $scoresTable[$gameId];

    $result['name'] = $gamesTable[$gameId];
    $result['sessions'] = DictionaryToObjectArray($sessions, 'id', 'name');
    $result['players'] = DictionaryToObjectArray($players, 'id', 'name');
    $result['scores'] = $scores;
    $app->response->setBody(json_encode($result));
});

$app->get("/scores", function(){
    $scores = Score::where('game', 1)->take(1)->get();
    $score = $scores[0];
    $score->score = 887;
    $save = $score->save();
    $something = Score::create(['game' => 5, 'name' => 97, 'score' => 86]);
    $something->delete();
    
    echo json_encode($something);
    //echo Score::all()->toJson();
});

$app->run();