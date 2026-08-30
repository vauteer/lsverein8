<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many days ahead of the execution date a SEPA collection is announced.
 *
 * Was a private const 8 in two controllers, carried over from lsverein7. It is
 * the bank's lead time, so it belongs to the club: a club that changes bank
 * changes this, and the two collection dialogs then default alike without
 * anybody editing PHP.
 *
 * Default 8 so every existing club keeps the number it has been using.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->unsignedTinyInteger('sepa_lead_days')->default(8)->after('sepa_mandate_date');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('sepa_lead_days');
        });
    }
};
