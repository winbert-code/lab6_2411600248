<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'sku',
        'description',
        'quantity',
        'reorder_level',
        'unit_price',
        'supplier',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reorder_level' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function latestTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('quantity', '<=', 0);
    }

    public function scopeInCategory(Builder $query, int|string $category): Builder
    {
        return is_numeric($category)
            ? $query->where('category_id', (int) $category)
            : $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isLowStock(): bool
    {
        return (int) $this->quantity <= (int) $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return (int) $this->quantity <= 0;
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => self::STATUS_INACTIVE]);
    }

    public function activate(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function getStockStatusAttribute(): string
    {
        return $this->stockStatus();
    }

    public function getStockStatusColorAttribute(): string
    {
        return match (true) {
            $this->isOutOfStock() => 'danger',
            $this->isLowStock() => 'warning',
            default => 'success',
        };
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->isOutOfStock() => 'Out of Stock',
            $this->isLowStock() => 'Low Stock',
            default => 'In Stock',
        };
    }

    public function availabilityStatus(): string
    {
        return $this->isInactive() ? 'Inactive' : $this->stockStatus();
    }

    public function stockValue(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
