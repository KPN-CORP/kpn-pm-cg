<?php

use App\Http\Controllers\Admin\AppraisalController as AdminAppraisalController;
use App\Http\Controllers\Admin\OnBehalfController as AdminOnBehalfController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SendbackController as AdminSendbackController;
use App\Http\Controllers\AdminImportController;
use App\Http\Controllers\AdminTasksController;
use App\Http\Controllers\Appraisal360;
use App\Http\Controllers\AppraisalTaskController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalFlowController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\ImportGoalsController;
use App\Http\Controllers\ImportKpiController;
use App\Http\Controllers\KpiCompanyImportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportExcelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LayerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SendbackController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MyAppraisalController;
use App\Http\Controllers\MyGoalController;
use App\Http\Controllers\RatingAdminController;
use App\Http\Controllers\CalibrationController;
use App\Http\Controllers\EmployeePAController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\FormAppraisalController;
use App\Http\Controllers\FormGroupAppraisalController;
use App\Http\Controllers\PaReminderController;
use App\Http\Controllers\Proposed360;
use App\Http\Controllers\Proposed360Controller;

use App\Http\Controllers\RatingController;
use App\Http\Controllers\TeamAppraisalController;
use App\Http\Controllers\TeamGoalController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WeightageController;
use App\Imports\ApprovalLayerAppraisalImport;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\NotificationMiddleware;

Route::get('language/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');

Route::get('dbauth', [SsoController::class, 'dbauth']);
Route::get('sourcermb/dbauth', [SsoController::class, 'dbauthReimburse']);
Route::get('facecard/dbauth', [SsoController::class, 'dbauthFacecard']);
Route::get('auth-service', [SsoController::class, 'handleJWTAuth']);

Route::get('fetch-employees', [EmployeeController::class, 'fetchAndStoreEmployees']);
Route::get('updmenu-employees', [EmployeeController::class, 'updateEmployeeAccessMenu']);
Route::get('daily-schedules', [ScheduleController::class, 'reminderDailySchedules']);
Route::get('schedule-PA', [ScheduleController::class, 'DailyUpdateSchedulePA']);

Route::get('wa-employee/{id}', [EmployeeController::class, 'getEmployeeFromWA']);

Route::get('/test-email', function () {
    $messages = '<p>This is a test message with <strong>bold</strong> text.</p>';
    $name = 'John Doe';

    return view('email.reminderschedule', compact('messages', 'name'));
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.store');
                
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                    ->name('password.request');
                    
    Route::get('reset-password-email', [PasswordResetLinkController::class, 'selfResetView']);
    
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                    ->name('password.email');    
});


Route::middleware('auth', 'locale', 'notification')->group(function () {

    Route::get('/', function () {
        return redirect('goals');
    });

    Route::get('/search-employee', [SearchController::class, 'searchEmployee']);

    Route::get('reset-self', [PasswordResetLinkController::class, 'selfReset'])
                ->name('password.reset.self');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tasks
    Route::get('/tasks', [TaskController::class, 'task'])->name('tasks');

    // My Goals
    Route::get('/goals', [MyGoalController::class, 'index'])->name('goals');
    Route::get('/goals/detail/{id}', [MyGoalController::class, 'show'])->name('goals.detail');
    Route::get('/goals/form/{id}', [MyGoalController::class, 'create'])->name('goals.form');
    Route::post('/goals/submit', [MyGoalController::class, 'store'])->name('goals.submit');
    Route::get('/goals/edit/{id}', [MyGoalController::class, 'edit'])->name('goals.edit');
    Route::post('/goals/update', [MyGoalController::class, 'update'])->name('goals.update');
    // routes/web.php
    Route::get('/goals/latest/{id}', [MyGoalController::class, 'latest'])->name('goals.latest');


    // Team Goals
    Route::get('/team-goals', [TeamGoalController::class, 'index'])->name('team-goals');
    Route::get('/team-goals/detail/{id}', [TeamGoalController::class, 'show'])->name('team-goals.detail');
    Route::get('/team-goals/form/{id}', [TeamGoalController::class, 'create'])->name('team-goals.form');
    Route::post('/team-goals/submit', [TeamGoalController::class, 'store'])->name('team-goals.submit');
    Route::get('/team-goals/edit/{id}', [TeamGoalController::class, 'edit'])->name('team-goals.edit');
    Route::get('/team-goals/approval/{id}', [TeamGoalController::class, 'approval'])->name('team-goals.approval');
    Route::get('/get-tooltip-content', [TeamGoalController::class, 'getTooltipContent']);
    Route::get('/units-of-measurement', [TeamGoalController::class, 'unitOfMeasurement']);
    Route::post('/import-goals-manager', [TeamGoalController::class, 'import'])->name('importgoalsmanager');
    Route::get('/export-invalid-goal', [TeamGoalController::class, 'exportInvalidGoal'])->name('export.invalid.goal');


    // My Appraisal
    Route::get('/appraisals', [MyAppraisalController::class, 'index'])->name('appraisals');
    
    Route::get('/appraisals/create/{id}', [MyAppraisalController::class, 'create'])->name('form.appraisal');
    Route::get('/appraisals/edit/{id}', [MyAppraisalController::class, 'edit'])->name('edit.appraisal');
    Route::post('/appraisals/submit', [MyAppraisalController::class, 'store'])->name('appraisal.submit');
    Route::post('/appraisals/update', [MyAppraisalController::class, 'update'])->name('appraisal.update');
    Route::post('/appraisals/update', [MyAppraisalController::class, 'update'])->name('appraisal.update');
    Route::post('/appraisals/file/destroy', [MyAppraisalController::class, 'destroyFile'])->name('delete.attachment');
    
    // Team Appraisal
    Route::get('/appraisals-task', [AppraisalTaskController::class, 'index'])->name('appraisals-task');
    Route::get('/appraisals-task/detail/{id}', [AppraisalTaskController::class, 'detail'])->name('appraisals-task.detail');
    Route::post('/appraisals-task/submit', [AppraisalTaskController::class, 'storeInitiate'])->name('appraisals-task.submit');
    Route::post('/appraisals-task/submitReview', [AppraisalTaskController::class, 'storeReview'])->name('appraisals-task.submitReview');
    Route::get('/appraisals-task/approval/{id}', [AppraisalTaskController::class, 'approval'])->name('appraisals-task.approval');
    Route::get('/appraisals-task/initiate/{id}', [AppraisalTaskController::class, 'initiate'])->name('appraisals-task.initiate');

    Route::get('/appraisals-task/teams-data', [AppraisalTaskController::class, 'getTeamData']);
    Route::get('/appraisals-task/360-data', [AppraisalTaskController::class, 'get360Data']);
    
    // Appraisal 360
    Route::get('/appraisals-task/review/{id}', [AppraisalTaskController::class, 'review'])->name('appraisals-360.review');
    
    // Rating | Calibration
    Route::get('/rating', [RatingController::class, 'index'])->name('rating');
    Route::post('/rating-submit', [RatingController::class, 'store'])->name('rating.submit');

    Route::get('/export-ratings/{level}', [RatingController::class, 'exportToExcel'])->name('rating.export');
    Route::post('/rating/import', [RatingController::class, 'importFromExcel'])->name('rating.import');
    Route::get('/export-invalid-rating', [RatingController::class, 'exportInvalidRating'])->name('export.invalid.rating');

    
    // Approval
    Route::post('/approval/goal', [ApprovalController::class, 'store'])->name('approval.goal');

    // Sendback
    Route::post('/sendback/goal', [SendbackController::class, 'store'])->name('sendback.goal');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    Route::get('export/employees', [ExportExcelController::class, 'export'])->name('export.employee');
    Route::get('/get-report-content/{reportType}', [ReportController::class, 'getReportContent']);
    Route::get('/export/report-emp', [ExportExcelController::class, 'exportreportemp'])->name('export.reportemp');

    Route::post('/export', [ExportExcelController::class, 'export'])->name('export');
    Route::post('/admin-export', [ExportExcelController::class, 'exportAdmin'])->name('admin.export');
    Route::post('/notInitiatedReport', [ExportExcelController::class, 'notInitiated'])->name('team-goals.notInitiated');
    Route::post('/initiatedReport', [ExportExcelController::class, 'initiated'])->name('team-goals.initiated');
    // Route::get('/export/goals', [ReportController::class, 'exportGoal'])->name('export.goal');
    Route::post('/get-report-content', [ReportController::class, 'getReportContent'])->name('reports.content');
    
    Route::get('/changes-group-company', [ReportController::class, 'changesGroupCompany']);
    Route::get('/changes-company', [ReportController::class, 'changesCompany']);
    
    // Authentication
    
    Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('{first}/{second}', [HomeController::class, 'secondLevel'])->name('second');

    Route::get('/guides', [GuideController::class, 'index'])->name('guides');
    Route::post('/guides', [GuideController::class, 'store'])->name('upload.guide');
    Route::delete('/guides-delete/{id}', [GuideController::class, 'destroy'])->name('delete.guide');

    // Proposed 360
    Route::middleware(['flow_access:Propose 360'])->group(function () {
        Route::get('/proposed360', [Proposed360Controller::class, 'index'])->name('proposed360');
        Route::post('/proposed360', [Proposed360Controller::class, 'store'])->name('proposed360.store');
        Route::post('/proposed360/resubmit', [Proposed360Controller::class, 'resubmit'])->name('proposed360.resubmit');
        Route::post('/proposed360/action', [Proposed360Controller::class, 'action'])->name('proposed360.action');
    });
    
    Route::post('/approval/{approvalRequest}/action', [ApprovalController::class, 'processAction'])->name('approval.action');
    
    
    
    // ============================ Administrator ===================================

    Route::middleware(['permission:viewschedule'])->group(function () {
        // Schedule
        Route::get('/schedules', [ScheduleController::class, 'schedule'])->name('schedules');
        Route::get('/schedules-form', [ScheduleController::class, 'form'])->name('schedules.form');
        Route::post('/schedule-save', [ScheduleController::class, 'save'])->name('save-schedule');
        Route::get('/schedule/edit/{id}', [ScheduleController::class, 'edit'])->name('edit-schedule');
        Route::post('/schedule', [ScheduleController::class, 'update'])->name('update-schedule');
        // Route::delete('/schedule/{id}', [ScheduleController::class, 'softDelete'])->name('soft-delete-schedule');
        Route::delete('/schedule/{id}/delete', [ScheduleController::class, 'softDelete'])->name('soft-delete-schedule');

    });

    Route::middleware(['permission:masterrating'])->group(function () {
        // Route::resource('ratings', RatingAdminController::class);
        Route::get('/admratings', [RatingAdminController::class, 'index'])->name('admratings');
        Route::get('/pages/rating-admin/create', [RatingAdminController::class, 'create'])->name('pages.rating-admin.create');
        Route::get('/pages/rating-admin/update/{id}', [RatingAdminController::class, 'show'])->name('pages.rating-admin.update');
        Route::post('/admratings/submit', [RatingAdminController::class, 'store'])->name('admratings.store');
        Route::delete('/rating-admin/{id}', [RatingAdminController::class, 'destroy'])->name('rating-admin-destroy');
        Route::delete('/detail-rating-admin/{id}', [RatingAdminController::class, 'destroyDetail'])->name('detail-rating-admin-destroy');
        Route::get('/rating-admin/{id}/edit', [RatingAdminController::class, 'edit'])->name('rating-admin.edit');
    });

    Route::middleware(['permission:mastercalibration'])->group(function () {
        Route::get('/admcalibrations', [CalibrationController::class, 'index'])->name('admcalibrations');
        Route::get('/CalibrationsCreate', [CalibrationController::class, 'create'])->name('calibrations-create');
        Route::post('/CalibrationsCreateShow', [CalibrationController::class, 'show'])->name('showcalibrations');
        Route::post('/CalibrationsStore', [CalibrationController::class, 'store'])->name('savecalibrations');
        Route::get('/update/Calibrations/{id}', [CalibrationController::class, 'formupdate'])->name('update.Calibrations');
        Route::delete('/calibrationDestroy/{id}', [CalibrationController::class, 'destroy'])->name('calibrationDestroy');
        Route::post('/CalibrationsUpdate', [CalibrationController::class, 'update'])->name('updatecalibrations');
    });

    Route::middleware(['permission:employeepa'])->group(function () {
        Route::get('/admemployees', [EmployeePAController::class, 'index'])->name('admemployee');
        Route::delete('/admemployeedestroy', [EmployeePAController::class, 'destroy'])->name('admemployeeDestroy');
        Route::put('/employeepa/update', [EmployeePAController::class, 'update'])->name('employeepa.update');
        Route::get('/export-employeepa', [EmployeePAController::class, 'exportEmployeepa'])->name('employeepa.export');
    });

    Route::middleware(['permission:masterweightage'])->group(function () {
        Route::get('/admin-weightage', [WeightageController::class, 'index'])->name('admin-weightage');
        Route::get('/create-weightage', [WeightageController::class, 'create'])->name('admin-weightage.create');
        Route::get('/admin-weightage/detail/{id}', [WeightageController::class, 'detail'])->name('admin-weightage.detail');
        Route::get('/admin-weightage/edit/{id}', [WeightageController::class, 'edit'])->name('admin-weightage.edit');
        Route::get('/admin-weightage/archive/{id}', [WeightageController::class, 'archive'])->name('admin-weightage.archive');
        Route::post('/archive-weightage', [WeightageController::class, 'archiving'])->name('archive-weightage');
        Route::post('/admin-weightage/submit', [WeightageController::class, 'store'])->name('admin-weightage.submit');
        Route::post('/admin-weightage/update', [WeightageController::class, 'update'])->name('admin-weightage.update');
        Route::post('/check-master-weightage', [WeightageController::class, 'checkMasterWeightage'])->name('check.master-weightage');
    });

    Route::middleware(['permission:viewlayer'])->group(function () {
        // layer
        Route::get('/layer', [LayerController::class, 'layer'])->name('layer');
        Route::post('/update-layer', [LayerController::class, 'updatelayer'])->name('update-layer');
        Route::post('/import-layer', [LayerController::class, 'importLayer'])->name('import-layer');
        Route::post('/history-show', [LayerController::class, 'show'])->name('history-show');
        
        
        Route::get('/layer-appraisal', [LayerController::class, 'layerAppraisal'])->name('layer-appraisal');
        Route::get('/layer-appraisal/edit/{id}', [LayerController::class, 'layerAppraisalEdit'])->name('layer-appraisal.edit');
        Route::post('/layer-appraisal/import', [LayerController::class, 'layerAppraisalImport'])->name('layer-appraisal.import');
        Route::post('/layer-appraisal/update', [LayerController::class, 'layerAppraisalUpdate'])->name('layer-appraisal.update');
        Route::get('/export-invalid-layer-appraisal', [LayerController::class, 'exportInvalidLayerAppraisal'])->name('export.invalid.layer.appraisal');
        Route::get('/employee-layer-appraisal/details/{employeeId}', [LayerController::class, 'getEmployeeLayerDetails']);
        
        // Admin Tasks
        Route::get('admin-tasks', [AdminTasksController::class, 'index'])->name('admin-tasks');
        Route::get('admin-tasks/detail/{id}', [AdminTasksController::class, 'detail'])->name('admin-tasks.detail');
        Route::post('admin-tasks/{id}', [AdminTasksController::class, 'action'])->name('admin-tasks.action');

        Route::get('/approval-history', [AdminTasksController::class, 'indexHistory'])
            ->name('approval-history');

        Route::get('/approval-history/detail/{id}', [AdminTasksController::class, 'detailHistory'])
            ->name('approval-history.detail');

    
    });

    Route::middleware(['permission:viewaudittrail'])->group(function () {
        Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
    });
    
    Route::middleware(['permission:viewrole'])->group(function () {
        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/admin/roles/submit', [RoleController::class, 'store'])->name('roles.store');
        Route::post('/admin/roles/update', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/admin/roles/delete/{id}', [RoleController::class, 'destroy'])->name('roles.delete');
        Route::get('/admin/roles/assign', [RoleController::class, 'assign'])->name('roles.assign');
        Route::get('/admin/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/admin/roles/manage', [RoleController::class, 'manage'])->name('roles.manage');
        Route::get('/admin/roles/get-assignment', [RoleController::class, 'getAssignment'])->name('getAssignment');
        Route::get('/admin/roles/get-permission', [RoleController::class, 'getPermission'])->name('getPermission');
        Route::post('/admin/assign-user', [RoleController::class, 'assignUser'])->name('assign.user');
    });

    Route::middleware(['permission:importgoals'])->group(function () {
        Route::get('/import-goals', [ImportGoalsController::class, 'showImportForm'])->name('importg');
        Route::post('/import-goals', [ImportGoalsController::class, 'import'])->name('importgoals');
        Route::post('/import-clustering-kpi', [ImportGoalsController::class, 'importClusteringKPI'])->name('importClusteringKPI');
        Route::get('/download-excel/{file}', [ImportGoalsController::class, 'downloadExcel'])->name('downloadExcel');
    });

    Route::middleware(['permission:importkpi'])->group(function () {
        Route::get('/import-kpi', [ImportKpiController::class, 'showImportKpiForm'])->name('importkpi');
        Route::post('/import-kpi/submit', [ImportKpiController::class, 'importKpi'])->name('importkpisubmit');
        Route::delete('/achievements/{id}', [ImportKpiController::class, 'destroy'])->name('achievements.destroy');
        Route::get('/download-template', [ImportKpiController::class, 'downloadTemplate'])->name('downloadTemplateImport');
        Route::get('/import-kpi-company', [KpiCompanyImportController::class, 'showImportForm'])->name('importkpicompany');
        Route::post('/import-kpi-company', [KpiCompanyImportController::class, 'import'])->name('importKpiCompany');
        Route::post('/download-kpi-company', [KpiCompanyImportController::class, 'downloadExcel'])->name('downloadTemplateImportKPICompany');
    });

    Route::middleware(['permission:reminderpa'])->group(function () {
        // Route::resource('pa-reminders', PaReminderController::class);
        Route::get('/reminder-pa', [PaReminderController::class, 'index'])->name('reminderpaindex');
        Route::get('/remindersCreate', [PaReminderController::class, 'create'])->name('prcreate');
        Route::post('/remindersStore', [PaReminderController::class, 'store'])->name('prstore');
        Route::delete('/reminders/{id}', [PaReminderController::class, 'destroy'])->name('reminders.destroy');
        Route::get('/reminders/{id}/edit', [PaReminderController::class, 'edit'])->name('reminders.edit');
        Route::put('/reminders/{id}', [PaReminderController::class, 'update'])->name('reminders.update');
    });
    
    Route::middleware(['permission:reportpa'])->group(function () {
        Route::get('/admin-appraisal', [AdminAppraisalController::class, 'index'])->name('admin.appraisal');
        Route::get('/admin-appraisal/details/{id}', [AdminAppraisalController::class, 'detail'])->name('admin.appraisal.details');
        
        Route::post('/check-file', [AdminAppraisalController::class, 'checkFileAvailability']); // Check file existence
        Route::post('/check-jobs', [AdminAppraisalController::class, 'checkJobAvailability']); // Check file existence
        Route::get('/appraisal-details/download/{fileName}', [AdminAppraisalController::class, 'downloadFile']);
        Route::get('/appraisal-details/delete/{fileName}', [AdminAppraisalController::class, 'deleteFile']);

        Route::get('/admin-appraisal/get-detail-data/{id}', [AdminAppraisalController::class, 'getDetailData'])->name('get.detail.data');
        Route::post('/export-appraisal-detail', [AdminAppraisalController::class, 'exportAppraisalDetail']);
    });
    
    Route::middleware(['permission:viewonbehalf'])->group(function () {
        // Approval-Admin
        Route::post('/admin/approval/goal', [AdminOnBehalfController::class, 'store'])->name('admin.approval.goal');
        Route::get('/admin/approval/goal/{id}', [AdminOnBehalfController::class, 'create'])->name('admin.create.approval.goal');
        Route::get('/admin/appraisal/{id}/{type}', [AppraisalTaskController::class, 'review'])->name('admin.create.approval.appraisal');
        Route::post('/admin/revoke-appraisal/{id}/{type}', [AppraisalTaskController::class, 'revoke'])->name('admin.revoke.approval.appraisal');

        // Goals - Admin
        Route::get('/onbehalf', [AdminOnBehalfController::class, 'index'])->name('onbehalf');
        Route::post('/admin/onbehalf/content', [AdminOnBehalfController::class, 'getOnBehalfContent'])->name('admin.onbehalf.content');
        Route::get('/admin/rating/{id}', [AdminOnBehalfController::class, 'rating'])->name('admin.onbehalfs.rating');
        // Route::get('/admin/onbehalf/{id}', [AdminOnBehalfController::class, 'getOnBehalfContent'])->name('admin.onbehalf.content');
        Route::post('/admin/goal-content', [AdminOnBehalfController::class, 'getGoalContent']);
        // Sendback
        Route::post('/admin/sendback/goal', [AdminSendbackController::class, 'store'])->name('admin.sendback.goal');
        // Revoke
        Route::post('/admin/goals-revoke', [AdminOnBehalfController::class, 'goalsRevoke'])->name('admin.goals.revoke');
    });
    
    Route::middleware(['permission:viewreport'])->group(function () {
        
        Route::get('/reports-admin', [AdminReportController::class, 'index'])->name('admin.reports');
        // Route::get('/admin/get-report-content/{reportType}', [AdminReportController::class, 'getReportContent']);
        Route::post('/admin/get-report-content', [AdminReportController::class, 'getReportContent']);
        Route::get('/admin/changes-group-company', [AdminReportController::class, 'changesGroupCompany']);
        Route::get('/admin/changes-company', [AdminReportController::class, 'changesCompany']);
        //Employee
        Route::get('/employees', [EmployeeController::class, 'employee'])->name('employees');
        Route::get('/employee/filter', [EmployeeController::class, 'filterEmployees'])->name('employee.filter');
    });

    Route::middleware(['permission:viewflowsetting'])->group(function () {
        // Approval Flow
        Route::get('/approval-flow', [ApprovalFlowController::class, 'index'])->name('approval-flow.index');
        Route::get('/approval-flow-data', [ApprovalFlowController::class, 'data'])->name('approval-flow.data');
        Route::get('/approval-flow/create/{id}', [ApprovalFlowController::class, 'create'])->name('approval-flow.create');
        Route::post('/approval-flow', [ApprovalFlowController::class, 'store'])->name('approval-flow.store');
        Route::get('/approval-flow/edit/{id}', [ApprovalFlowController::class, 'edit'])->name('approval-flow.edit');
        Route::put('/approval-flow/{id}', [ApprovalFlowController::class, 'update'])->name('approval-flow.update');
        Route::delete('/approval-flow/{id}', [ApprovalFlowController::class, 'destroy'])->name('approval-flow.destroy');
        
        // Flow
        Route::get('/flows', [FlowController::class, 'index'])->name('flows.index');
        Route::get('/flows-data', [FlowController::class, 'data'])->name('flows.data');
        Route::get('/flows/create/{id}', [FlowController::class, 'create'])->name('flows.create');
        Route::post('/flows', [FlowController::class, 'store'])->name('flows.store');
        Route::get('/flows/edit/{id}', [FlowController::class, 'edit'])->name('flows.edit');
        Route::put('/flows/{id}', [FlowController::class, 'update'])->name('flows.update');
        Route::delete('/flows/{id}', [FlowController::class, 'destroy'])->name('flows.destroy');
        
        // Assignment
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments-data', [AssignmentController::class, 'data'])->name('assignments.data');
        Route::get('/assignments/create/{id}', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::get('/assignments/edit/{id}', [AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::put('/assignments/{id}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    });
    
    Route::middleware(['permission:viewimport', 'role:superadmin'])->group(function () {
        Route::get('/import-rating', [AdminImportController::class, 'index'])->name('importRating');
        Route::post('/import-rating/store', [AdminImportController::class, 'storeRating'])->name('importRating.store');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    });

});


Route::fallback(function () {
    return view('errors.404');
});

require __DIR__.'/auth.php';
