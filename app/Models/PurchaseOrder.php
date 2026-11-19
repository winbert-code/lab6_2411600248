<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'order_number',
        'supplier',
        'order_date',
        'expected_date',
        'status',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     */
    public function scopeReceived(Builder $query): void
    {
        $query->where('status', self::STATUS_RECEIVED);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', self::STATUS_CANCELLED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function totalAmount(): float
    {
        return (float) $this->items->sum(fn (PurchaseOrderItem $item) => $item->lineTotal());
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_RECEIVED => 'success',
            self::STATUS_CANCELLED => 'secondary',
            default => 'warning',
        };
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
