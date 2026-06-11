<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_type_id')->constrained('journal_types');
            $table->string('entry_number', 50);
            $table->date('entry_date');
            $table->string('document_number', 50)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('fiscal_period_id')->nullable()->constrained('fiscal_periods')->nullOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->boolean('is_manual_number')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('entry_number');
            $table->index(['entry_date', 'status']);
            $table->index(['journal_type_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
