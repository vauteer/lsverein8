<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const DEFAULTS = [
        1 => '25 Jahre',
        2 => '30 Jahre',
        3 => '40 Jahre',
        4 => '50 Jahre',
        5 => '60 Jahre',
        6 => '70 Jahre',
        7 => 'Ehrenvorstand',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('events')->insert(collect(self::DEFAULTS)
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
        DB::table('events')->whereIn('id', array_keys(self::DEFAULTS))->delete();
    }
};
