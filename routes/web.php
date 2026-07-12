<?php

use App\Http\Controllers\Admin\AppointmentCalendarController as AdminAppointmentCalendarController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\PromotionRuleController as AdminPromotionRuleController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RfmSegmentController as AdminRfmSegmentController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StaffScheduleExceptionController as AdminStaffScheduleExceptionController;
use App\Http\Controllers\Admin\StaffWeeklyScheduleController as AdminStaffWeeklyScheduleController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\Auth\GoogleDeletionController;
use App\Http\Controllers\Auth\GooglePasswordSetupController;
use App\Http\Controllers\Customer\AppointmentCalendarController as CustomerAppointmentCalendarController;
use App\Http\Controllers\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Customer\FeedbackController as CustomerFeedbackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\AppointmentCalendarController as StaffAppointmentCalendarController;
use App\Http\Controllers\Staff\AppointmentController as StaffAppointmentController;
use App\Http\Controllers\Staff\CustomerController as StaffCustomerController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\FeedbackController as StaffFeedbackController;
use App\Http\Controllers\Staff\TransactionController as StaffTransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRouteName());
})->middleware(['auth', 'active', 'verified'])->name('dashboard');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/delete/google', [GoogleDeletionController::class, 'redirect'])->name('profile.deletion.google');
    Route::get('/profile/delete/google/callback', [GoogleDeletionController::class, 'callback'])->name('profile.deletion.google.callback');
    Route::get('/profile/password/google', [GooglePasswordSetupController::class, 'redirect'])->name('profile.password.google');
    Route::get('/profile/password/google/callback', [GooglePasswordSetupController::class, 'callback'])->name('profile.password.google.callback');
});

Route::middleware(['auth', 'active', 'verified', 'role:super_admin,admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/appointments/calendar', AdminAppointmentCalendarController::class)->name('appointments.calendar');
        Route::post('/appointments/calendar', [AdminAppointmentController::class, 'storeFromCalendar'])->name('appointments.calendar.store');
        Route::get('/appointments/available-therapists', [AdminAppointmentController::class, 'availableTherapists'])->name('appointments.available-therapists');
        Route::post('/appointments/{appointment}/complete', [AdminAppointmentController::class, 'complete'])->name('appointments.complete');
        Route::patch('/appointments/{appointment}/outcome', [AdminAppointmentController::class, 'outcome'])->name('appointments.outcome');
        Route::resource('appointments', AdminAppointmentController::class)->except('destroy');
        Route::resource('customers', AdminCustomerController::class)->only(['index', 'show', 'update']);
        Route::get('/staff/{staff}/weekly-schedules/create', [AdminStaffWeeklyScheduleController::class, 'create'])->name('staff.weekly-schedules.create');
        Route::post('/staff/{staff}/weekly-schedules', [AdminStaffWeeklyScheduleController::class, 'store'])->name('staff.weekly-schedules.store');
        Route::get('/staff/{staff}/weekly-schedules/{weeklySchedule}/edit', [AdminStaffWeeklyScheduleController::class, 'edit'])->name('staff.weekly-schedules.edit');
        Route::patch('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'update'])->name('staff.weekly-schedules.update');
        Route::delete('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'destroy'])->name('staff.weekly-schedules.destroy');
        Route::get('/staff/{staff}/schedule-exceptions/create', [AdminStaffScheduleExceptionController::class, 'create'])->name('staff.schedule-exceptions.create');
        Route::post('/staff/{staff}/schedule-exceptions', [AdminStaffScheduleExceptionController::class, 'store'])->name('staff.schedule-exceptions.store');
        Route::get('/staff/{staff}/schedule-exceptions/{scheduleException}/edit', [AdminStaffScheduleExceptionController::class, 'edit'])->name('staff.schedule-exceptions.edit');
        Route::patch('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'update'])->name('staff.schedule-exceptions.update');
        Route::delete('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'destroy'])->name('staff.schedule-exceptions.destroy');
        Route::resource('staff', AdminStaffController::class)->except('destroy');
        Route::patch('/services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');
        Route::resource('services', AdminServiceController::class)->except('destroy');
        Route::resource('transactions', AdminTransactionController::class)->except('destroy');
        Route::patch('/rfm-segments/{rfmSegment}/toggle', [AdminRfmSegmentController::class, 'toggle'])->name('rfm-segments.toggle');
        Route::resource('rfm-segments', AdminRfmSegmentController::class)
            ->parameters(['rfm-segments' => 'rfmSegment'])
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('/promotion-rules/{promotionRule}/toggle', [AdminPromotionRuleController::class, 'toggle'])->name('promotion-rules.toggle');
        Route::resource('promotion-rules', AdminPromotionRuleController::class)
            ->parameters(['promotion-rules' => 'promotionRule'])
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('/promotions/generate', [AdminPromotionController::class, 'generate'])->name('promotions.generate');
        Route::resource('promotions', AdminPromotionController::class)->only(['index', 'show', 'update']);
        Route::resource('feedback', AdminFeedbackController::class)->only(['index', 'show']);
        Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::view('/settings', 'modules.placeholder', [
            'eyebrow' => 'Admin module',
            'title' => 'Settings',
            'description' => 'Maintain business details, user status, and practical system defaults.',
            'status' => 'Phase 11',
            'actionLabel' => 'Back to dashboard',
            'actionRoute' => 'admin.dashboard',
            'cards' => [
                ['label' => 'Business', 'value' => 'Contact details'],
                ['label' => 'Users', 'value' => 'Account status'],
                ['label' => 'Defaults', 'value' => 'Operating values'],
            ],
        ])->name('settings.index');
    });

Route::middleware(['auth', 'active', 'verified', 'super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', AdminUserManagementController::class)->only(['index', 'store', 'update']);
    });

Route::middleware(['auth', 'active', 'verified', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
        Route::get('/appointments/calendar', StaffAppointmentCalendarController::class)->name('appointments.calendar');
        Route::resource('appointments', StaffAppointmentController::class)->only(['index', 'show']);
        Route::resource('customers', StaffCustomerController::class)->only(['index', 'show']);
        Route::resource('transactions', StaffTransactionController::class)->only(['index', 'show']);
        Route::resource('feedback', StaffFeedbackController::class)->only(['index', 'show']);
    });

Route::middleware(['auth', 'active', 'verified', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/appointments/availability', [CustomerAppointmentController::class, 'availability'])->name('appointments.availability');
        Route::get('/appointments/calendar', CustomerAppointmentCalendarController::class)->name('appointments.calendar');
        Route::patch('/appointments/{appointment}/cancel', [CustomerAppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::resource('appointments', CustomerAppointmentController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('feedback', CustomerFeedbackController::class)->only(['index', 'create', 'store']);
        Route::redirect('/profile', '/profile')->name('profile.edit');
    });

require __DIR__.'/auth.php';
