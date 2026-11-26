<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make bookings and payments reference a parent of the same tenant by
     * replacing the single-column foreign keys with (tenant_id, x_id) ones.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id']);
            $table->dropForeign(['unit_id']);
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->foreign(['tenant_id', 'booking_id'])->references(['tenant_id', 'id'])->on('bookings')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'booking_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->dropUnique(['tenant_id', 'id']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
