<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Description of Score
 *
 * @author Stefan
 */
class Score extends Model{
    //put your code here
    public $timestamps = false;
    public $incrementing = false;
    
    protected $primaryKey = array('game','name');
    protected $fillable = array('game', 'name', 'score');
    
    /**
     * (https://github.com/laravel/framework/issues/5517)
     * Set the keys for a save update query.
     * This is a fix for tables with composite keys
     * TODO: Investigate this later on
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query
            //Put appropriate values for your keys here:
            ->where('game', '=', $this->game)
            ->where('name', '=', $this->name);

        return $query;
    }    
}
