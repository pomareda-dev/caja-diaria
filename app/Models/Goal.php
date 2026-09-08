<?php

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $target_amount
 * @property Carbon|null $target_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $progress_amount
 * @property-read int $percent
 * @property-read string $remaining_amount
 * @property-read bool $is_complete
 */
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'target_amount',
        'target_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    // ─── Accessors ────────────────────────────────────────────────

    /**
     * Total saved so far: sum of all contribution amounts.
     * Uses the `contributions_sum_amount` aggregate when loaded (withSum),
     * otherwise queries the sum lazily.
     */
    public function getProgressAmountAttribute(): string
    {
        $sum = $this->contributions_sum_amount ?? $this->contributions()->sum('amount');

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }

    /**
     * Progress percentage capped at 100. Guaranteed positive target by
     * validation; defensively returns 0 when target is missing or zero.
     */
    public function getPercentAttribute(): int
    {
        if (! (float) $this->target_amount) {
            return 0;
        }

        return (int) min(100, round((float) $this->progress_amount / (float) $this->target_amount * 100));
    }

    /**
     * Amount still needed to reach the target. Never negative.
     */
    public function getRemainingAmountAttribute(): string
    {
        $remaining = max(0, (float) $this->target_amount - (float) $this->progress_amount);

        return number_format($remaining, 2, '.', '');
    }

    /**
     * Whether the goal has been reached (completed_at is set).
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->completed_at !== null;
    }

    // ─── Completion helpers ───────────────────────────────────────

    /**
     * Recompute `completed_at` from the current progress against the target.
     * When the sum of contributions equals or exceeds the target the goal is
     * marked completed (now); when it falls back below, it reopens (null).
     * Only writes when the completion state actually flips, preserving the
     * original completion timestamp when over-contributing.
     */
    public function syncCompletion(): void
    {
        $progress = round((float) $this->progress_amount, 2);
        $target = round((float) $this->target_amount, 2);

        $shouldComplete = $progress >= $target;

        if ($this->is_complete === $shouldComplete) {
            return;
        }

        $this->completed_at = $shouldComplete ? now() : null;
        $this->update(['completed_at' => $this->completed_at]);
    }

    /**
     * Total earmarked in active goals (completed_at null) for a user.
     * Contributions to completed goals no longer reduce the available balance.
     */
    public static function apartadoAmount(int $userId): float
    {
        $sum = GoalContribution::query()
            ->whereHas('goal', function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->whereNull('completed_at');
            })
            ->sum('amount');

        return round((float) ($sum ?? 0), 2);
    }
}
