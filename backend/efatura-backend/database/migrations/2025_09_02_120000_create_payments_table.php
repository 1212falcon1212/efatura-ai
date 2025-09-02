<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('subscription_invoice_id')->nullable();
                $table->string('provider', 40)->default('moka');
                $table->decimal('amount_try', 12, 2);
                $table->string('currency', 8)->default('TRY');
                $table->string('status', 32)->default('initiated'); // initiated, authorized, captured, failed
                $table->string('provider_txn_id', 128)->nullable();
                $table->json('request_json')->nullable();
                $table->json('response_json')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->index(['organization_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};


