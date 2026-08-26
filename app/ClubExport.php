<?php

namespace App;

use App\Models\Club;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The club-specific slice of the database, as an SQL script.
 *
 * Ported from lsverein7's ExportController/SqlConverter. That version read
 * model attributes and undid Eloquent's casts by hand; this one reads raw rows
 * through the query builder, so the values that go out are the ones the
 * columns hold and there is nothing to convert back.
 *
 * The script TRUNCATEs each table before filling it — see header() for what
 * that means for whoever imports it.
 */
class ClubExport
{
    /**
     * `tracings` is deliberately absent: the audit log is keyed by user and
     * row id with no club of its own, so there is no honest way to slice it.
     * lsverein7 left it out too.
     */
    public function __construct(private readonly Club $club) {}

    public function toSql(): string
    {
        $sql = $this->header();

        foreach ($this->tables() as $table => $query) {
            $sql .= $this->tableSql($table, $query);
        }

        return $sql;
    }

    /**
     * A filename the club can tell apart, e.g. "sportverein-2026-08-26.sql".
     */
    public function filename(): string
    {
        return str($this->club->name)->slug().'-'.now()->format('Y-m-d').'.sql';
    }

    /**
     * Every table in the export, in foreign-key order, each already narrowed
     * to this club.
     *
     * @return array<string, Builder>
     */
    private function tables(): array
    {
        $clubId = $this->club->id;
        $memberIds = DB::table('members')->where('club_id', $clubId)->pluck('id');
        $userIds = DB::table('club_user')->where('club_id', $clubId)->pluck('user_id');

        return [
            'users' => DB::table('users')->whereIn('id', $userIds),
            'clubs' => DB::table('clubs')->where('id', $clubId),
            'club_user' => DB::table('club_user')->where('club_id', $clubId),
            'members' => DB::table('members')->where('club_id', $clubId),
            'club_member' => DB::table('club_member')->where('club_id', $clubId),
            'sections' => $this->ownOrShared('sections', 'member_section', 'section_id', $memberIds),
            'member_section' => DB::table('member_section')->whereIn('member_id', $memberIds),
            'events' => $this->ownOrShared('events', 'event_member', 'event_id', $memberIds),
            'event_member' => DB::table('event_member')->whereIn('member_id', $memberIds),
            'roles' => $this->ownOrShared('roles', 'member_role', 'role_id', $memberIds),
            'member_role' => DB::table('member_role')->whereIn('member_id', $memberIds),
            'items' => DB::table('items')->where('club_id', $clubId),
            'item_member' => DB::table('item_member')->whereIn('member_id', $memberIds),
            'subscriptions' => DB::table('subscriptions')->where('club_id', $clubId),
            'member_subscription' => DB::table('member_subscription')->whereIn('member_id', $memberIds),
            'debits' => DB::table('debits')->whereIn('member_id', $memberIds),
        ];
    }

    /**
     * The club's own rows of a table plus any installation-wide row
     * (`club_id IS NULL`) its members are actually assigned to.
     *
     * `sections`, `events` and `roles` have both kinds. lsverein7 exported
     * only `club_id = N`, which leaves a dangling foreign key the moment a
     * member is given one of the shared rows — the insert_events_defaults
     * migration seeds seven of them into every installation. No club uses one
     * today, which is the only reason that never surfaced.
     *
     * @param  Collection<int, int>  $memberIds
     */
    private function ownOrShared(string $table, string $pivot, string $foreignKey, Collection $memberIds): Builder
    {
        $used = DB::table($pivot)->whereIn('member_id', $memberIds)->pluck($foreignKey);

        return DB::table($table)
            ->where('club_id', $this->club->id)
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('club_id')
                ->whereIn('id', $used));
    }

    /**
     * One table: a TRUNCATE, then a single multi-row INSERT.
     */
    private function tableSql(string $table, Builder $query): string
    {
        $sql = "TRUNCATE `{$table}`;".PHP_EOL;

        $rows = $query->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return $sql.PHP_EOL;
        }

        $columns = array_keys((array) $rows->first());
        $columnList = implode(', ', array_map(fn (string $column): string => "`{$column}`", $columns));

        $values = $rows
            ->map(fn (object $row): string => '('.implode(', ', array_map(
                fn (string $column): string => $this->quote(((array) $row)[$column]),
                $columns
            )).')')
            ->implode(','.PHP_EOL);

        return $sql."INSERT INTO `{$table}` ({$columnList}) VALUES".PHP_EOL.$values.';'.PHP_EOL.PHP_EOL;
    }

    /**
     * One value as an SQL literal.
     *
     * Strings go through PDO::quote rather than lsverein7's
     * `str_replace("'", "\\'", …)`, which escaped the quote but not the
     * backslash — a value ending in one closed the literal early and shifted
     * every column after it.
     */
    private function quote(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => DB::getPdo()->quote((string) $value),
        };
    }

    private function header(): string
    {
        $lines = [
            '-- '.config('app.name').' — '.__('Club export').': '.$this->club->name,
            '-- '.now()->format('Y-m-d H:i'),
            '--',
            '-- WARNING: every table below is TRUNCATEd before it is filled.',
            '-- Import this into an empty database only. Running it against an',
            '-- installation that holds other clubs deletes their data.',
            '',
            'SET NAMES utf8mb4;',
            "SET time_zone = '+00:00';",
            'SET foreign_key_checks = 0;',
            "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';",
            '',
            '',
        ];

        return implode(PHP_EOL, $lines);
    }
}
