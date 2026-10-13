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
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->string('nama_penunjuk', 100)->nullable()->after('nama_kth');
            $table->string('kabupaten_kota', 100)->nullable()->after('nama_penunjuk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->dropColumn(['nama_penunjuk', 'kabupaten_kota']);
        });
    }
};
