<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['bank', 'wallet', 'cash', 'credit', 'other']);
            $table->decimal('balance', 12, 2)->default(0);
            $table->boolean('exclude_from_reconciliation')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
