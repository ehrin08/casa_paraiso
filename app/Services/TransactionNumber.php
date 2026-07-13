<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\QueryException;

class TransactionNumber
{
    public const MAX_SAVE_ATTEMPTS = 3;

    public function next(): string
    {
        $prefix = 'TRX-'.now()->format('Ymd').'-';
        $sequence = 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Transaction::query()->where('transaction_number', $number)->exists());

        return $number;
    }

    /**
     * Persist a new transaction while retrying only transaction-number collisions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Transaction
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_SAVE_ATTEMPTS; $attempt++) {
            try {
                return Transaction::query()->create([
                    ...$attributes,
                    'transaction_number' => $this->next(),
                ]);
            } catch (QueryException $exception) {
                if (! UniqueConstraintViolation::forColumn($exception, 'transaction_number')) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw $lastException;
    }
}
