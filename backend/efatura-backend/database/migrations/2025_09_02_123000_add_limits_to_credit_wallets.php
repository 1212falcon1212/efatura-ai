<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_wallets', 'doc_limit_daily')) {
                $table->integer('doc_limit_daily')->nullable()->after('auto_topup_enabled');
            }
            if (!Schema::hasColumn('credit_wallets', 'doc_limit_monthly')) {
                $table->integer('doc_limit_monthly')->nullable()->after('doc_limit_daily');
            }
            if (!Schema::hasColumn('credit_wallets', 'limit_action')) {
                $table->string('limit_action', 16)->default('block')->after('doc_limit_monthly'); // block | continue
            }
            if (!Schema::hasColumn('credit_wallets', 'auto_purchase_package')) {
                $table->string('auto_purchase_package', 32)->nullable()->after('limit_action'); // e.g., CRD_500
            }
            if (!Schema::hasColumn('credit_wallets', 'auto_purchase_enabled')) {
                $table->boolean('auto_purchase_enabled')->default(false)->after('auto_purchase_package');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_wallets', function (Blueprint $table) {
            $table->dropColumn(['doc_limit_daily','doc_limit_monthly','limit_action','auto_purchase_package','auto_purchase_enabled']);
        });
    }
};


