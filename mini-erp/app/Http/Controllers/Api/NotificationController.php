<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'x-service-token' => config('services.notifications.token'),
            ])
                ->acceptJson()
                ->timeout(3)
                ->get(
                    rtrim(config('services.notifications.url'), '/')
                    . '/notifications'
                );

            if ($response->failed()) {
                Log::warning('Notification service returned an error', [
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'message' => 'Unable to load notifications',
                ], 502);
            }

            return response()->json([
                'data' => $response->json('data', []),
                'total' => $response->json('total', 0),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Notification service unavailable', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Notification service unavailable',
            ], 503);
        }
    }
}
