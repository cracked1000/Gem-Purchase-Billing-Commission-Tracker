<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinalizingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PayinController;
use App\Http\Controllers\GemExportController;
use App\Http\Controllers\GemRegisterController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerDuplicateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('sellers', SellerController::class);

Route::get('sellers-duplicates', [SellerDuplicateController::class, 'index'])->name('sellers.duplicates');
Route::post('sellers-duplicates/merge', [SellerDuplicateController::class, 'merge'])->name('sellers.duplicates.merge');

Route::get('invoices-archive', [InvoiceController::class, 'trash'])->name('invoices.trash');
Route::patch('invoices-archive/{invoice}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore');
Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
Route::post('receipts/open-folder', [InvoiceController::class, 'openReceiptsFolder'])->name('receipts.open-folder');
Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
Route::resource('invoices', InvoiceController::class);

Route::get('finalizing', [FinalizingController::class, 'index'])->name('finalizing.index');
Route::get('finalizing/pdf', [FinalizingController::class, 'pdf'])->name('finalizing.pdf');

Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
Route::get('finance/{seller}', [FinanceController::class, 'show'])->name('finance.show');
Route::post('finance/{seller}/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
Route::delete('finance/payments/{payment}', [FinanceController::class, 'destroyPayment'])->name('finance.payments.destroy');

Route::get('payin', [PayinController::class, 'index'])->name('payin.index');
Route::get('payin/{seller}', [PayinController::class, 'show'])->name('payin.show');

Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
Route::patch('verification/{item}', [VerificationController::class, 'update'])->name('verification.update');

Route::get('gems', [GemRegisterController::class, 'index'])->name('gems.index');
Route::get('gems/pdf', [GemRegisterController::class, 'pdf'])->name('gems.pdf');
Route::get('gems/excel', [GemRegisterController::class, 'excel'])->name('gems.excel');

Route::get('export', [GemExportController::class, 'index'])->name('export.index');
Route::get('export/registry', [GemExportController::class, 'registry'])->name('export.registry');
Route::get('export/pdf', [GemExportController::class, 'pdf'])->name('export.pdf');
Route::get('export/excel', [GemExportController::class, 'excel'])->name('export.excel');
Route::post('export/assign', [GemExportController::class, 'assign'])->name('export.assign');

Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
Route::get('backups/download', [BackupController::class, 'download'])->name('backups.download');
Route::post('backups/snapshot', [BackupController::class, 'snapshot'])->name('backups.snapshot');
Route::post('backups/restore', [BackupController::class, 'restore'])->name('backups.restore');

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
Route::get('reports/daily/pdf', [ReportController::class, 'dailyPdf'])->name('reports.daily.pdf');
Route::get('reports/all/pdf', [ReportController::class, 'allPdf'])->name('reports.all.pdf');
Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
