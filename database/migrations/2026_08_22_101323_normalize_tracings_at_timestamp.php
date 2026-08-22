<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the implicit DEFAULT/ON UPDATE current_timestamp() that MariaDB attached to
 * `tracings.at` when the table was first created in lsverein7 under
 * explicit_defaults_for_timestamp = 0.
 *
 * ON UPDATE current_timestamp() silently rewrites the audit timestamp whenever a row
 * is updated, and the attributes are absent on any database built from the migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracings', function (Blueprint $table) {
            $table->timestamp('at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tracings', function (Blueprint $table) {
            $table->timestamp('at')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }
};
