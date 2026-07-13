<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Transaction;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsTransactionIndex
{
    use HandlesIndexSorting;

    /**
     * @param  (Closure(Builder): void)|null  $scope
     * @return array{transactions: LengthAwarePaginator, status: string, search: string, sort: string, direction: string}
     */
    protected function transactionIndex(Request $request, ?Closure $scope = null): array
    {
        $status = (string) $request->query('payment_status');
        $search = trim((string) $request->query('q'));
        $sort = $this->indexSort($request, Transaction::INDEX_SORTS, 'created');
        $direction = $this->indexDirection($request, 'desc');
        $query = Transaction::query()->forFilteredIndex($status, $search);

        if ($scope) {
            $scope($query);
        }

        return [
            'transactions' => $query
                ->orderBy(Transaction::INDEX_SORTS[$sort], $direction)
                ->orderByDesc('transactions.created_at')
                ->paginate(12)
                ->withQueryString(),
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }
}
