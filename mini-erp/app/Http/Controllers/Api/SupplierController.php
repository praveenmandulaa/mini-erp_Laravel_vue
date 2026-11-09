<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Supplier::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
