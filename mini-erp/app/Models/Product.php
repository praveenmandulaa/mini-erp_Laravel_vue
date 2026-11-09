<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'cost_price',
        'stock_quantity',
        'reorder_level',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'reorder_level' => 'integer',
            'status' => 'boolean',
        ];
    }
    public function purchaseItems(): HasMany
{
    return $this->hasMany(PurchaseItem::class);
}

public function stockMovements(): HasMany
{
    return $this->hasMany(StockMovement::class);
}
public function salesItems(): HasMany
{
    return $this->hasMany(SalesItem::class);
}
}