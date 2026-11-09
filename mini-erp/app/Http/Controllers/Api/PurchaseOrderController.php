<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PurchaseOrder::with(['supplier', 'items.product'])
                ->latest()
                ->paginate(10)
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'order_number' => 'PO-' . Str::upper(Str::random(12)),
                'order_date' => $data['order_date'],
                'status' => 'draft',
                'total_amount' => 0,
            ]);

            $totalPaise = 0;

            foreach ($data['items'] as $item) {
                $quantity = (int) $item['quantity'];

                $cost = number_format(
                    (float) $item['unit_cost'],
                    2,
                    '.',
                    ''
                );

                [$rupees, $paise] = explode('.', $cost);

                $unitPaise = ((int) $rupees * 100) + (int) $paise;

                $subtotalPaise = $unitPaise * $quantity;
                $totalPaise += $subtotalPaise;

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $cost,
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
            'message' => 'Purchase order created as draft',
            'data' => $order->load([
                'supplier',
                'items.product',
            ]),
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'data' => $purchaseOrder->load([
                'supplier',
                'items.product',
            ]),
        ]);
    }

    public function receive(
        PurchaseOrder $purchaseOrder,
        NotificationService $notificationService
    ): JsonResponse {
        $receivedProductIds = DB::transaction(
            function () use ($purchaseOrder) {
                $order = PurchaseOrder::whereKey($purchaseOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'status' => [
                            'This purchase order has already been received.',
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

                    $after = $before + $quantity;

                    if ($after > 4294967295) {
                        throw ValidationException::withMessages([
                            'quantity' => [
                                'Stock quantity exceeds the supported limit.',
                            ],
                        ]);
                    }

                    $product->update([
                        'stock_quantity' => $after,
                    ]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'IN',
                        'quantity' => $quantity,
                        'reference_type' => 'purchase_order',
                        'reference_id' => $order->id,
                        'stock_before' => $before,
                        'stock_after' => $after,
                    ]);

                    $productIds[] = $product->id;
                }

                $order->update([
                    'status' => 'received',
                ]);

                return array_values(array_unique($productIds));
            },
            3
        );

        // The transaction has committed.
        // Synchronize the latest stock with Node.js.
        foreach ($receivedProductIds as $productId) {
            $product = Product::find($productId);

            if ($product) {
                $notificationService->checkLowStock($product);
            }
        }

        return response()->json([
            'message' => 'Purchase order received successfully',
            'data' => $purchaseOrder->fresh()->load([
                'supplier',
                'items.product',
            ]),
        ]);
    }
}
