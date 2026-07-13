<?php

namespace App\Services;

use Illuminate\Database\QueryException;

final class UniqueConstraintViolation
{
    public static function forColumn(QueryException $exception, string $column): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());
        $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');

        return $isUniqueViolation && str_contains($message, strtolower($column));
    }
}
