<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(
                min(max((int) $request->input('per_page', 10), 1), 100)
            );

        return response()->json($products);
    }

    // POST /api/products
    public function store(
        StoreProductRequest $request,
        NotificationService $notificationService
    ): JsonResponse {
        $data = $request->validated();

        // New products always start with zero stock.
        // Inventory increases through purchase receiving.
        $data['stock_quantity'] = 0;

        $product = Product::create($data);

        $notificationService->checkLowStock($product);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    // GET /api/products/{product}
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product,
        ]);
    }

    // PUT/PATCH /api/products/{product}
    public function update(
        UpdateProductRequest $request,
        Product $product,
        NotificationService $notificationService
    ): JsonResponse {
        // Only validated product details are updated.
        // stock_quantity is prohibited by the request validator.
        $product->update($request->validated());

        $product->refresh();

        $notificationService->checkLowStock($product);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    // DELETE /api/products/{product}
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
