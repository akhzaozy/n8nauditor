<?php

use App\Http\Controllers\Api\AuditIngestController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);
Route::post('/v1/audits/ingest', [AuditIngestController::class, 'ingest']);
