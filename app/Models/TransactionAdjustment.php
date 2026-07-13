<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class TransactionAdjustment extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    public const ACTION_PAYMENT = 'payment';

    public const ACTION_CORRECTION = 'correction';

    public const ACTION_REFUND = 'refund';

    public const ACTION_VOID = 'void';

    public const ACTION_REPAIR = 'repair';

    protected $fillable = [
        'transaction_id',
        'action',
        'previous_amount',
        'new_amount',
        'previous_amount_paid',
        'new_amount_paid',
        'payment_delta',
        'payment_method',
        'occurred_at',
        'recorded_by',
        'reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'previous_amount_paid' => 'decimal:2',
            'new_amount_paid' => 'decimal:2',
            'payment_delta' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Transaction adjustments are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Transaction adjustments are append-only.');
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
