<?php

namespace App\Models;

use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $principal_amount
 * @property Carbon $disbursement_date
 * @property string $installment_amount
 * @property int $installments_count
 * @property array $payment_dates
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $rate_factor
 * @property-read string $total_to_pay
 * @property-read int $paid_installments
 * @property-read string $remaining
 * @property-read bool $is_active
 */
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'principal_amount',
        'disbursement_date',
        'installment_amount',
        'installments_count',
        'payment_dates',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'disbursement_date' => 'date',
            'installment_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'payment_dates' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    // ─── Accessors ────────────────────────────────────────────────

    /**
     * Derived rate factor: (installment × count) / principal.
     * Returns 0 when principal is zero or missing.
     */
    public function getRateFactorAttribute(): float
    {
        if (! (float) $this->principal_amount) {
            return 0.0;
        }

        return ($this->installment_amount * $this->installments_count) / $this->principal_amount;
    }

    /**
     * Total amount to pay: installment × number of installments.
     */
    public function getTotalToPayAttribute(): string
    {
        return number_format($this->installment_amount * $this->installments_count, 2, '.', '');
    }

    /**
     * Count of non-projected (real) movements linked to this debt.
     */
    public function getPaidInstallmentsAttribute(): int
    {
        return $this->movements()->where('is_projected', false)->count();
    }

    /**
     * Remaining balance: total to pay minus sum of real movement amounts.
     * Never negative.
     */
    public function getRemainingAttribute(): string
    {
        $paid = (float) $this->movements()
            ->where('is_projected', false)
            ->sum('amount');

        $remaining = max(0, (float) $this->total_to_pay - abs($paid));

        return number_format($remaining, 2, '.', '');
    }

    /**
     * Whether the debt is still active (not closed).
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->closed_at === null;
    }
}
