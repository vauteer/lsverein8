<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `sepa` named the standard rather than the value: it holds the club's
     * SEPA creditor identifier (Gläubiger-ID), which generateSepa() writes
     * into the XML. `sepa_date` is the default mandate signature date.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('sepa', 'sepa_creditor_id');
            $table->renameColumn('sepa_date', 'sepa_mandate_date');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('sepa_creditor_id', 'sepa');
            $table->renameColumn('sepa_mandate_date', 'sepa_date');
        });
    }
};
