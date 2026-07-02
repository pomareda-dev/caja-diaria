<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('source', ['manual', 'recurring', 'import']);
            $table->foreignId('recurring_id')->nullable()->constrained('recurring_transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
