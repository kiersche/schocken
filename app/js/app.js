/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

var app = angular.module('schockenApp', ['ngRoute', 'ngResource']);

app.factory('Game', function($resource) {
  return $resource('./api.php/games/:id'); // Note the full endpoint address
});

app.controller('GameListCtrl', function ($scope, Game) {
  $scope.newGameName = '';
  $scope.games = Game.query();  
  
  $scope.cancelCreate = function(){
      $scope.newGameName = '';
  }
  
  $scope.doCreate = function(){
      if ($scope.newGameName != ''){
          var nameObj = {};
          nameObj.name = $scope.newGameName;
          $scope.newGameName = '';
          Game.save({}, nameObj, function(response){
              var gameObj = {};
              gameObj.id = response.id;
              gameObj.name = nameObj.name;
              $scope.games.push(gameObj);
          });
      }
  }
});

app.controller('GameDetailCtrl', function ($scope, Game) {
  $scope.newPlayerName = '';
  $scope.newSessionName = '';
  
  var doQuery = function(){
    $scope.games = Game.query();
  }
  
  $scope.cancelCreate = function(){
      $scope.newGameName = '';
  }
  
  $scope.doCreate = function(){
      if ($scope.newGameName != ''){
          var nameObj = {};
          nameObj.name = $scope.newGameName;
          $scope.newGameName = '';
          Game.save({}, nameObj, function(response){
              var gameObj = {};
              gameObj.id = response.id;
              gameObj.name = nameObj.name;
              $scope.games.push(gameObj);
          });
      }
  }
  doQuery();
});

app.config(['$routeProvider',
  function($routeProvider) {
    $routeProvider.
      when('/games', {
        templateUrl: 'partials/game-list.html',
        controller: 'GameListCtrl'
      }).
      when('/games/:gameId', {
        templateUrl: 'partials/game-detail.html',
        controller: 'GameDetailCtrl'
      }).
      otherwise({
        redirectTo: '/games'
      });
  }]);