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
        Schema::create('penggarap_lahans', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 100);
            $table->string('no_hp', 20)->nullable();
            $table->decimal('luas_garapan', 8, 2)->comment('Satuan Hektar (contoh: 1.50)');
            $table->integer('lama_menggarap')->comment('Satuan Tahun');
            $table->string('foto_path')->comment('Lokasi simpan foto di storage Laravel');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->double('utm_x')->nullable();
            $table->double('utm_y')->nullable();
            $table->userstamps();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggarap_lahans');
    }
};
