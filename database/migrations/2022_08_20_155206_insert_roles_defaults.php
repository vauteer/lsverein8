<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const DEFAULTS = [
        1 => '1. Vorstand',
        2 => '2. Vorstand',
        3 => 'Kassier',
        7 => 'Schriftführer',
        8 => 'Ehrenamtsbeauftragter',
        9 => 'Beisitzer',
        10 => 'Kassenprüfer',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('roles')->insert(collect(self::DEFAULTS)
            ->map(fn (string $name, int $id): array => [
                'id' => $id,
                'club_id' => null,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all());
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('id', array_keys(self::DEFAULTS))->delete();
    }
};
