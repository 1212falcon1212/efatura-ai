<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_wallets', 'low_balance_threshold')) {
                $table->decimal('low_balance_threshold', 12, 2)->nullable()->after('balance');
            }
            if (!Schema::hasColumn('credit_wallets', 'auto_topup_enabled')) {
                $table->boolean('auto_topup_enabled')->default(false)->after('low_balance_threshold');
            }
            if (!Schema::hasColumn('credit_wallets', 'auto_topup_amount')) {
                $table->decimal('auto_topup_amount', 12, 2)->nullable()->after('auto_topup_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            $table->dropColumn(['low_balance_threshold','auto_topup_enabled','auto_topup_amount']);
        });
    }
};
