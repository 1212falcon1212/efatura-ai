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
        Schema::table('customers', function (Blueprint $table) {
            // Sütunları yeniden adlandır ve ekle
            $table->renameColumn('name', 'surname'); // name -> surname (soyisim)
            $table->string('name')->after('organization_id')->default(''); // yeni name (isim) alanı
            
            // tckn_vkn sütununu ekle
            $table->string('tckn_vkn', 11)->after('surname')->nullable();

            // Adres alanlarını ekle
            $table->string('city')->after('phone')->nullable();
            $table->string('district')->after('city')->nullable();
            $table->string('street_address')->after('district')->nullable();
            $table->string('tax_office')->after('street_address')->nullable();
            $table->string('urn')->after('tax_office')->nullable();

            // Eski sütunları kaldır
            $table->dropColumn(['vkn', 'tckn', 'address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Sütunları geri ekle
            $table->string('vkn')->nullable();
            $table->string('tckn')->nullable();
            $table->json('address')->nullable();
            
            // Yeni sütunları kaldır
            $table->dropColumn(['tckn_vkn', 'city', 'district', 'street_address', 'tax_office', 'urn']);

            // 'name' sütununu geri getir ve 'surname' sütununu kaldır
            $table->dropColumn('name');
            $table->renameColumn('surname', 'name');
        });
    }
};
