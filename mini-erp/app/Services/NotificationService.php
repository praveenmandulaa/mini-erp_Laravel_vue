<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function checkLowStock(Product $product): void
    {
        $url = rtrim(
            (string) config('services.notifications.url'),
            '/'
        );

        $token = (string) config(
            'services.notifications.token'
        );

        if ($url === '' || $token === '') {
            Log::warning(
                'Notification service is not configured.'
            );

            return;
        }

        try {
            $response = Http::withHeaders([
                'x-service-token' => $token,
            ])
                ->acceptJson()
                ->timeout(5)
                ->post(
                    "{$url}/notifications/low-stock",
                    [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'stock_quantity' =>
                            (int) $product->stock_quantity,
                        'reorder_level' =>
                            (int) $product->reorder_level,
                    ]
                );

            if ($response->failed()) {
                Log::warning(
                    'Notification synchronization failed.',
                    [
                        'product_id' => $product->id,
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]
                );
            }
        } catch (Throwable $exception) {
            // Notifications must not break product
            // creation or inventory transactions.
            Log::error(
                'Notification service unavailable.',
                [
                    'product_id' => $product->id,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }
}
