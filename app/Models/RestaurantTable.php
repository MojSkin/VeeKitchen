<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'label',
        'capacity',
        'qr_token',
        'status',
        'occupied_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'occupied_at' => 'datetime',
            'capacity' => 'integer',
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
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Orders still open on this table (everything short of delivered/cancelled).
     *
     * @return HasMany<Order, $this>
     */
    public function openOrders(): HasMany
    {
        return $this->orders()->whereIn('status', [
            OrderStatus::AwaitingPayment,
            OrderStatus::Queued,
            OrderStatus::Preparing,
            OrderStatus::Ready,
        ]);
    }

    /**
     * Find a table by its QR token.
     *
     * @param  Builder<RestaurantTable>  $query
     */
    public function scopeForQrToken(Builder $query, string $qrToken): ?self
    {
        return $query->where('qr_token', $qrToken)->first();
    }

    /**
     * Generate an unguessable QR token (43+ chars of entropy).
     */
    public static function generateQrToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
