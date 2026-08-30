<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every honour and every role belongs to a club, as every section now does.
 *
 * A null `club_id` made a row installation-wide: listed in every club, editable
 * only by root. The live database has none — all 20 events and 40 roles name
 * their club — and the only thing that ever created one was the pair of
 * insert_*_defaults migrations, whose contents are commented out as of the same
 * day. So there is nothing to clean up first, on a fresh build or here.
 *
 * If some database still holds one, this fails rather than guessing: the
 * connection runs in strict mode, where MariaDB refuses to write a NOT NULL
 * column over existing nulls instead of silently turning them into zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->nullable(false);
    }

    public function down(): void
    {
        $this->nullable(true);
    }

    private function nullable(bool $nullable): void
    {
        foreach (['events', 'roles'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($nullable): void {
                // The whole definition, not just the nullability: change()
                // rewrites the column from what it is given, so the foreign key
                // and its cascade have to be named again.
                $table->foreignId('club_id')->nullable($nullable)->change();
            });
        }
    }
};
