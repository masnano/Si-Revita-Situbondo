<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Si Revita Situbondo - Web Routes
| Sistem Revitalisasi dan Pelaporan Keuangan Sekota Situbondo
|--------------------------------------------------------------------------
*/

// Root redirect
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::get('/quick-login/{role}', [AuthController::class, 'quickLogin'])->name('quick-login');

// Protected Routes (Must be Authenticated)
Route::middleware('auth')->group(function () {

    // 1. Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Laporan Revitalisasi Sekolah (BKU, BPK, BB, BP, Realisasi, Kuitansi)
    Route::prefix('laporan')->name('reports.')->group(function () {
        Route::get('/bku', [ReportController::class, 'bku'])->name('bku')->middleware('permission:bku.view');
        Route::get('/bpk', [ReportController::class, 'bpk'])->name('bpk')->middleware('permission:bpk.view');
        Route::get('/bb', [ReportController::class, 'bb'])->name('bb')->middleware('permission:bb.view');
        Route::get('/bp', [ReportController::class, 'bp'])->name('bp')->middleware('permission:bp.view');
        Route::get('/realisasi', [ReportController::class, 'realisasi'])->name('realisasi')->middleware('permission:laporan.view');
        Route::get('/kuitansi', [ReportController::class, 'kuitansi'])->name('kuitansi')->middleware('permission:kuitansi.view');
        Route::get('/kuitansi/{id}/print', [ReportController::class, 'printKuitansi'])->name('kuitansi.print')->middleware('permission:kuitansi.print');
        Route::get('/export/{type}', [ReportController::class, 'exportCsv'])->name('export');
    });

    // 3. Modul Transaksi Keuangan (BKU / Kas / Bank / Pajak)
    Route::resource('transactions', TransactionController::class);
    Route::post('/transactions/{transaction}/setor-pajak', [TransactionController::class, 'setorPajak'])->name('transactions.setor_pajak');

    // 4. Modul Data Master Sekolah & Proyek & RAB
    Route::resource('schools', SchoolController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('rab', BudgetItemController::class)->only(['index', 'store', 'update', 'destroy']);

    // 5. Modul Khusus Role ROOT (Kelola User, Role, & Permission Granular)
    Route::middleware('role:root')->group(function () {
        Route::resource('users', UserController::class);

        // Role & Permission Matrix Management
        Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::get('/roles/{role}', [RolePermissionController::class, 'show'])->name('roles.show');
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles.update');
        Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'updatePermissions'])->name('roles.update_permissions');
        Route::post('/roles/permissions/store', [RolePermissionController::class, 'storePermission'])->name('roles.store_permission');
    });
});

