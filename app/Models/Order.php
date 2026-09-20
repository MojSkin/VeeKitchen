<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'restaurant_table_id',
        'customer_id',
        'guest_token',
        'guest_name',
        'discount_id',
        'order_number',
        'status',
        'subtotal',
        'discount_total',
        'total',
        'notes',
        'placed_at',
        'paid_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * The discount applied at placement time (null = no discount).
     *
     * @return BelongsTo<Discount, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * The tracked total as a Money value object.
     */
    public function totalMoney(): Money
    {
        return Money::of($this->total);
    }

    /**
     * Whether the bearer of a guest token may track this order.
     */
    public function isOwnedByGuest(string $guestToken): bool
    {
        return $this->guest_token !== null
            && hash_equals($this->guest_token, $guestToken);
    }
}
