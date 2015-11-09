<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Models;

/**
 * Description of EditActionKind
 *
 * @author Stefan
 */
class EditActionKind {
    const AddName = "AddName";
    const AddGame = "AddGame";
    const DeleteName = "DeleteName";
    const DeleteGame = "DeleteGame";
    const DeleteScore = "DeleteScore";
    const SetName = "SetName";
    const SetScore = "SetScore";
    const Undo = "Undo";
}
