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
            $table->uuid('client_uuid')->nullable()->unique();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penggarap_lahans', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['client_uuid', 'gps_accuracy_m', 'captured_at']);
        });
    }
};
