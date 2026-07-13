<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pendingAppointments = DB::table('appointments')->where('status', 'pending')->count();
        $missingQuotes = DB::table('appointments')->whereNull('quoted_amount')->count();
        $missingPaidAmounts = DB::table('transactions')->whereNull('amount_paid')->count();
        $invalidPaidAmounts = DB::table('transactions')
            ->where(fn ($query) => $query
                ->where('amount_paid', '<', 0)
                ->orWhereColumn('amount_paid', '>', 'amount'))
            ->count();
        $invalidPaymentStatuses = DB::table('transactions')
            ->where(function ($query): void {
                $query
                    ->where(fn ($query) => $query
                        ->where('payment_status', 'unpaid')
                        ->where('amount_paid', '!=', 0))
                    ->orWhere(fn ($query) => $query
                        ->where('payment_status', 'partial')
                        ->where(fn ($query) => $query
                            ->where('amount_paid', '<=', 0)
                            ->orWhereColumn('amount_paid', '>=', 'amount')))
                    ->orWhere(fn ($query) => $query
                        ->where('payment_status', 'paid')
                        ->whereColumn('amount_paid', '!=', 'amount'))
                    ->orWhere(fn ($query) => $query
                        ->whereIn('payment_status', ['refunded', 'void'])
                        ->where('amount_paid', '!=', 0))
                    ->orWhereNotIn('payment_status', ['unpaid', 'partial', 'paid', 'refunded', 'void']);
            })
            ->count();
        $duplicateAppointmentLinks = DB::query()
            ->fromSub(
                DB::table('transactions')
                    ->select('appointment_id')
                    ->whereNotNull('appointment_id')
                    ->groupBy('appointment_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_transaction_links',
            )
            ->count();

        if ($pendingAppointments > 0
            || $missingQuotes > 0
            || $missingPaidAmounts > 0
            || $invalidPaidAmounts > 0
            || $invalidPaymentStatuses > 0
            || $duplicateAppointmentLinks > 0) {
            throw new RuntimeException(sprintf(
                'Payment integrity finalization stopped: pending=%d, missing_quotes=%d, missing_amount_paid=%d, invalid_amount_paid=%d, invalid_payment_statuses=%d, duplicate_links=%d. Run `php artisan casa:repair-workflow-redundancy` first, review its dry-run output, then run it with `--apply --actor=<admin-user-id>` after explicit database approval.',
                $pendingAppointments,
                $missingQuotes,
                $missingPaidAmounts,
                $invalidPaidAmounts,
                $invalidPaymentStatuses,
                $duplicateAppointmentLinks,
            ));
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('quoted_amount', 10, 2)->nullable(false)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0)->nullable(false)->change();
            $table->unique('appointment_id', 'transactions_appointment_unique');
        });

        // Add the replacement first so the appointment foreign key is indexed
        // throughout the change, then remove the redundant non-unique index.
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_appointment_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('appointment_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_appointment_unique');
            $table->decimal('amount_paid', 10, 2)->nullable()->default(null)->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('quoted_amount', 10, 2)->nullable()->change();
        });
    }
};
