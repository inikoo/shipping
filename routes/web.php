<?php

/** @var \Laravel\Lumen\Routing\Router $router */


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

$router->get(
    '/', function () use ($router) {
    return $router->app->version();
}
);

$router->get('labels/{checksum}', 'ShipmentController@display_label');
$router->get('async_labels/{shipperAccountID}/{labelId}', 'ShipmentController@display_async_label');

$router->group(
    ['middleware' => 'auth'], function ($router) {
    $router->get('me', 'AuthController@me');
    $router->post('shipper-accounts', 'ShipperAccountController@create');
    $router->post('shipment', 'ShipmentController@create');
    $router->post('services', 'ShipmentController@services');
    $router->get('track/{shipmentId}', 'ShipmentController@track');

});
