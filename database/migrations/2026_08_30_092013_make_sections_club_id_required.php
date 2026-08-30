<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every section belongs to a club.
 *
 * `sections.club_id` was nullable, which made a row installation-wide: listed
 * in every club, editable only by root. Nothing ever used it — in the live
 * database all 13 sections name their club — and in reality no two clubs run
 * the same section, so the option only bought special cases in the scope, the
 * policy, the unique rule and the export.
 *
 * `events` and `roles` keep their nullable `club_id`: their defaults ARE seeded
 * installation-wide, by insert_events_defaults and insert_roles_defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // The full definition, not just the nullability: change() rewrites
            // the column from what it is given, so the foreign key and the
            // cascade have to be named again or they are dropped.
            $table->foreignId('club_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->change();
        });
    }
};
