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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'xml')) {
                $table->text('xml')->nullable()->after('kolaysoft_ref');
            }
            if (!Schema::hasColumn('invoices', 'ettn')) {
                $table->string('ettn')->nullable()->after('xml');
                $table->index(['organization_id','ettn']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'ettn')) {
                $table->dropIndex(['organization_id','ettn']);
                $table->dropColumn('ettn');
            }
            if (Schema::hasColumn('invoices', 'xml')) {
                $table->dropColumn('xml');
            }
        });
    }
};
