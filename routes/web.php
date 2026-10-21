<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HpsConversionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\RpdBreakdownController;
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
        Route::post('/bp/auto-setor', [ReportController::class, 'autoSetorPajak'])->name('bp.auto_setor')->middleware('role:root,admin');
        Route::get('/ai-audit', [ReportController::class, 'aiAudit'])->name('ai_audit')->middleware('permission:laporan.view');
        Route::get('/realisasi', [ReportController::class, 'realisasi'])->name('realisasi')->middleware('permission:laporan.view');
        Route::get('/kuitansi', [ReportController::class, 'kuitansi'])->name('kuitansi')->middleware('permission:kuitansi.view');
        Route::get('/kuitansi/{id}/print', [ReportController::class, 'printKuitansi'])->name('kuitansi.print')->middleware('permission:kuitansi.print');
        Route::get('/export/{type}', [ReportController::class, 'exportCsv'])->name('export');
    });

    // 3. Modul Transaksi Keuangan (BKU / Kas / Bank / Pajak)
    Route::post('/transactions/ai/generate-description', [TransactionController::class, 'generateAiDescription'])->name('transactions.ai.generate_description');
    Route::get('/transactions/ai/generate-ntpn', [TransactionController::class, 'generateAiNtpn'])->name('transactions.ai.generate_ntpn');
    Route::resource('transactions', TransactionController::class);
    Route::post('/transactions/{transaction}/setor-pajak', [TransactionController::class, 'setorPajak'])->name('transactions.setor_pajak');

    // 4. Modul Data Master Sekolah & Proyek & RAB
    Route::resource('schools', SchoolController::class);
    Route::resource('projects', ProjectController::class);
    Route::get('/rab/template', [BudgetItemController::class, 'template'])->name('rab.template');
    Route::post('/rab/import', [BudgetItemController::class, 'import'])->name('rab.import')->middleware('permission:rab.create');
    Route::resource('rab', BudgetItemController::class)->parameters(['rab' => 'budgetItem'])->only(['index', 'store', 'update', 'destroy']);

    // 5. Modul Pecah Bahan RPD (Upload RPD & Generate Isian BKU)
    Route::prefix('rpd')->name('rpd.')->group(function () {
        Route::get('/', [RpdBreakdownController::class, 'index'])->name('index');
        Route::get('/create', [RpdBreakdownController::class, 'create'])->name('create')->middleware('role:root,admin');
        Route::post('/', [RpdBreakdownController::class, 'store'])->name('store')->middleware('role:root,admin');
        Route::get('/template', [RpdBreakdownController::class, 'template'])->name('template');
        Route::get('/{rpd}', [RpdBreakdownController::class, 'show'])->name('show');
        Route::put('/items/{item}', [RpdBreakdownController::class, 'updateItem'])->name('items.update')->middleware('role:root,admin');
        Route::post('/{rpd}/post-bku', [RpdBreakdownController::class, 'postToBku'])->name('post_bku')->middleware('role:root,admin');
        Route::post('/{rpd}/unpost', [RpdBreakdownController::class, 'unpost'])->name('unpost')->middleware('role:root,admin');
        Route::post('/{rpd}/ai-enhance', [RpdBreakdownController::class, 'enhanceWithAi'])->name('ai_enhance')->middleware('role:root,admin');
        Route::post('/{rpd}/sync-disbursement', [RpdBreakdownController::class, 'syncDisbursement'])->name('sync_disbursement')->middleware('role:root,admin');
        Route::get('/{rpd}/export-bku', [RpdBreakdownController::class, 'exportBku'])->name('export_bku');
        Route::post('/suggest-price', [RpdBreakdownController::class, 'suggestSitubondoPrice'])->name('ai_suggest_price');
        Route::delete('/{rpd}', [RpdBreakdownController::class, 'destroy'])->name('destroy')->middleware('role:root,admin');
    });

    // 5. Modul Konversi Data HPS ke Template Keluaran (DPP & Pajak)
    Route::prefix('hps-konversi')->name('hps.')->group(function () {
        Route::get('/', [HpsConversionController::class, 'index'])->name('index');
        Route::post('/convert', [HpsConversionController::class, 'convert'])->name('convert');
    });

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

    // 6. Buku Panduan Penggunaan Sistem (PDF Viewer & Download)
    Route::get('/panduan', [GuideController::class, 'index'])->name('panduan.index');
    Route::get('/panduan/download', [GuideController::class, 'download'])->name('panduan.download');
    Route::get('/panduan/preview', [GuideController::class, 'preview'])->name('panduan.preview');

    // 7. Modul Notifikasi Sistem
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread_count');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read_all');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::get('/{notification}/visit', [NotificationController::class, 'visit'])->name('visit');
    });
});

