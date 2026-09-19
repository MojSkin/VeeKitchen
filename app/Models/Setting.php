<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Read a setting: branch-specific first, then the global one.
     */
    public static function valueFor(?int $branchId, string $key, ?string $default = null): ?string
    {
        $query = static::query()->where('key', $key);

        $branchValue = (clone $query)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->value('value');

        if ($branchValue !== null) {
            return $branchValue;
        }

        return $query->whereNull('branch_id')->value('value') ?? $default;
    }

    /**
     * Write a setting (upsert per branch scope).
     */
    public static function put(?int $branchId, string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            ['value' => $value],
        );
    }
}
