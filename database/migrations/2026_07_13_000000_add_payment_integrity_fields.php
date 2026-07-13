<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('quoted_amount', 10, 2)->nullable()->after('service_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Nullable until the explicit repair command backfills legacy rows.
            $table->decimal('amount_paid', 10, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('quoted_amount');
        });
    }
};
