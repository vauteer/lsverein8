<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id()->from(200);
            $table->foreignId('club_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('blsv_id')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
