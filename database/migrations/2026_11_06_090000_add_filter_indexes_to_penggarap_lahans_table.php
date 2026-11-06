<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the admin filters/sorts and the export list ordering.
     */
    public function up(): void
    {
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->index(['created_by', 'captured_at']);
            $table->index('captured_at');
            $table->index('kabupaten_kota');
            $table->index('luas_garapan');
        });
    }

    public function down(): void
    {
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'captured_at']);
            $table->dropIndex(['captured_at']);
            $table->dropIndex(['kabupaten_kota']);
            $table->dropIndex(['luas_garapan']);
        });
    }
};
