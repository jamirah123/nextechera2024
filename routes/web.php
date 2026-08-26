<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deployments\DeploymentController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Guards\GuardController;
use App\Http\Controllers\Organization\ClientController;
use App\Http\Controllers\Organization\ManpowerCoverageController;
use App\Http\Controllers\Organization\OrganizationDashboardController;
use App\Http\Controllers\Organization\RegionController;
use App\Http\Controllers\Organization\SiteController;
use App\Http\Controllers\Organization\SupervisorController;
use App\Http\Controllers\Shifts\ShiftController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', GlobalSearchController::class)->name('search');

    Route::get('/organization', OrganizationDashboardController::class)->name('organization.index');
    Route::get('/manpower-coverage', [ManpowerCoverageController::class, 'index'])->name('manpower.coverage');
    Route::get('/manpower-coverage/export', [ManpowerCoverageController::class, 'export'])->name('manpower.coverage.export');

    Route::resource('regions', RegionController::class);
    Route::resource('supervisors', SupervisorController::class);
    Route::resource('clients', ClientController::class);
    Route::resource('sites', SiteController::class);
    Route::resource('guards', GuardController::class);

    Route::get('/deployments', [DeploymentController::class, 'index'])->name('deployments.index');
    Route::get('/deployments/create', [DeploymentController::class, 'create'])->name('deployments.create');
    Route::post('/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
    Route::get('/deployments/{deployment}/transfer', [DeploymentController::class, 'transferForm'])->name('deployments.transfer');
    Route::post('/deployments/{deployment}/transfer', [DeploymentController::class, 'transfer'])->name('deployments.transfer.store');
    Route::post('/deployments/{deployment}/end', [DeploymentController::class, 'end'])->name('deployments.end');

    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/calendar', [ShiftController::class, 'calendar'])->name('shifts.calendar');
    Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/recurring/create', [ShiftController::class, 'recurringCreate'])->name('shifts.recurring.create');
    Route::post('/shifts/recurring', [ShiftController::class, 'recurringStore'])->name('shifts.recurring.store');
    Route::post('/shifts/validate', [ShiftController::class, 'validatePreview'])->name('shifts.validate');
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::get('/shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::post('/shifts/{shift}/status', [ShiftController::class, 'updateStatus'])->name('shifts.status');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
