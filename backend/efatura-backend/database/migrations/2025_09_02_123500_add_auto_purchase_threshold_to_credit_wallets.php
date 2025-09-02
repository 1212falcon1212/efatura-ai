<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_wallets', 'auto_purchase_threshold_credits')) {
                $table->integer('auto_purchase_threshold_credits')->nullable()->after('auto_purchase_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            $table->dropColumn(['auto_purchase_threshold_credits']);
        });
    }
};


