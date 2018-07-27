<?php

use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/flush', function () use ($router) {
    Cache::flush();
});

$router->get('getAmericaGames/{limit:[0-9]*}/{offset:[0-9]*}', function ($limit, $offset) {
    $limit  = (int)$limit;
    $offset = (int)$offset;
    
    $nin = new \App\Http\Controllers\NintenDealsController();
    
    return response()->json($nin->getAmericaGames(["limit" => (int)$limit], (int)$offset));
});

$router->get('getJapanGames', function () {
    $nin = new \App\Http\Controllers\NintenDealsController();

    return response()->json($nin->getJapanGames());
});

$router->get('getEuropeGames/{limit:[0-9]*}/{offset:[0-9]*}', function ($limit, $offset) {
    $limit  = (int)$limit;
    $offset = (int)$offset;

    $nin = new \App\Http\Controllers\NintenDealsController();

    return response()->json($nin->getEuropeGames(["limit" => (int)$limit], (int)$offset));
});

$router->get('getPrices/{country:[a-zA-Z]{2}}/{gameIds:[0-9\,]*}/{offset:[0-9]*}', function ($country, $gameIds, $offset) {
    $nin = new \App\Http\Controllers\NintenDealsController();
    
    return response()->json($nin->getPrices(strtoupper($country), explode(",", $gameIds), $offset));
});

$router->get('getMetacriticScores/{title}', function ($title) {
    $nin = new \App\Http\Controllers\NintenDealsController();
    
    return response()->json($nin->getMetacriticScores($title));
});


