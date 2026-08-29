<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `display` read like a switch. It holds which parts of the club's
     * identity appear — logo, name or both (App\Enums\ClubIdentityDisplay), which is
     * what ClubIdentity.vue renders.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('display', 'identity_display');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('identity_display', 'display');
        });
    }
};
