<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| School Transport API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the school transport
| module. These routes are loaded by the SchoolTransportServiceProvider.
|
*/

Route::prefix('school-transport')->middleware(['api', 'fleetbase.api'])->group(function () {
    Route::prefix('v1')->namespace('Fleetbase\SchoolTransport\Http\Controllers\Api\V1')->group(function () {

        // Students
        Route::group(['prefix' => 'students'], function () {
            Route::get('/', 'StudentController@query');
            Route::post('/', 'StudentController@create');
            Route::get('/{id}', 'StudentController@find');
            Route::put('/{id}', 'StudentController@update');
            Route::delete('/{id}', 'StudentController@delete');
        });

        // Schools
        Route::group(['prefix' => 'schools'], function () {
            Route::get('/', 'SchoolController@query');
            Route::post('/', 'SchoolController@create');
            Route::get('/{id}', 'SchoolController@find');
            Route::put('/{id}', 'SchoolController@update');
            Route::delete('/{id}', 'SchoolController@delete');
        });

        // Routes
        Route::group(['prefix' => 'routes'], function () {
            Route::get('/', 'SchoolRouteController@query');
            Route::post('/', 'SchoolRouteController@create');
            Route::get('/{id}', 'SchoolRouteController@find');
            Route::put('/{id}', 'SchoolRouteController@update');
            Route::delete('/{id}', 'SchoolRouteController@delete');
        });

        // Buses
        Route::group(['prefix' => 'buses'], function () {
            Route::get('/', 'BusController@query');
            Route::post('/', 'BusController@create');
            Route::get('/{id}', 'BusController@find');
            Route::put('/{id}', 'BusController@update');
            Route::delete('/{id}', 'BusController@delete');
        });

        // Drivers
        Route::group(['prefix' => 'drivers'], function () {
            Route::get('/', 'DriverController@query');
            Route::post('/', 'DriverController@create');
            Route::get('/{id}', 'DriverController@find');
            Route::put('/{id}', 'DriverController@update');
            Route::delete('/{id}', 'DriverController@delete');
        });

        // Bus Assignments
        Route::group(['prefix' => 'assignments'], function () {
            Route::get('/', 'BusAssignmentController@query');
            Route::post('/', 'BusAssignmentController@create');
            Route::get('/{id}', 'BusAssignmentController@find');
            Route::put('/{id}', 'BusAssignmentController@update');
            Route::delete('/{id}', 'BusAssignmentController@delete');
        });

        // Trips
        Route::group(['prefix' => 'trips'], function () {
            Route::get('/', 'TripController@query');
            Route::post('/', 'TripController@create');
            Route::get('/{id}', 'TripController@find');
            Route::put('/{id}', 'TripController@update');
            Route::delete('/{id}', 'TripController@delete');
        });

        // Tracking
        Route::group(['prefix' => 'tracking'], function () {
            Route::get('/', 'TrackingController@query');
            Route::post('/', 'TrackingController@create');
            Route::get('/{id}', 'TrackingController@find');
        });

        // Alerts
        Route::group(['prefix' => 'alerts'], function () {
            Route::get('/', 'AlertController@query');
            Route::post('/', 'AlertController@create');
            Route::get('/{id}', 'AlertController@find');
            Route::put('/{id}', 'AlertController@update');
            Route::delete('/{id}', 'AlertController@delete');
        });

        // Communications
        Route::group(['prefix' => 'communications'], function () {
            Route::get('/', 'CommunicationController@query');
            Route::post('/', 'CommunicationController@create');
            Route::get('/{id}', 'CommunicationController@find');
            Route::put('/{id}', 'CommunicationController@update');
            Route::delete('/{id}', 'CommunicationController@delete');
        });

        // Dashboard
        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/stats', 'DashboardController@stats');
            Route::get('/active-trips', 'DashboardController@activeTrips');
            Route::get('/alerts-summary', 'DashboardController@alertsSummary');
        });

        // Reports
        Route::group(['prefix' => 'reports'], function () {
            Route::get('/trip-summary', 'ReportController@tripSummary');
            Route::get('/student-activity', 'ReportController@studentActivity');
            Route::get('/bus-utilization', 'ReportController@busUtilization');
            Route::get('/safety-incidents', 'ReportController@safetyIncidents');
        });
    });
});
