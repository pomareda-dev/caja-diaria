<?php

namespace App\Models;

use Database\Factories\GoalContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goal_id
 * @property Carbon $date
 * @property string $amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GoalContribution extends Model
{
    /** @use HasFactory<GoalContributionFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
