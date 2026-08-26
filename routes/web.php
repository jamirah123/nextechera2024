<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deployments\DeploymentController;
use App\Http\Controllers\Finance\BillingController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ProfitabilityController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Guards\GuardController;
use App\Http\Controllers\Organization\ClientController;
use App\Http\Controllers\Organization\ManpowerCoverageController;
use App\Http\Controllers\Organization\OrganizationDashboardController;
use App\Http\Controllers\Organization\RegionController;
use App\Http\Controllers\Organization\SiteController;
use App\Http\Controllers\Organization\SupervisorController;
use App\Http\Controllers\Hr\AbsenceController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DesertionController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Dashboards\OperationalDashboardController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Shifts\ReplacementController;
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
    Route::get('/ops-dashboards', [OperationalDashboardController::class, 'company'])->name('ops-dashboards.company');
    Route::get('/ops-dashboards/regions/{region}', [OperationalDashboardController::class, 'region'])->name('ops-dashboards.region');
    Route::get('/ops-dashboards/sites/{site}', [OperationalDashboardController::class, 'site'])->name('ops-dashboards.site');
    Route::get('/ops-dashboards/guards/{guard}', [OperationalDashboardController::class, 'guard'])->name('ops-dashboards.guard');
    Route::get('/search', GlobalSearchController::class)->name('search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');

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

    Route::get('/replacements', [ReplacementController::class, 'index'])->name('replacements.index');
    Route::get('/replacements/create', [ReplacementController::class, 'create'])->name('replacements.create');
    Route::post('/replacements', [ReplacementController::class, 'store'])->name('replacements.store');
    Route::get('/replacements/{replacement}', [ReplacementController::class, 'show'])->name('replacements.show');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::get('/leaves/{leave}', [LeaveController::class, 'show'])->name('leaves.show');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('/leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');
    Route::post('/leaves/{leave}/complete', [LeaveController::class, 'complete'])->name('leaves.complete');

    Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
    Route::get('/absences/create', [AbsenceController::class, 'create'])->name('absences.create');
    Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store');
    Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->name('absences.show');
    Route::post('/absences/{absence}/clear', [AbsenceController::class, 'clear'])->name('absences.clear');

    Route::get('/desertions', [DesertionController::class, 'index'])->name('desertions.index');
    Route::get('/desertions/create', [DesertionController::class, 'create'])->name('desertions.create');
    Route::post('/desertions', [DesertionController::class, 'store'])->name('desertions.store');
    Route::get('/desertions/{desertion}', [DesertionController::class, 'show'])->name('desertions.show');
    Route::post('/desertions/{desertion}/status', [DesertionController::class, 'updateStatus'])->name('desertions.status');

    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('/attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
    Route::post('/attendances', [AttendanceController::class, 'store'])->name('attendances.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/monthly-shifts', [ReportController::class, 'monthlyShifts'])->name('reports.monthly-shifts');
    Route::get('/reports/monthly-shifts/export', [ReportController::class, 'exportMonthlyShifts'])->name('reports.monthly-shifts.export');
    Route::get('/reports/daily-shifts', [ReportController::class, 'dailyShifts'])->name('reports.daily-shifts');
    Route::get('/reports/daily-shifts/export', [ReportController::class, 'exportDailyShifts'])->name('reports.daily-shifts.export');
    Route::get('/reports/weekly-shifts', [ReportController::class, 'weeklyShifts'])->name('reports.weekly-shifts');
    Route::get('/reports/weekly-shifts/export', [ReportController::class, 'exportWeeklyShifts'])->name('reports.weekly-shifts.export');
    Route::get('/reports/guards', [ReportController::class, 'guards'])->name('reports.guards');
    Route::get('/reports/guards/export', [ReportController::class, 'exportGuards'])->name('reports.guards.export');
    Route::get('/reports/deployments', [ReportController::class, 'deployments'])->name('reports.deployments');
    Route::get('/reports/deployments/export', [ReportController::class, 'exportDeployments'])->name('reports.deployments.export');
    Route::get('/reports/hr', [ReportController::class, 'hr'])->name('reports.hr');
    Route::get('/reports/hr/export', [ReportController::class, 'exportHr'])->name('reports.hr.export');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/export', [BillingController::class, 'export'])->name('billing.export');
    Route::get('/billing/create', [BillingController::class, 'create'])->name('billing.create');
    Route::post('/billing', [BillingController::class, 'store'])->name('billing.store');
    Route::get('/billing/{billing}', [BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/{billing}/edit', [BillingController::class, 'edit'])->name('billing.edit');
    Route::put('/billing/{billing}', [BillingController::class, 'update'])->name('billing.update');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/export', [InvoiceController::class, 'exportDocument'])->name('invoices.export-document');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    Route::get('/profitability', [ProfitabilityController::class, 'index'])->name('profitability.index');
    Route::get('/profitability/export', [ProfitabilityController::class, 'export'])->name('profitability.export');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password');
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

    Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/backup', [SystemSettingController::class, 'backup'])->name('settings.backup');
    Route::post('/settings/production-check', [SystemSettingController::class, 'productionCheck'])->name('settings.production-check');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
