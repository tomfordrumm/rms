<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['restaurant_id', 'date', 'time', 'status'], 'reservations_restaurant_date_time_status_index');
            $table->index(['table_id', 'date', 'time', 'status'], 'reservations_table_date_time_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_restaurant_date_time_status_index');
            $table->dropIndex('reservations_table_date_time_status_index');
        });
    }
};
