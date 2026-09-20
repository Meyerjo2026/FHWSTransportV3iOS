<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\JourneyController;
use App\Http\Controllers\Admin\MapController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect('/login');
    }
    if (Auth::user()->must_change_password) {
        return redirect()->route('password.force');
    }

    return match (Auth::user()->role) {
        'admin' => redirect('/admin'),
        'staff' => redirect('/staff'),
        default => redirect('/student'),
    };
});

Route::middleware('auth')->get('/dashboard', function () {
    return match (Auth::user()->role) {
        'admin' => redirect('/admin'),
        'staff' => redirect('/staff'),
        default => redirect('/student'),
    };
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/force-password', [ForcePasswordController::class, 'show'])->name('password.force');
    Route::post('/force-password', [ForcePasswordController::class, 'update'])->name('password.force.update');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Student
Route::middleware(['auth', 'password.changed', 'role:student'])->prefix('student')->group(function () {
    Route::get('/', [StudentController::class, 'create']);
    Route::post('/', [StudentController::class, 'store']);
    Route::get('/mine', [StudentController::class, 'mine']);
});

// Staff
Route::middleware(['auth', 'password.changed', 'role:staff'])->prefix('staff')->group(function () {
    Route::get('/', [StaffController::class, 'approve']);
    Route::get('/bulk', [StaffController::class, 'bulkForm']);
    Route::post('/bulk', [StaffController::class, 'bulkUpload']);
    Route::get('/students', [StaffController::class, 'bulkStudentsForm']);
    Route::post('/students', [StaffController::class, 'bulkStudentsUpload']);
});

// Shared approve/reject action (staff + admin)
Route::middleware(['auth', 'password.changed', 'role:staff,admin'])
    ->post('/requests/{tripRequest}/status', [StaffController::class, 'setStatus']);

// Admin
Route::middleware(['auth', 'password.changed', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'consolidate']);
    Route::get('/review', [AdminController::class, 'review']);
    Route::post('/review/bulk', [AdminController::class, 'bulkStatus']);
    Route::get('/finalise', [AdminController::class, 'finaliseForm']);
    Route::post('/finalise', [AdminController::class, 'finalise']);
    Route::get('/quotes', [AdminController::class, 'quotesIndex']);
    Route::post('/quotes', [AdminController::class, 'generateQuote']);
    Route::get('/quotes/{quote}', [AdminController::class, 'quoteShow']);
Route::get('/group-assignments', [AdminController::class, 'groupAssignments']);
        Route::post('/group-assignments', [AdminController::class, 'storeStaff']);
        Route::post('/staff/{staff}/reset-password', [AdminController::class, 'resetStaffPassword']);
        Route::post('/staff/{staff}/toggle', [AdminController::class, 'toggleStaff']);
        Route::delete('/staff/{staff}', [AdminController::class, 'destroyStaff']);
    Route::post('/group-assignments/{type}/{value}', [AdminController::class, 'updateGroupAssignment'])->where('value', '.*');
    Route::get('/sites', [AdminController::class, 'sites']);
    Route::post('/sites', [AdminController::class, 'storeSite']);
    Route::post('/sites/bulk-upload', [AdminController::class, 'bulkUploadSites']);
    Route::post('/sites/{site}/toggle', [AdminController::class, 'toggleSite']);
    Route::post('/sites/{site}', [AdminController::class, 'updateSite']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/bulk-trips', [StaffController::class, 'adminBulkForm']);
    Route::post('/bulk-trips', [StaffController::class, 'bulkUpload']);
    Route::get('/export', [DashboardController::class, 'export']);
    Route::get('/map', [MapController::class, 'index']);
    Route::get('/journeys', [JourneyController::class, 'index']);
    Route::post('/journeys', [JourneyController::class, 'store']);
    Route::delete('/journeys/{journey}', [JourneyController::class, 'destroy']);
});

require __DIR__.'/auth.php';
