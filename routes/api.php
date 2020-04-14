<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function() {
    Route::post('register', 'AuthController@postRegister');
    Route::post('login', 'AuthController@postLogin');
    Route::get('register/activate/{token}', 'AuthController@getActivateUser');

    Route::group(['middleware' => 'auth:api'], function() {
        Route::get('user', 'AuthController@getUser');
        Route::get('logout', 'AuthController@getLogout');
    });
});

Route::group(['middleware' => 'api', 'prefix' => 'password'], function() {
    Route::post('create', 'PasswordResetController@postCreate');
    Route::get('find/{token}', 'PasswordResetController@getFind');
    Route::post('reset', 'PasswordResetController@postReset');
});
