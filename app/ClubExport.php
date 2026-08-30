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
    /**
     * The users the export carries: everybody in this club's `club_user`.
     *
     * @var Collection<int, int>|null
     */
    private ?Collection $userIds = null;

    public function __construct(private readonly Club $club) {}

    public function toSql(): string
    {
        $sql = $this->header();

        foreach ($this->tables() as $table => $query) {
            $sql .= $this->tableSql($table, $query);
        }

        return $sql.$this->footer();
    }

    /**
     * A filename the club can tell apart, e.g. "sportverein-2026-08-26.sql".
     */
    public function filename(): string
    {
        return str($this->club->name)->slug().'-'.now()->format('Y-m-d').'.sql';
    }

    /**
     * Every table in the export, each already narrowed to this club.
     *
     * Listed parents before children for reading, not for the database: the
     * script disables foreign key checks, and `users` sits at the top even
     * though it points at `clubs` below it.
     *
     * @return array<string, Builder>
     */
    private function tables(): array
    {
        $clubId = $this->club->id;
        $memberIds = DB::table('members')->where('club_id', $clubId)->pluck('id');

        return [
            'users' => DB::table('users')->whereIn('id', $this->userIds()),
            'clubs' => DB::table('clubs')->where('id', $clubId),
            'club_user' => DB::table('club_user')->where('club_id', $clubId),
            'members' => DB::table('members')->where('club_id', $clubId),
            'club_member' => DB::table('club_member')->where('club_id', $clubId),
            'sections' => DB::table('sections')->where('club_id', $clubId),
            'member_section' => DB::table('member_section')->whereIn('member_id', $memberIds),
            'events' => DB::table('events')->where('club_id', $clubId),
            'event_member' => DB::table('event_member')->whereIn('member_id', $memberIds),
            'roles' => DB::table('roles')->where('club_id', $clubId),
            'member_role' => DB::table('member_role')->whereIn('member_id', $memberIds),
            'items' => DB::table('items')->where('club_id', $clubId),
            'item_member' => DB::table('item_member')->whereIn('member_id', $memberIds),
            'subscriptions' => DB::table('subscriptions')->where('club_id', $clubId),
            'member_subscription' => DB::table('member_subscription')->whereIn('member_id', $memberIds),
            'debits' => DB::table('debits')->whereIn('member_id', $memberIds),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function userIds(): Collection
    {
        return $this->userIds ??= DB::table('club_user')
            ->where('club_id', $this->club->id)
            ->pluck('user_id');
    }

    /**
     * One table: a TRUNCATE, then a single multi-row INSERT.
     */
    private function tableSql(string $table, Builder $query): string
    {
        $sql = "TRUNCATE `{$table}`;".PHP_EOL;

        $rows = $query->orderBy('id')->get()
            ->map(fn (object $row): array => $this->rewrite($table, (array) $row));

        if ($rows->isEmpty()) {
            return $sql.PHP_EOL;
        }

        $columns = array_keys($rows->first());
        $columnList = implode(', ', array_map(fn (string $column): string => "`{$column}`", $columns));

        $values = $rows
            ->map(fn (array $row): string => '('.implode(', ', array_map(
                fn (string $column): string => $this->quote($row[$column]),
                $columns
            )).')')
            ->implode(','.PHP_EOL);

        return $sql."INSERT INTO `{$table}` ({$columnList}) VALUES".PHP_EOL.$values.';'.PHP_EOL.PHP_EOL;
    }

    /**
     * The columns a `users` row cannot be handed over as it stands.
     *
     * Three of them, and all three matter because a club admin may export
     * their own club (ClubPolicy::export delegates to update):
     *
     * - `password` and `remember_token` are somebody's credentials. A user of
     *   two clubs appears in both exports, so without this the admin of one
     *   club could read the hash of an account that also works in another —
     *   the root account among them. Whoever restores the file gives every
     *   account a new password, which the header says.
     * - `club_id` is the club a user is *working in*, and that may be a club
     *   this file does not contain. Restored as it stood, `currentClubId()`
     *   would point at a missing row and `currentClub()` return null, which
     *   the first `currentClub()->name` turns into a fatal.
     * - `created_by` may name a user the file does not contain either.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rewrite(string $table, array $row): array
    {
        if ($table !== 'users') {
            return $row;
        }

        $row['password'] = '';
        $row['remember_token'] = null;
        $row['club_id'] = $this->club->id;

        if (! $this->userIds()->contains($row['created_by'])) {
            $row['created_by'] = null;
        }

        return $row;
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
            '--',
            '-- Passwords are not exported. Every account comes over without',
            '-- one and has to be given a new one after the import.',
            '',
            'SET NAMES utf8mb4;',
            "SET time_zone = '+00:00';",
            'SET foreign_key_checks = 0;',
            // Appended, not assigned: a bare assignment drops
            // STRICT_TRANS_TABLES for the session, and the import would then
            // truncate a bad value instead of refusing it.
            "SET sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO');",
            '',
            '',
        ];

        return implode(PHP_EOL, $lines);
    }

    /**
     * Hand the session back as it was found.
     *
     * Without this, whoever sources the file keeps working with foreign key
     * checks disabled — the script turns them off and used to leave them off.
     */
    private function footer(): string
    {
        return 'SET foreign_key_checks = 1;'.PHP_EOL;
    }
}
