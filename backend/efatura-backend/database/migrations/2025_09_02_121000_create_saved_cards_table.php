<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('saved_cards')) {
            Schema::create('saved_cards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('provider', 40)->default('moka');
                $table->string('token', 191);
                $table->string('masked_pan', 32)->nullable();
                $table->string('holder_name', 128)->nullable();
                $table->string('exp_month', 2)->nullable();
                $table->string('exp_year', 4)->nullable();
                $table->string('customer_code', 128)->nullable();
                $table->timestamps();
                $table->index(['organization_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_cards');
    }
};


