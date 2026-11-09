<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:products,sku',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
           // 'stock_quantity' => 'sometimes|integer|min:0',
           'stock_quantity' => ['prohibited'],
            'reorder_level' => 'sometimes|integer|min:0',
            'status' => 'sometimes|boolean',
        ];
    }
}
