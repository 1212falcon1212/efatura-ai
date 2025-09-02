<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('despatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('status')->default('queued');
            $table->string('mode')->default('despatch'); // despatch | receipt
            $table->date('issue_date')->nullable();
            $table->json('sender')->nullable();
            $table->json('receiver')->nullable();
            $table->json('items')->nullable();
            $table->json('totals')->nullable();
            $table->string('provider_ref')->nullable();
            $table->longText('xml')->nullable();
            $table->string('ettn')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despatches');
    }
};

