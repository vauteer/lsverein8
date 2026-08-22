<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id()->from(50);
            $table->foreignId('club_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();

            $table->unique(['club_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
