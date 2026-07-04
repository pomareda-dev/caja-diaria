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
        Schema::table('movements', function (Blueprint $table): void {
            $table->boolean('is_projected')->default(false);
            $table->integer('sort_order')->default(0);
            $table->index(['user_id', 'date', 'is_projected', 'sort_order'], 'movements_user_date_projected_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table): void {
            $table->dropIndex('movements_user_date_projected_sort_index');
            $table->dropColumn('is_projected');
            $table->dropColumn('sort_order');
        });
    }
};
