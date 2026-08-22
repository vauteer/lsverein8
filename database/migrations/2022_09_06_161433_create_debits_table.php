<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->decimal('amount');
            $table->string('transfer_text');
            $table->date('due_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debits');
    }
};
