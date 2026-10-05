<?php

use App\Http\Controllers\AgentEngineController;
use App\Http\Controllers\ReviewTerminalController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook Handlers
Route::post('/webhooks/github', [WebhookController::class, 'handleGitHub']);
Route::post('/webhooks/gitlab', [WebhookController::class, 'handleGitLab']);
Route::post('/webhooks/simulate', [WebhookController::class, 'simulate']);

// Agent Engine Status & Seeder
Route::get('/engine/status', [AgentEngineController::class, 'status']);
Route::post('/engine/seed-samples', [AgentEngineController::class, 'seedSamplePRs']);

// Review Terminal API
Route::get('/pull-requests/{id}/details', [ReviewTerminalController::class, 'apiData']);
Route::post('/pull-requests/{id}/audit', [ReviewTerminalController::class, 'triggerAudit']);
Route::post('/inline-comments/{id}/publish', [ReviewTerminalController::class, 'publishComment']);
Route::post('/review-sessions/{id}/publish-all', [ReviewTerminalController::class, 'publishAllComments']);
Route::post('/patch-diffs/{id}/apply', [ReviewTerminalController::class, 'applyPatch']);
Route::get('/review-sessions/{id}/download-patch', [ReviewTerminalController::class, 'downloadPatch']);
Route::get('/review-sessions/{id}/download-sarif', [ReviewTerminalController::class, 'downloadSarif']);
Route::post('/pull-requests/{id}/check-runs/sync', [ReviewTerminalController::class, 'syncCheckRun']);
Route::post('/pull-requests/{id}/create-autofix-branch', [ReviewTerminalController::class, 'createAutoFixBranch']);
Route::post('/findings/{id}/discuss', [ReviewTerminalController::class, 'postInlineDiscussion']);
