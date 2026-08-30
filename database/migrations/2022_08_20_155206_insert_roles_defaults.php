<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeded seven installation-wide roles — commented out on 2026-08-30.
 *
 * They were inserted once per installation with a null `club_id`, which is how
 * every club came to see the same ones. `roles.club_id` is NOT NULL now, so
 * these rows cannot exist any more, and a fresh build that created them would
 * fail the migration that tightens the column.
 *
 * The file stays, and so does its row in `migrations`: the live database ran
 * it in 2022 and its seven rows were given to club 1 long ago. Only a fresh
 * build is affected, and there a club gets its own copies of
 * `Role::DEFAULTS` from ClubController::store() instead.
 *
 * Left in place rather than deleted so the history is still readable.
 */
return new class extends Migration
{
    // /**
    //  * @var array<int, string>
    //  */
    // private const DEFAULTS = [
    //     1 => '1. Vorstand',
    //     2 => '2. Vorstand',
    //     3 => 'Kassier',
    //     7 => 'Schriftführer',
    //     8 => 'Ehrenamtsbeauftragter',
    //     9 => 'Beisitzer',
    //     10 => 'Kassenprüfer',
    // ];
    //
    // public function up(): void
    // {
    //     $now = now();
    //
    //     DB::table('roles')->insert(collect(self::DEFAULTS)
    //         ->map(fn (string $name, int $id): array => [
    //             'id' => $id,
    //             'club_id' => null,
    //             'name' => $name,
    //             'created_at' => $now,
    //             'updated_at' => $now,
    //         ])
    //         ->values()
    //         ->all());
    // }
    //
    // public function down(): void
    // {
    //     DB::table('roles')->whereIn('id', array_keys(self::DEFAULTS))->delete();
    // }
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
