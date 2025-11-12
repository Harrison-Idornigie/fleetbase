<?php

use Illuminate\Support\Facades\Route;
use Fleetbase\SchoolTransportEngine\Http\Controllers\StudentController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\SchoolRouteController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\BusAssignmentController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\CommunicationController;

Route::prefix('school-transport')->middleware(['api', 'auth:sanctum'])->group(function () {

    // Student management routes
    Route::prefix('students')->group(function () {
        Route::get('/', [StudentController::class, 'index']);
        Route::post('/', [StudentController::class, 'store']);
        Route::get('/{student}', [StudentController::class, 'show']);
        Route::patch('/{student}', [StudentController::class, 'update']);
        Route::delete('/{student}', [StudentController::class, 'destroy']);
        Route::post('/bulk-import', [StudentController::class, 'bulkImport']);
        Route::get('/{student}/assignments', [StudentController::class, 'assignments']);
    });

    // School route management
    Route::prefix('routes')->group(function () {
        Route::get('/', [SchoolRouteController::class, 'index']);
        Route::post('/', [SchoolRouteController::class, 'store']);
        Route::get('/{route}', [SchoolRouteController::class, 'show']);
        Route::patch('/{route}', [SchoolRouteController::class, 'update']);
        Route::delete('/{route}', [SchoolRouteController::class, 'destroy']);
        Route::get('/{route}/students', [SchoolRouteController::class, 'students']);
        Route::post('/{route}/optimize', [SchoolRouteController::class, 'optimizeRoute']);
        Route::get('/{route}/tracking', [SchoolRouteController::class, 'trackRoute']);
    });

    // Bus assignment management
    Route::prefix('assignments')->group(function () {
        Route::get('/', [BusAssignmentController::class, 'index']);
        Route::post('/', [BusAssignmentController::class, 'store']);
        Route::get('/{assignment}', [BusAssignmentController::class, 'show']);
        Route::patch('/{assignment}', [BusAssignmentController::class, 'update']);
        Route::delete('/{assignment}', [BusAssignmentController::class, 'destroy']);
        Route::post('/bulk-assign', [BusAssignmentController::class, 'bulkAssign']);
    });

    // Parent communication
    Route::prefix('communications')->group(function () {
        Route::get('/', [CommunicationController::class, 'index']);
        Route::post('/', [CommunicationController::class, 'store']);
        Route::get('/{communication}', [CommunicationController::class, 'show']);
        Route::patch('/{communication}/status', [CommunicationController::class, 'updateStatus']);
        Route::post('/notifications/send', [CommunicationController::class, 'sendNotification']);
        Route::get('/notifications/templates', [CommunicationController::class, 'templates']);
    });

    // Dashboard and analytics
    Route::get('/dashboard/stats', [StudentController::class, 'dashboardStats']);
    Route::get('/dashboard/route-efficiency', [SchoolRouteController::class, 'routeEfficiency']);
    Route::get('/dashboard/student-attendance', [BusAssignmentController::class, 'attendanceReport']);
});
