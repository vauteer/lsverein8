<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `users.locale` becomes an override rather than the source of truth: the
     * club's language applies, and a user only stores a value here to deviate
     * from it. Null means "follow the club".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->default(null)->change();
        });

        // Everyone matched their club exactly, because the club language did
        // not exist as a concept when these rows were written - nobody chose
        // to deviate. Clearing those makes them follow the club from now on,
        // which changes nothing today and is what the column now means.
        // A user whose language differs from their club keeps it.
        DB::table('users')
            ->join('clubs', 'clubs.id', '=', 'users.club_id')
            ->whereColumn('users.locale', 'clubs.locale')
            ->update(['users.locale' => null]);
    }

    public function down(): void
    {
        // Restore a concrete value first: the column is about to be NOT NULL
        // again, and an inheriting user has nothing in it.
        DB::table('users')
            ->join('clubs', 'clubs.id', '=', 'users.club_id')
            ->whereNull('users.locale')
            ->update(['users.locale' => DB::raw('clubs.locale')]);

        DB::table('users')->whereNull('locale')->update(['locale' => config('app.locale')]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable(false)->default('de')->change();
        });
    }
};
