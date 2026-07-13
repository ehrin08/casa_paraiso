<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_VOID = 'void';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_UNPAID,
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
        self::PAYMENT_VOID,
    ];

    public const PAYMENT_RECEIVED_STATUSES = [
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
    ];

    public const TERMINAL_PAYMENT_STATUSES = [
        self::PAYMENT_REFUNDED,
        self::PAYMENT_VOID,
    ];

    public const METHOD_CASH = 'Cash';

    public const METHOD_GCASH = 'GCash';

    public const METHOD_BANK_TRANSFER = 'Bank transfer';

    public const METHOD_OTHER = 'Other';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_GCASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_OTHER,
    ];

    public const INDEX_SORTS = [
        'number' => 'transactions.transaction_number',
        'customer' => 'transaction_customers.name',
        'service' => 'transaction_services.name',
        'amount' => 'transactions.amount',
        'status' => 'transactions.payment_status',
        'created' => 'transactions.created_at',
    ];

    protected $fillable = [
        'transaction_number',
        'customer_profile_id',
        'appointment_id',
        'service_id',
        'amount',
        'amount_paid',
        'payment_status',
        'payment_method',
        'paid_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public static function derivedPaymentStatus(string|int|float $amount, string|int|float $amountPaid): string
    {
        $chargeInCents = (int) round((float) $amount * 100);
        $paidInCents = (int) round((float) $amountPaid * 100);

        if ($paidInCents <= 0) {
            return self::PAYMENT_UNPAID;
        }

        return $paidInCents < $chargeInCents
            ? self::PAYMENT_PARTIAL
            : self::PAYMENT_PAID;
    }

    public function getOpenBalanceAttribute(): string
    {
        if (in_array($this->payment_status, self::TERMINAL_PAYMENT_STATUSES, true)) {
            return '0.00';
        }

        return number_format(max(0, (float) $this->amount - (float) ($this->amount_paid ?? 0)), 2, '.', '');
    }

    public function getNetCollectedAttribute(): string
    {
        return number_format(max(0, (float) ($this->amount_paid ?? 0)), 2, '.', '');
    }

    public function scopeForIndex(Builder $query): Builder
    {
        return $query
            ->with(['customerProfile.user', 'service', 'appointment', 'recorder'])
            ->leftJoin('customer_profiles as transaction_customer_profiles', 'transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as transaction_customers', 'transaction_customers.id', '=', 'transaction_customer_profiles.user_id')
            ->leftJoin('services as transaction_services', 'transaction_services.id', '=', 'transactions.service_id')
            ->select('transactions.*');
    }

    public function scopeWithPaymentStatus(Builder $query, string $status): Builder
    {
        return $query->when(
            in_array($status, self::PAYMENT_STATUSES, true),
            fn (Builder $query) => $query->where('transactions.payment_status', $status),
        );
    }

    public function scopeSearchIndex(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
            $query->where('transactions.transaction_number', 'like', "%{$search}%")
                ->orWhere('transaction_customers.name', 'like', "%{$search}%")
                ->orWhere('transaction_services.name', 'like', "%{$search}%");
        }));
    }

    public function scopeForFilteredIndex(Builder $query, string $status, string $search): Builder
    {
        return $query
            ->forIndex()
            ->withPaymentStatus($status)
            ->searchIndex($search);
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function adjustments()
    {
        return $this->hasMany(TransactionAdjustment::class)->oldest('occurred_at')->oldest('id');
    }
}
