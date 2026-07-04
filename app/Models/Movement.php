<?php

namespace App\Models;

use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property string $description
 * @property int|null $category_id
 * @property string $amount
 * @property string $source
 * @property int|null $recurring_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'description',
        'category_id',
        'amount',
        'source',
        'recurring_id',
        'notes',
        'is_projected',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_projected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_id');
    }

    public function scopeForMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('date', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function scopeActual(Builder $query): void
    {
        $query->where('date', '<=', now()->toDateString());
    }

    public function scopeProjected(Builder $query): void
    {
        $query->where('date', '>', now()->toDateString());
    }

    public static function openingBalance(Carbon $monthStart, int $userId): string
    {
        $sum = static::where('user_id', $userId)
            ->where('date', '<', $monthStart->toDateString())
            ->whereIn('source', ['manual', 'import'])
            ->where('is_projected', false)
            ->sum('amount');

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }

    /**
     * Calculate the real balance up to today for a user.
     * Sums all real (manual, import) movements with date <= today.
     */
    public static function nextSortOrder(int $userId, string $date, bool $isProjected): int
    {
        return (int) static::where('user_id', $userId)
            ->where('date', $date)
            ->where('is_projected', $isProjected)
            ->max('sort_order') + 1;
    }

    public static function realBalance(int $userId): string
    {
        $sum = static::where('user_id', $userId)
            ->where('date', '<=', now()->toDateString())
            ->whereIn('source', ['manual', 'import'])
            ->where('is_projected', false)
            ->sum('amount');

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }
}
