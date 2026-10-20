<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Admin\SupervisorController;
use App\Http\Controllers\Admin\ParkingAssignmentController;
use App\Http\Controllers\Admin\ParkingController;
use App\Http\Controllers\Supervisor\DeclarationController;
use App\Http\Controllers\Director\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? auth()->user()->homeRoute() : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/administration/connexion', [AuthenticatedSessionController::class, 'createAdministrator'])->name('admin.login');
    Route::post('/administration/connexion', [AuthenticatedSessionController::class, 'store'])->name('admin.login.store');
    Route::get('/direction/connexion', [AuthenticatedSessionController::class, 'createDirector'])->name('director.login');
    Route::post('/direction/connexion', [AuthenticatedSessionController::class, 'store'])->name('director.login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/changer-mot-de-passe', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/changer-mot-de-passe', [PasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/tableau-de-bord', [DeclarationController::class, 'index'])->middleware(['can:declare-incidents', 'password.changed'])->name('dashboard');
    Route::middleware(['can:declare-incidents', 'password.changed'])->prefix('superviseur')->name('supervisor.')->group(function () {
        Route::get('/declarations', fn () => redirect()->route('dashboard'))->name('dashboard');
        Route::post('/incidents', [DeclarationController::class, 'incident'])->name('incidents.store');
        Route::post('/rien-a-signaler', [DeclarationController::class, 'ras'])->name('ras.store');
    });
    Route::get('/directeur', [DashboardController::class, 'index'])->middleware(['can:view-all-declarations', 'password.changed'])->name('director.dashboard');
    Route::get('/directeur/notifications', [\App\Http\Controllers\Director\NotificationController::class, 'index'])
        ->middleware(['can:view-all-declarations', 'password.changed'])->name('director.notifications');

    Route::prefix('administration/parkings')->name('admin.parkings.')
        ->middleware(['can:manage-users', 'password.changed'])->group(function () {
            Route::get('/', [ParkingController::class, 'index'])->name('index');
            Route::get('/ajouter', [ParkingController::class, 'create'])->name('create');
            Route::post('/', [ParkingController::class, 'store'])->name('store');
            Route::get('/{parking}/modifier', [ParkingController::class, 'edit'])->name('edit');
            Route::put('/{parking}', [ParkingController::class, 'update'])->name('update');
        });

    Route::prefix('administration/superviseurs')->name('admin.supervisors.')
        ->middleware(['can:manage-users', 'password.changed'])->group(function () {
            Route::get('/', [SupervisorController::class, 'index'])->name('index');
            Route::get('/ajouter', [SupervisorController::class, 'create'])->name('create');
            Route::post('/', [SupervisorController::class, 'store'])->name('store');
            Route::get('/{supervisor}/modifier', [SupervisorController::class, 'edit'])->name('edit');
            Route::put('/{supervisor}', [SupervisorController::class, 'update'])->name('update');
            Route::get('/{supervisor}/desactiver', [SupervisorController::class, 'confirmDeactivation'])->name('deactivate');
            Route::patch('/{supervisor}/statut', [SupervisorController::class, 'updateStatus'])->name('status');
            Route::get('/{supervisor}/mot-de-passe', [SupervisorController::class, 'editPassword'])->name('password.edit');
            Route::put('/{supervisor}/mot-de-passe', [SupervisorController::class, 'resetPassword'])->name('password.reset');
            Route::get('/{supervisor}/parkings', [ParkingAssignmentController::class, 'edit'])->name('parkings.edit');
            Route::put('/{supervisor}/parkings', [ParkingAssignmentController::class, 'update'])->name('parkings.update');
        });
});
