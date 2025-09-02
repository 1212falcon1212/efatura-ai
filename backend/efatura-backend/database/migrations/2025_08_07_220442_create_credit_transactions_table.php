<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_wallet_id')->constrained('credit_wallets')->cascadeOnDelete();
            $table->enum('type', ['top_up','debit','refund','adjustment']);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('TRY');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['credit_wallet_id','type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
