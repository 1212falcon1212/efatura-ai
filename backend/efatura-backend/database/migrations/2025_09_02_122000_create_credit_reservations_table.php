<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('credit_reservations')) {
            Schema::create('credit_reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('buyer_organization_id');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->integer('credits');
                $table->string('status', 16)->default('reserved');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['buyer_organization_id']);
                $table->index(['status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reservations');
    }
};


