<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_products' => Product::count(),

                'active_products' => Product::where('status', true)->count(),

                'total_stock_units' => (int) Product::sum('stock_quantity'),

                'low_stock_products' => Product::whereColumn(
                    'stock_quantity',
                    '<=',
                    'reorder_level'
                )->count(),

                'draft_purchase_orders' => PurchaseOrder::where(
                    'status',
                    'draft'
                )->count(),

                'received_purchase_orders' => PurchaseOrder::where(
                    'status',
                    'received'
                )->count(),

                'draft_sales_orders' => SalesOrder::where(
                    'status',
                    'draft'
                )->count(),

                'confirmed_sales_orders' => SalesOrder::where(
                    'status',
                    'confirmed'
                )->count(),
            ],
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->orderBy('stock_quantity')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json($products);
    }

    public function movements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type' => ['nullable', 'in:IN,OUT'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = StockMovement::with('product:id,name,sku')
            ->when(
                isset($validated['product_id']),
                fn ($query) => $query->where(
                    'product_id',
                    $validated['product_id']
                )
            )
            ->when(
                isset($validated['type']),
                fn ($query) => $query->where(
                    'type',
                    $validated['type']
                )
            )
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json($movements);
    }
}