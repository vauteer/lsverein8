<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `members.payment_method`, which said nothing the bank details did not
 * already say.
 *
 * The column held `k` (direct debit), `r` (invoice) and `n` ("Nichtzahler").
 * `n` was the club's way of recording somebody a family contribution already
 * covers; that now belongs on a 0 € subscription, which names the reason
 * instead of only denying the payment. What is left splits exactly along
 * `iban`, so `Member::payment_method` is derived from it.
 *
 * Verified against production before dropping: for every current member of
 * both clubs, the old rule (`payment_method = 'k'`) and the new one (an IBAN
 * is on file) select the identical set of member/subscription pairs — 231
 * items / 10.780,00 € in club 1, 131 / 2.111,00 € in club 2. The column was
 * also already contradicting itself, with 4 members set to `k` without an
 * IBAN and 4 set to `n` with one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }

    /**
     * Rebuilds the column from the bank details, the same way the application
     * now reads it. `n` cannot be restored and is not meant to be: those rows
     * are identified by their 0 € subscription from here on.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->char('payment_method', 1)->default('r');
        });

        DB::table('members')->whereNotNull('iban')->where('iban', '<>', '')
            ->update(['payment_method' => 'k']);

        Schema::table('members', function (Blueprint $table): void {
            $table->char('payment_method', 1)->default(null)->change();
        });
    }
};
