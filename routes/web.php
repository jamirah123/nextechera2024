<?php

use App\Http\Controllers\Admin\ArchivedRecordController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DataImportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\EmailDeliveryController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dashboards\OperationalDashboardController;
use App\Http\Controllers\Deployments\DeploymentController;
use App\Http\Controllers\Finance\AdvanceController;
use App\Http\Controllers\Finance\BillingController;
use App\Http\Controllers\Finance\GuardAdvanceController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\Ledger\BankReconciliationController;
use App\Http\Controllers\Finance\Ledger\GlAccountController;
use App\Http\Controllers\Finance\Ledger\GlJournalController;
use App\Http\Controllers\Finance\Ledger\GlPeriodController;
use App\Http\Controllers\Finance\Ledger\LedgerDashboardController;
use App\Http\Controllers\Finance\Ledger\LedgerReportController;
use App\Http\Controllers\Finance\Ledger\PurchaseInvoiceController;
use App\Http\Controllers\Finance\Ledger\VatPackController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PayrollPayslipController;
use App\Http\Controllers\Finance\PayrollRunController;
use App\Http\Controllers\Finance\ProfitabilityController;
use App\Http\Controllers\Finance\StaffAdvanceController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Guards\GuardController;
use App\Http\Controllers\Guards\GuardSalaryRevisionController;
use App\Http\Controllers\Guards\GuardUniformChargeRevisionController;
use App\Http\Controllers\Hr\AbsenceController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DesertionController;
use App\Http\Controllers\Hr\GuardAssetController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\LeaveTypeController;
use App\Http\Controllers\Hr\EmployeePromotionController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Hr\StaffController;
use App\Http\Controllers\Hr\StaffSalaryRevisionController;
use App\Http\Controllers\NavigationBadgeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operations\IncidentController;
use App\Http\Controllers\Operations\WorkOrderController;
use App\Http\Controllers\Organization\ClientController;
use App\Http\Controllers\Organization\ManpowerCoverageController;
use App\Http\Controllers\Operations\OperationalPeriodController;
use App\Http\Controllers\Organization\OrganizationDashboardController;
use App\Http\Controllers\Organization\RegionController;
use App\Http\Controllers\Organization\SiteController;
use App\Http\Controllers\Organization\SupervisorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\UniformChargeReportController;
use App\Http\Controllers\Shifts\ReplacementController;
use App\Http\Controllers\Shifts\ShiftController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/ops-dashboards', [OperationalDashboardController::class, 'company'])->name('ops-dashboards.company');
    Route::get('/ops-dashboards/regions/{region}', [OperationalDashboardController::class, 'region'])->name('ops-dashboards.region');
    Route::get('/ops-dashboards/sites/{site}', [OperationalDashboardController::class, 'site'])->name('ops-dashboards.site');
    Route::get('/ops-dashboards/guards/{guard}', [OperationalDashboardController::class, 'guard'])->name('ops-dashboards.guard');
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:search')->name('search');
    Route::get('/navigation/badges', NavigationBadgeController::class)->name('navigation.badges');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');
    Route::post('/notifications/{auditLog}/state', [NotificationController::class, 'updateState'])->name('notifications.state');

    Route::get('/organization', OrganizationDashboardController::class)->name('organization.index');
    Route::get('/manpower-coverage', [ManpowerCoverageController::class, 'index'])->name('manpower.coverage');
    Route::get('/manpower-coverage/report', [ManpowerCoverageController::class, 'report'])->name('manpower.deficit-report');
    Route::get('/manpower-coverage/report/export', [ManpowerCoverageController::class, 'exportReport'])
        ->middleware('throttle:exports')
        ->name('manpower.deficit-report.export');
    Route::post('/manpower-coverage/monitor', [ManpowerCoverageController::class, 'updateMonitor'])
        ->middleware('throttle:mutations')
        ->name('manpower.monitor.update');
    Route::get('/manpower-coverage/export', [ManpowerCoverageController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('manpower.coverage.export');
    Route::post('/manpower-coverage/gaps/{gap}/overtime', [ManpowerCoverageController::class, 'resolveOvertime'])
        ->middleware('throttle:mutations')
        ->name('manpower.gaps.overtime');
    Route::get('/operations/periods', [OperationalPeriodController::class, 'index'])->name('operations.periods.index');
    Route::post('/operations/periods/{period}/close', [OperationalPeriodController::class, 'close'])->name('operations.periods.close');
    Route::post('/operations/periods/{period}/reopen', [OperationalPeriodController::class, 'reopen'])->name('operations.periods.reopen');

    Route::resource('regions', RegionController::class);
    Route::resource('supervisors', SupervisorController::class);
    Route::get('/supervisors/{supervisor}/deploy', [SupervisorController::class, 'deployForm'])->name('supervisors.deploy');
    Route::post('/supervisors/{supervisor}/region-transfers', [SupervisorController::class, 'transfer'])
        ->middleware('throttle:mutations')
        ->name('supervisors.region-transfers.store');
    Route::post('/supervisors/{supervisor}/deploy', [SupervisorController::class, 'deploy'])
        ->middleware('throttle:mutations')
        ->name('supervisors.deploy.store');
    Route::resource('clients', ClientController::class);
    Route::resource('sites', SiteController::class);
    Route::resource('guards', GuardController::class);
    Route::post('/guards/{guard}/promotions', [EmployeePromotionController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('guards.promotions.store');
    Route::post('/guards/{guard}/salary-revisions', [GuardSalaryRevisionController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('guards.salary-revisions.store');
    Route::post('/guards/{guard}/uniform-charge-revisions', [GuardUniformChargeRevisionController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('guards.uniform-charge-revisions.store');
    Route::get('/guards/{guard}/attachments/{attachment}', [GuardController::class, 'showAttachment'])->name('guards.attachments.show');
    Route::get('/guards/{guard}/attachments/{attachment}/stream', [GuardController::class, 'streamAttachment'])->name('guards.attachments.stream');
    Route::get('/guards/{guard}/attachments/{attachment}/download', [GuardController::class, 'downloadAttachment'])->name('guards.attachments.download');
    Route::get('/guards/{guard}/termination-letter', [GuardController::class, 'downloadTerminationLetter'])->name('guards.termination-letter');
    Route::patch('/guards/{guard}/attachments/{attachment}', [GuardController::class, 'updateAttachment'])->name('guards.attachments.update');
    Route::delete('/guards/{guard}/attachments/{attachment}', [GuardController::class, 'destroyAttachment'])->name('guards.attachments.destroy');

    Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
    Route::post('/positions', [PositionController::class, 'store'])->middleware('throttle:mutations')->name('positions.store');
    Route::put('/positions/{position}', [PositionController::class, 'update'])->middleware('throttle:mutations')->name('positions.update');
    Route::resource('staff', StaffController::class);
    Route::post('/staff/{staff}/salary-revisions', [StaffSalaryRevisionController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('staff.salary-revisions.store');
    Route::post('/staff/{staff}/advances', [StaffAdvanceController::class, 'store'])->name('staff.advances.store');
    Route::post('/staff/{staff}/advances/{advance}/write-off', [StaffAdvanceController::class, 'writeOff'])->name('staff.advances.write-off');

    Route::get('/deployments', [DeploymentController::class, 'index'])->name('deployments.index');
    Route::get('/deployments/board', [DeploymentController::class, 'board'])->name('deployments.board');
    Route::post('/deployments/board', [DeploymentController::class, 'boardStore'])
        ->middleware('throttle:mutations')
        ->name('deployments.board.store');
    Route::get('/deployments/create', [DeploymentController::class, 'create'])->name('deployments.create');
    Route::post('/deployments', [DeploymentController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('deployments.store');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
    Route::get('/deployments/{deployment}/edit', [DeploymentController::class, 'edit'])->name('deployments.edit');
    Route::put('/deployments/{deployment}', [DeploymentController::class, 'update'])->name('deployments.update');
    Route::get('/deployments/{deployment}/letter', [DeploymentController::class, 'downloadLetter'])->name('deployments.letter');
    Route::get('/deployments/{deployment}/transfer', [DeploymentController::class, 'transferForm'])->name('deployments.transfer');
    Route::get('/deployments/transfers/{transfer}/letter', [DeploymentController::class, 'downloadTransferLetter'])->name('deployments.transfers.letter');
    Route::post('/deployments/{deployment}/transfer', [DeploymentController::class, 'transfer'])
        ->middleware('throttle:mutations')
        ->name('deployments.transfer.store');
    Route::post('/deployments/{deployment}/end', [DeploymentController::class, 'end'])
        ->middleware('throttle:mutations')
        ->name('deployments.end');

    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/calendar', [ShiftController::class, 'calendar'])->name('shifts.calendar');
    Route::get('/shifts/allocate', [ShiftController::class, 'allocate'])->name('shifts.allocate');
    Route::post('/shifts/allocate', [ShiftController::class, 'allocateStore'])
        ->middleware('throttle:mutations')
        ->name('shifts.allocate.store');
    Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [ShiftController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('shifts.store');
    Route::get('/shifts/recurring/create', [ShiftController::class, 'recurringCreate'])->name('shifts.recurring.create');
    Route::post('/shifts/recurring', [ShiftController::class, 'recurringStore'])
        ->middleware('throttle:mutations')
        ->name('shifts.recurring.store');
    Route::post('/shifts/validate', [ShiftController::class, 'validatePreview'])->name('shifts.validate');
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::get('/shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::post('/shifts/{shift}/status', [ShiftController::class, 'updateStatus'])->name('shifts.status');

    Route::get('/replacements', [ReplacementController::class, 'index'])->name('replacements.index');
    Route::get('/replacements/create', [ReplacementController::class, 'create'])->name('replacements.create');
    Route::post('/replacements', [ReplacementController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('replacements.store');
    Route::get('/replacements/{replacement}', [ReplacementController::class, 'show'])->name('replacements.show');

    Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::post('/leave-types', [LeaveTypeController::class, 'store'])->middleware('throttle:mutations')->name('leave-types.store');
    Route::put('/leave-types/{leaveTypeConfig}', [LeaveTypeController::class, 'update'])->middleware('throttle:mutations')->name('leave-types.update');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/export', [LeaveController::class, 'export'])->name('leaves.export');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::get('/leaves/{leave}/letter', [LeaveController::class, 'downloadLetter'])->name('leaves.letter');
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

    Route::get('/assets', [GuardAssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [GuardAssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [GuardAssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [GuardAssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [GuardAssetController::class, 'edit'])->name('assets.edit');
    Route::put('/assets/{asset}', [GuardAssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [GuardAssetController::class, 'destroy'])->name('assets.destroy');
    Route::post('/assets/{asset}/return', [GuardAssetController::class, 'returnItems'])->name('assets.return');

    Route::get('/desertions', [DesertionController::class, 'index'])->name('desertions.index');
    Route::get('/desertions/create', [DesertionController::class, 'create'])->name('desertions.create');
    Route::post('/desertions', [DesertionController::class, 'store'])->name('desertions.store');
    Route::get('/desertions/{desertion}', [DesertionController::class, 'show'])->name('desertions.show');
    Route::post('/desertions/{desertion}/status', [DesertionController::class, 'updateStatus'])->name('desertions.status');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incidents/export', [IncidentController::class, 'export'])->name('incidents.export');
    Route::get('/incidents/daily-report/pdf', [IncidentController::class, 'exportDailyPdf'])->name('incidents.export-daily');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::get('/incidents/{incident}/attachments/{attachment}/stream', [IncidentController::class, 'streamAttachment'])->name('incidents.attachments.stream');
    Route::get('/incidents/{incident}/attachments/{attachment}/download', [IncidentController::class, 'downloadAttachment'])->name('incidents.attachments.download');
    Route::delete('/incidents/{incident}/attachments/{attachment}', [IncidentController::class, 'destroyAttachment'])->name('incidents.attachments.destroy');

    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete');
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->name('work-orders.cancel');

    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('/attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
    Route::post('/attendances', [AttendanceController::class, 'store'])->name('attendances.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/monthly-shifts', [ReportController::class, 'monthlyShifts'])->name('reports.monthly-shifts');
    Route::get('/reports/monthly-shifts/export', [ReportController::class, 'exportMonthlyShifts'])
        ->middleware('throttle:exports')
        ->name('reports.monthly-shifts.export');
    Route::get('/reports/daily-shifts', [ReportController::class, 'dailyShifts'])->name('reports.daily-shifts');
    Route::get('/reports/daily-shifts/export', [ReportController::class, 'exportDailyShifts'])
        ->middleware('throttle:exports')
        ->name('reports.daily-shifts.export');
    Route::get('/reports/weekly-shifts', [ReportController::class, 'weeklyShifts'])->name('reports.weekly-shifts');
    Route::get('/reports/weekly-shifts/export', [ReportController::class, 'exportWeeklyShifts'])
        ->middleware('throttle:exports')
        ->name('reports.weekly-shifts.export');
    Route::get('/reports/guards', [ReportController::class, 'guards'])->name('reports.guards');
    Route::get('/reports/guards/export', [ReportController::class, 'exportGuards'])
        ->middleware('throttle:exports')
        ->name('reports.guards.export');
    Route::get('/reports/deployments', [ReportController::class, 'deployments'])->name('reports.deployments');
    Route::get('/reports/deployments/export', [ReportController::class, 'exportDeployments'])
        ->middleware('throttle:exports')
        ->name('reports.deployments.export');
    Route::get('/reports/uniform-charge-exemptions', [UniformChargeReportController::class, 'index'])->name('reports.uniform-exemptions');
    Route::get('/reports/hr', [ReportController::class, 'hr'])->name('reports.hr');
    Route::get('/reports/hr/export', [ReportController::class, 'exportHr'])
        ->middleware('throttle:exports')
        ->name('reports.hr.export');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/export', [BillingController::class, 'export'])->name('billing.export');
    Route::get('/billing/create', [BillingController::class, 'create'])->name('billing.create');
    Route::post('/billing', [BillingController::class, 'store'])->name('billing.store');
    Route::get('/billing/{billing}', [BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/{billing}/pdf', [BillingController::class, 'downloadPdf'])->name('billing.pdf');
    Route::get('/billing/{billing}/edit', [BillingController::class, 'edit'])->name('billing.edit');
    Route::put('/billing/{billing}', [BillingController::class, 'update'])->name('billing.update');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
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

    Route::get('/advances', [AdvanceController::class, 'index'])->name('advances.index');
    Route::get('/advances/export', [AdvanceController::class, 'export'])->name('advances.export');

    Route::get('/ledger', [LedgerDashboardController::class, '__invoke'])->name('ledger.index');
    Route::get('/ledger/accounts', [GlAccountController::class, 'index'])->name('ledger.accounts.index');
    Route::post('/ledger/accounts', [GlAccountController::class, 'store'])->name('ledger.accounts.store');
    Route::get('/ledger/journals', [GlJournalController::class, 'index'])->name('ledger.journals.index');
    Route::get('/ledger/journals/create', [GlJournalController::class, 'create'])->name('ledger.journals.create');
    Route::post('/ledger/journals', [GlJournalController::class, 'store'])->name('ledger.journals.store');
    Route::get('/ledger/journals/{journal}', [GlJournalController::class, 'show'])->name('ledger.journals.show');
    Route::get('/ledger/purchases', [PurchaseInvoiceController::class, 'index'])->name('ledger.purchases.index');
    Route::get('/ledger/purchases/create', [PurchaseInvoiceController::class, 'create'])->name('ledger.purchases.create');
    Route::post('/ledger/purchases', [PurchaseInvoiceController::class, 'store'])->name('ledger.purchases.store');
    Route::get('/ledger/purchases/{purchase}', [PurchaseInvoiceController::class, 'show'])->name('ledger.purchases.show');
    Route::get('/ledger/purchases/{purchase}/edit', [PurchaseInvoiceController::class, 'edit'])->name('ledger.purchases.edit');
    Route::put('/ledger/purchases/{purchase}', [PurchaseInvoiceController::class, 'update'])->name('ledger.purchases.update');
    Route::post('/ledger/purchases/{purchase}/post', [PurchaseInvoiceController::class, 'post'])->name('ledger.purchases.post');
    Route::post('/ledger/purchases/{purchase}/cancel', [PurchaseInvoiceController::class, 'cancel'])->name('ledger.purchases.cancel');
    Route::get('/ledger/reports/trial-balance', [LedgerReportController::class, 'trialBalance'])->name('ledger.reports.trial-balance');
    Route::get('/ledger/reports/profit-loss', [LedgerReportController::class, 'profitAndLoss'])->name('ledger.reports.profit-loss');
    Route::get('/ledger/periods', [GlPeriodController::class, 'index'])->name('ledger.periods.index');
    Route::post('/ledger/periods/{period}/close', [GlPeriodController::class, 'close'])->name('ledger.periods.close');
    Route::post('/ledger/periods/{period}/reopen', [GlPeriodController::class, 'reopen'])->name('ledger.periods.reopen');
    Route::get('/ledger/vat', [VatPackController::class, 'index'])->name('ledger.vat.index');
    Route::get('/ledger/vat/export', [VatPackController::class, 'export'])->name('ledger.vat.export');
    Route::get('/ledger/bank', [BankReconciliationController::class, 'index'])->name('ledger.bank.index');
    Route::post('/ledger/bank', [BankReconciliationController::class, 'store'])->name('ledger.bank.store');
    Route::get('/ledger/bank/{bankAccount}', [BankReconciliationController::class, 'show'])->name('ledger.bank.show');
    Route::post('/ledger/bank/{bankAccount}/lines', [BankReconciliationController::class, 'storeLine'])->name('ledger.bank.lines.store');
    Route::post('/ledger/bank/{bankAccount}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('ledger.bank.auto-match');
    Route::post('/ledger/bank/{bankAccount}/lines/{line}/match', [BankReconciliationController::class, 'match'])->name('ledger.bank.lines.match');
    Route::post('/ledger/bank/{bankAccount}/lines/{line}/unmatch', [BankReconciliationController::class, 'unmatch'])->name('ledger.bank.lines.unmatch');
    Route::post('/ledger/bank/{bankAccount}/lines/{line}/exclude', [BankReconciliationController::class, 'exclude'])->name('ledger.bank.lines.exclude');

    Route::get('/payroll', [PayrollRunController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/create', [PayrollRunController::class, 'create'])->name('payroll.create');
    Route::post('/payroll', [PayrollRunController::class, 'store'])->name('payroll.store');
    Route::get('/payroll/{payroll}', [PayrollRunController::class, 'show'])->name('payroll.show');
    Route::post('/payroll/{payroll}/calculate', [PayrollRunController::class, 'calculate'])
        ->middleware('throttle:mutations')
        ->name('payroll.calculate');
    Route::post('/payroll/{payroll}/submit', [PayrollRunController::class, 'submit'])
        ->middleware('throttle:mutations')
        ->name('payroll.submit');
    Route::post('/payroll/{payroll}/approve', [PayrollRunController::class, 'approve'])
        ->middleware('throttle:mutations')
        ->name('payroll.approve');
    Route::post('/payroll/{payroll}/reject', [PayrollRunController::class, 'reject'])
        ->middleware('throttle:mutations')
        ->name('payroll.reject');
    Route::post('/payroll/{payroll}/pay', [PayrollRunController::class, 'pay'])
        ->middleware('throttle:mutations')
        ->name('payroll.pay');
    Route::post('/payroll/{payroll}/cancel', [PayrollRunController::class, 'cancel'])
        ->middleware('throttle:mutations')
        ->name('payroll.cancel');
    Route::get('/payroll/{payroll}/export/bank', [PayrollRunController::class, 'exportBank'])
        ->middleware('throttle:exports')
        ->name('payroll.export.bank');
    Route::get('/payroll/{payroll}/export/payslips', [PayrollRunController::class, 'exportPayslips'])
        ->middleware('throttle:exports')
        ->name('payroll.export.payslips');
    Route::get('/payroll/{payroll}/payslips/{payslip}', [PayrollPayslipController::class, 'show'])->name('payroll.payslips.show');
    Route::get('/payroll/{payroll}/payslips/{payslip}/export', [PayrollPayslipController::class, 'export'])->name('payroll.payslips.export');
    Route::get('/payroll/{payroll}/payslips/{payslip}/print', [PayrollPayslipController::class, 'print'])->name('payroll.payslips.print');
    Route::post('/payroll/{payroll}/payslips/{payslip}/deductions', [PayrollPayslipController::class, 'storeDeduction'])->name('payroll.payslips.deductions.store');
    Route::delete('/payroll/{payroll}/payslips/{payslip}/deductions/{deduction}', [PayrollPayslipController::class, 'destroyDeduction'])->name('payroll.payslips.deductions.destroy');

    Route::post('/guards/{guard}/advances', [GuardAdvanceController::class, 'store'])->name('guards.advances.store');
    Route::post('/guards/{guard}/advances/{advance}/write-off', [GuardAdvanceController::class, 'writeOff'])->name('guards.advances.write-off');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('audit.export');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

    Route::get('/archived-records', [ArchivedRecordController::class, 'index'])->name('archived.index');
    Route::get('/archived-records/{deletedRecordSnapshot}', [ArchivedRecordController::class, 'show'])->name('archived.show');
    Route::post('/archived-records/{deletedRecordSnapshot}/restore', [ArchivedRecordController::class, 'restore'])->name('archived.restore');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])
        ->middleware('throttle:backups')
        ->name('backups.store');
    Route::get('/backups/{backup}', [BackupController::class, 'show'])->name('backups.show');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])
        ->middleware('throttle:exports')
        ->name('backups.download');
    Route::get('/backups/{backup}/download-files', [BackupController::class, 'downloadFiles'])
        ->middleware('throttle:exports')
        ->name('backups.download-files');
    Route::post('/backups/{backup}/verify', [BackupController::class, 'verify'])
        ->middleware('throttle:backups')
        ->name('backups.verify');
    Route::post('/backups/{backup}/test-restore', [BackupController::class, 'testRestore'])
        ->middleware('throttle:backups')
        ->name('backups.test-restore');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])
        ->middleware('throttle:backups')
        ->name('backups.restore');
    Route::post('/backups/{backup}/restore-files', [BackupController::class, 'restoreFiles'])
        ->middleware('throttle:backups')
        ->name('backups.restore-files');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password');
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}/attachments/{attachment}', [UserController::class, 'showAttachment'])->name('users.attachments.show');
    Route::get('/users/{user}/attachments/{attachment}/stream', [UserController::class, 'streamAttachment'])->name('users.attachments.stream');
    Route::get('/users/{user}/attachments/{attachment}/download', [UserController::class, 'downloadAttachment'])->name('users.attachments.download');
    Route::delete('/users/{user}/attachments/{attachment}', [UserController::class, 'destroyAttachment'])->name('users.attachments.destroy');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('/roles/permissions', [RoleController::class, 'update'])->name('roles.permissions.update');
    Route::post('/roles/permissions/clone', [RoleController::class, 'clone'])->name('roles.permissions.clone');
    Route::post('/roles/permissions/reset', [RoleController::class, 'reset'])->name('roles.permissions.reset');

    Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [SystemSettingController::class, 'removeLogo'])->name('settings.logo.remove');
    Route::delete('/settings/favicon', [SystemSettingController::class, 'removeFavicon'])->name('settings.favicon.remove');
    Route::post('/settings/backup', [SystemSettingController::class, 'backup'])
        ->middleware('throttle:backups')
        ->name('settings.backup');
    Route::post('/settings/production-check', [SystemSettingController::class, 'productionCheck'])->name('settings.production-check');
    Route::get('/settings/email-deliveries', [EmailDeliveryController::class, 'index'])->name('email-deliveries.index');
    Route::post('/settings/test-email', [EmailDeliveryController::class, 'sendTest'])->name('settings.test-email');

    Route::get('/data-import', [DataImportController::class, 'index'])->name('data-import.index');
    Route::get('/data-import/templates/{type}', [DataImportController::class, 'template'])->name('data-import.template');
    Route::post('/data-import/guards', [DataImportController::class, 'importGuards'])->name('data-import.guards');
    Route::post('/data-import/sites', [DataImportController::class, 'importSites'])->name('data-import.sites');
    Route::post('/data-import/opening-balances', [DataImportController::class, 'importOpeningBalances'])->name('data-import.opening-balances');
    Route::put('/data-import/accounting-export', [DataImportController::class, 'updateAccountingExport'])->name('data-import.accounting-export');
    Route::post('/data-import/accounting-export/run', [DataImportController::class, 'runAccountingExport'])->name('data-import.accounting-export.run');
    Route::get('/data-import/exports/{file}', [DataImportController::class, 'downloadExport'])->name('data-import.exports.download');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/documents', [ProfileController::class, 'storeDocuments'])->name('profile.attachments.store');
    Route::get('/profile/documents/{attachment}', [ProfileController::class, 'showAttachment'])->name('profile.attachments.show');
    Route::get('/profile/documents/{attachment}/stream', [ProfileController::class, 'streamAttachment'])->name('profile.attachments.stream');
    Route::get('/profile/documents/{attachment}/download', [ProfileController::class, 'downloadAttachment'])->name('profile.attachments.download');
    Route::delete('/profile/documents/{attachment}', [ProfileController::class, 'destroyAttachment'])->name('profile.attachments.destroy');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
