<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->string('memo')->nullable();
            $table->date('from');
            $table->date('to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_member');
    }
};
