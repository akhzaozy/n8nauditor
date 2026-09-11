<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'disconnected: ' . $e->getMessage();
        }

        return response()->json([
            'status'    => 'ok',
            'app'       => config('app.name', 'SentinelAI'),
            'database'  => $dbStatus,
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }
}
