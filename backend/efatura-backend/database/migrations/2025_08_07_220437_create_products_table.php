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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->string('unit')->default('Adet');
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('TRY');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id','sku']);
            $table->index(['organization_id','name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
