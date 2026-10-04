<?php

use App\Http\Controllers\ReviewTerminalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReviewTerminalController::class, 'index'])->name('terminal.index');
Route::get('/terminal/{id}', [ReviewTerminalController::class, 'show'])->name('terminal.show');
Route::get('/reviews/{id}', [ReviewTerminalController::class, 'show']);
Route::get('/reviews/{id}/download-patch', [ReviewTerminalController::class, 'downloadPatch'])->name('reviews.download-patch');
