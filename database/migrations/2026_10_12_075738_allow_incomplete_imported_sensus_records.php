<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->comment('Lokasi simpan foto di storage Laravel')->change();
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 11, 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('penggarap_lahans')->whereNull('foto_path')->orWhereNull('latitude')->orWhereNull('longitude')->exists()) {
            throw new RuntimeException('Lengkapi foto dan koordinat bidang impor sebelum membatalkan migrasi ini.');
        }

        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->string('foto_path')->nullable(false)->comment('Lokasi simpan foto di storage Laravel')->change();
            $table->decimal('latitude', 10, 8)->nullable(false)->change();
            $table->decimal('longitude', 11, 8)->nullable(false)->change();
        });
    }
};
