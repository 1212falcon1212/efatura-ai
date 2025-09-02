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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->default('Adet');
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3)->default('TRY');
            $table->decimal('total', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
