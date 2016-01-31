<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App;

/**
 * Description of dbaccess
 *
 * @author Stefan
 */
class DbAccess {
    const dbFilename = "db.sqlite";
    const gamesTablename = "games";
    const gamesColDefs = "id INTEGER PRIMARY KEY, name TEXT";
    const sessionsTablename = "sessions";
    const sessionsColDefs = "id INTEGER PRIMARY KEY, name TEXT, gameId INTEGER not null, FOREIGN KEY (gameId) REFERENCES " + gamesTablename + "(id) ON DELETE CASCADE";
    const playersTablename = "players";
    const playersColDefs = "id INTEGER PRIMARY KEY, name TEXT, gameId INTEGER not null, FOREIGN KEY (gameId) REFERENCES " + gamesTablename + "(id) ON DELETE CASCADE";
    const scoresTablename = "scores";
    const scoresColDefs = "value UNSIGNED INTEGER, gameId INTEGER not null, sessionId INTEGER not null, playerId INTEGER not null, " + 
            "FOREIGN KEY (gameId) REFERENCES " + gamesTablename + "(id) ON DELETE CASCADE, " + 
            "FOREIGN KEY (sessionId) REFERENCES " + sessionsTablename + "(id) ON DELETE CASCADE, " + 
            "FOREIGN KEY (playerId) REFERENCES " + playersTablename + "(id) ON DELETE CASCADE, " + 
            "PRIMARY KEY (gameId, sessionId, playerId)";
    private $db;
    
    function __construct() {
        $this->db = new PDO('sqlite:' + dbFilename);
        $this->createTables();
    }
    
    private function getCreateTableStatement($tablename, $columnDefs){
        return "CREATE TABLE IF NOT EXISTS " + $tablename + " (" + $columnDefs + ")";
    }
    
    private function createTables(){
        $this->db->exec(getCreateTableStatement(gamesTablename, gamesColDefs) + ";");
        $this->db->exec(getCreateTableStatement(sessionsTablename, sessionsColDefs) + ";");
        $this->db->exec(getCreateTableStatement(playersTablename, playersColDefs) + ";");
        $this->db->exec(getCreateTableStatement(scoresTablename, scoresColDefs) + " WITHOUT ROWID;");
    }
}
