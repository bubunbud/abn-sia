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
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('group_name')->nullable();
            $table->string('account_category', 30);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_header')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
