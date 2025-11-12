<?php

use Illuminate\Support\Facades\Route;
use Fleetbase\SchoolTransportEngine\Http\Controllers\StudentController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\SchoolController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\SchoolRouteController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\BusController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\DriverController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\BusAssignmentController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\TripController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\TrackingController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\AlertController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\CommunicationController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\AttendanceController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\ParentGuardianController;
use Fleetbase\SchoolTransportEngine\Http\Controllers\StopController;

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
        Route::get('/{student}/guardians', [StudentController::class, 'guardians']);
        Route::get('/{student}/trips', [StudentController::class, 'trips']);
    });

    // School management routes
    Route::prefix('schools')->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{school}', [SchoolController::class, 'show']);
        Route::patch('/{school}', [SchoolController::class, 'update']);
        Route::delete('/{school}', [SchoolController::class, 'destroy']);
        Route::get('/{school}/students', [SchoolController::class, 'students']);
        Route::get('/{school}/routes', [SchoolController::class, 'routes']);
        Route::get('/{school}/buses', [SchoolController::class, 'buses']);
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
        Route::get('/{route}/stops', [SchoolRouteController::class, 'stops']);
    });

    // Bus management routes
    Route::prefix('buses')->group(function () {
        Route::get('/', [BusController::class, 'index']);
        Route::post('/', [BusController::class, 'store']);
        Route::get('/{bus}', [BusController::class, 'show']);
        Route::patch('/{bus}', [BusController::class, 'update']);
        Route::delete('/{bus}', [BusController::class, 'destroy']);
        Route::get('/{bus}/assignments', [BusController::class, 'assignments']);
        Route::get('/{bus}/trips', [BusController::class, 'trips']);
        Route::patch('/{bus}/status', [BusController::class, 'updateStatus']);
        Route::get('/{bus}/current-location', [BusController::class, 'currentLocation']);

        // FleetOps integration routes
        Route::post('/{bus}/maintenance', [BusController::class, 'scheduleMaintenance']);
        Route::get('/{bus}/maintenance', [BusController::class, 'maintenanceHistory']);
        Route::post('/{bus}/fuel', [BusController::class, 'recordFuel']);
        Route::get('/{bus}/fuel', [BusController::class, 'fuelReports']);
        Route::get('/{bus}/route-playback', [BusController::class, 'routePlayback']);
    });

    // Driver management routes
    Route::prefix('drivers')->group(function () {
        Route::get('/', [DriverController::class, 'index']);
        Route::post('/', [DriverController::class, 'store']);
        Route::get('/{driver}', [DriverController::class, 'show']);
        Route::patch('/{driver}', [DriverController::class, 'update']);
        Route::delete('/{driver}', [DriverController::class, 'destroy']);
        Route::get('/{driver}/assignments', [DriverController::class, 'assignments']);
        Route::get('/{driver}/trips', [DriverController::class, 'trips']);
        Route::patch('/{driver}/status', [DriverController::class, 'updateStatus']);
        Route::get('/{driver}/current-location', [DriverController::class, 'currentLocation']);
    });

    // Bus assignment management
    Route::prefix('assignments')->group(function () {
        Route::get('/', [BusAssignmentController::class, 'index']);
        Route::post('/', [BusAssignmentController::class, 'store']);
        Route::get('/{assignment}', [BusAssignmentController::class, 'show']);
        Route::patch('/{assignment}', [BusAssignmentController::class, 'update']);
        Route::delete('/{assignment}', [BusAssignmentController::class, 'destroy']);
        Route::post('/bulk-assign', [BusAssignmentController::class, 'bulkAssign']);
        Route::get('/{assignment}/trips', [BusAssignmentController::class, 'trips']);
    });

    // Trip management routes
    Route::prefix('trips')->group(function () {
        Route::get('/', [TripController::class, 'index']);
        Route::post('/', [TripController::class, 'store']);
        Route::post('/schedule', [TripController::class, 'scheduleTrip']);
        Route::get('/{trip}', [TripController::class, 'show']);
        Route::patch('/{trip}', [TripController::class, 'update']);
        Route::delete('/{trip}', [TripController::class, 'destroy']);
        Route::patch('/{trip}/status', [TripController::class, 'updateStatus']);
        Route::post('/{trip}/start', [TripController::class, 'startTrip']);
        Route::post('/{trip}/complete', [TripController::class, 'completeTrip']);
        Route::get('/{trip}/tracking', [TripController::class, 'trackingHistory']);
        Route::get('/{trip}/students', [TripController::class, 'students']);
        Route::post('/{trip}/check-in/{student}', [TripController::class, 'checkInStudent']);
        Route::post('/{trip}/check-out/{student}', [TripController::class, 'checkOutStudent']);
    });

    // Tracking routes
    Route::prefix('tracking')->group(function () {
        Route::get('/', [TrackingController::class, 'index']);
        Route::post('/', [TrackingController::class, 'store']);
        Route::get('/{log}', [TrackingController::class, 'show']);
        Route::get('/bus/{bus}/current', [TrackingController::class, 'currentLocation']);
        Route::get('/bus/{bus}/history', [TrackingController::class, 'busHistory']);
        Route::get('/trip/{trip}/history', [TrackingController::class, 'tripHistory']);
        Route::post('/bulk', [TrackingController::class, 'bulkStore']);
        Route::get('/statistics', [TrackingController::class, 'statistics']);
        Route::get('/realtime', [TrackingController::class, 'realtime']);

        // ETA and proximity routes
        Route::post('/calculate-eta', [TrackingController::class, 'calculateETA']);
        Route::get('/routes/{trip}/etas', [TrackingController::class, 'getRouteETAs']);
        Route::get('/routes/{route}/stops/{stop}/eta', [TrackingController::class, 'getStopETA']);
        Route::post('/check-proximity', [TrackingController::class, 'checkProximity']);
        Route::get('/cached-eta', [TrackingController::class, 'getCachedETA']);
    });

    // Alert management routes
    Route::prefix('alerts')->group(function () {
        Route::get('/', [AlertController::class, 'index']);
        Route::post('/', [AlertController::class, 'store']);
        Route::get('/{alert}', [AlertController::class, 'show']);
        Route::patch('/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
        Route::patch('/{alert}/resolve', [AlertController::class, 'resolve']);
        Route::delete('/{alert}', [AlertController::class, 'destroy']);
        Route::post('/bulk-acknowledge', [AlertController::class, 'bulkAcknowledge']);
        Route::post('/bulk-resolve', [AlertController::class, 'bulkResolve']);
        Route::get('/statistics', [AlertController::class, 'statistics']);
        Route::get('/types', [AlertController::class, 'types']);
    });

    // Parent/Guardian management routes
    Route::prefix('guardians')->group(function () {
        Route::get('/', [ParentGuardianController::class, 'index']);
        Route::post('/', [ParentGuardianController::class, 'store']);
        Route::get('/{guardian}', [ParentGuardianController::class, 'show']);
        Route::patch('/{guardian}', [ParentGuardianController::class, 'update']);
        Route::delete('/{guardian}', [ParentGuardianController::class, 'destroy']);
        Route::post('/{guardian}/students/add', [ParentGuardianController::class, 'addStudents']);
        Route::post('/{guardian}/students/remove', [ParentGuardianController::class, 'removeStudents']);
        Route::get('/emergency-contacts', [ParentGuardianController::class, 'emergencyContacts']);
        Route::post('/{guardian}/notify', [ParentGuardianController::class, 'sendNotification']);
        Route::post('/bulk-notify', [ParentGuardianController::class, 'bulkSendNotifications']);
        Route::get('/student/{student}', [ParentGuardianController::class, 'byStudent']);
        Route::patch('/{guardian}/preferences', [ParentGuardianController::class, 'updateNotificationPreferences']);
    });

    // Stop management routes
    Route::prefix('stops')->group(function () {
        Route::get('/', [StopController::class, 'index']);
        Route::post('/', [StopController::class, 'store']);
        Route::get('/{stop}', [StopController::class, 'show']);
        Route::patch('/{stop}', [StopController::class, 'update']);
        Route::delete('/{stop}', [StopController::class, 'destroy']);
        Route::get('/route/{route}', [StopController::class, 'byRoute']);
        Route::post('/{stop}/students/add', [StopController::class, 'addStudents']);
        Route::post('/{stop}/students/remove', [StopController::class, 'removeStudents']);
        Route::patch('/route/{route}/sequence', [StopController::class, 'updateSequence']);
        Route::get('/nearby', [StopController::class, 'nearby']);
        Route::get('/pickups', [StopController::class, 'pickups']);
        Route::get('/dropoffs', [StopController::class, 'dropoffs']);
    });

    // Parent communication
    Route::prefix('communications')->group(function () {
        Route::get('/', [CommunicationController::class, 'index']);
        Route::post('/', [CommunicationController::class, 'store']);
        Route::post('/send-notification', [CommunicationController::class, 'sendNotification']);
        Route::get('/{communication}', [CommunicationController::class, 'show']);
        Route::patch('/{communication}/status', [CommunicationController::class, 'updateStatus']);
        Route::get('/notifications/templates', [CommunicationController::class, 'templates']);
    });

    // Attendance management routes
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::post('/record', [AttendanceController::class, 'recordAttendance']);
        Route::get('/summary', [AttendanceController::class, 'attendanceSummary']);
        Route::get('/{attendance}', [AttendanceController::class, 'show']);
        Route::patch('/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);
    });

    // Dashboard and analytics
    Route::get('/dashboard/stats', [StudentController::class, 'dashboardStats']);
    Route::get('/dashboard/route-efficiency', [SchoolRouteController::class, 'routeEfficiency']);
    Route::get('/dashboard/student-attendance', [BusAssignmentController::class, 'attendanceReport']);
    Route::get('/dashboard/bus-utilization', [BusController::class, 'utilizationReport']);
    Route::get('/dashboard/driver-performance', [DriverController::class, 'performanceReport']);
    Route::get('/dashboard/alert-summary', [AlertController::class, 'alertSummary']);
});
