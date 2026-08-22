<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount');
            $table->string('transfer_text')->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
