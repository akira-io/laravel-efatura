<?php

declare(strict_types=1);

use App\Http\Controllers\ConvertCurrencyController;
use App\Http\Controllers\IssueDocumentController;
use App\Http\Controllers\PreviewDocumentController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\RenderDfaController;
use App\Http\Controllers\SignDocumentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')->name('dashboard');

Route::view('/document', 'document')->name('document');
Route::post('/document', PreviewDocumentController::class)->name('document.preview');

Route::view('/sign', 'sign')->name('sign');
Route::post('/sign', SignDocumentController::class)->name('sign.run');

Route::view('/dfa', 'dfa')->name('dfa');
Route::post('/dfa', RenderDfaController::class)->name('dfa.render');

Route::view('/currency', 'currency')->name('currency');
Route::post('/currency', ConvertCurrencyController::class)->name('currency.convert');

Route::get('/doctor', ReadinessController::class)->name('doctor');
Route::post('/doctor', ReadinessController::class)->defaults('remote', true)->name('doctor.remote');

Route::view('/issue', 'issue')->name('issue');
Route::post('/issue', IssueDocumentController::class)->name('issue.run');
