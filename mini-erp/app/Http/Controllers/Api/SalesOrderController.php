<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SalesOrder::with('items.product')
                ->latest()
                ->paginate(10)
        );
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {
            $order = SalesOrder::create([
                'order_number' => 'SO-' . Str::upper(Str::random(12)),
                'customer_name' => $data['customer_name'],
                'order_date' => $data['order_date'],
                'status' => 'draft',
                'total_amount' => 0,
            ]);

            $totalPaise = 0;

            foreach ($data['items'] as $item) {
                $quantity = (int) $item['quantity'];

                $price = number_format(
                    (float) $item['unit_price'],
                    2,
                    '.',
                    ''
                );

                [$rupees, $paise] = explode('.', $price);

                $unitPaise = ((int) $rupees * 100) + (int) $paise;

                $subtotalPaise = $unitPaise * $quantity;
                $totalPaise += $subtotalPaise;

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'subtotal' => number_format(
                        $subtotalPaise / 100,
                        2,
                        '.',
                        ''
                    ),
                ]);
            }

            $order->update([
                'total_amount' => number_format(
                    $totalPaise / 100,
                    2,
                    '.',
                    ''
                ),
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Sales order created as draft',
            'data' => $order->load('items.product'),
        ], 201);
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => $salesOrder->load('items.product'),
        ]);
    }

    public function confirm(
        SalesOrder $salesOrder,
        NotificationService $notificationService
    ): JsonResponse {
        $changedProductIds = DB::transaction(
            function () use ($salesOrder) {
                $order = SalesOrder::whereKey($salesOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'status' => [
                            'This sales order has already been confirmed.',
                        ],
                    ]);
                }

                $items = $order->items()
                    ->orderBy('product_id')
                    ->get();

                $productIds = [];

                foreach ($items as $item) {
                    $product = Product::whereKey($item->product_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $before = (int) $product->stock_quantity;
                    $quantity = (int) $item->quantity;

                    if ($before < $quantity) {
                        throw ValidationException::withMessages([
                            'stock' => [
                                "Insufficient stock for {$product->name}. "
                                . "Available: {$before}, "
                                . "requested: {$quantity}.",
                            ],
                        ]);
                    }

                    $after = $before - $quantity;

                    $product->update([
                        'stock_quantity' => $after,
                    ]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'OUT',
                        'quantity' => $quantity,
                        'reference_type' => 'sales_order',
                        'reference_id' => $order->id,
                        'stock_before' => $before,
                        'stock_after' => $after,
                    ]);

                    $productIds[] = $product->id;
                }

                $order->update([
                    'status' => 'confirmed',
                ]);

                return array_values(array_unique($productIds));
            },
            3
        );

        // Database transaction has committed.
        // Synchronize current stock with Node.js.
        foreach ($changedProductIds as $productId) {
            $product = Product::find($productId);

            if ($product) {
                $notificationService->checkLowStock($product);
            }
        }

        return response()->json([
            'message' => 'Sales order confirmed successfully',
            'data' => $salesOrder->fresh()->load('items.product'),
        ]);
    }
}
