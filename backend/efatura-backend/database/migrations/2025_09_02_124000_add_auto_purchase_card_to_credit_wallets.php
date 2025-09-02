<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_wallets', 'auto_purchase_card_token')) {
                $table->string('auto_purchase_card_token', 191)->nullable()->after('auto_purchase_threshold_credits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            $table->dropColumn(['auto_purchase_card_token']);
        });
    }
};


