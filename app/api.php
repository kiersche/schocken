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