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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->enum('status', ['queued','processing','sent','failed','canceled'])->default('queued');
            $table->enum('type', ['e_fatura','e_arsiv'])->default('e_fatura');
            $table->date('issue_date')->nullable();
            $table->json('customer');
            $table->json('items');
            $table->json('totals')->nullable();
            $table->string('kolaysoft_ref')->nullable();
            $table->text('xml')->nullable();
            $table->string('ettn')->nullable();
            $table->timestamps();

            $table->index(['organization_id','status']);
            $table->index(['organization_id','external_id']);
            $table->index(['organization_id','ettn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
