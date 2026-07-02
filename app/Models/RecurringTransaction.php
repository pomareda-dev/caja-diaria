<?php

namespace App\Models;

use Database\Factories\RecurringTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $category_id
 * @property string $amount
 * @property int $day_of_month
 * @property Carbon $start_month
 * @property Carbon|null $end_month
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecurringTransaction extends Model
{
    /** @use HasFactory<RecurringTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'amount',
        'day_of_month',
        'start_month',
        'end_month',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'start_month' => 'date',
            'end_month' => 'date',
            'active' => 'boolean',
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

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'recurring_id');
    }
}
