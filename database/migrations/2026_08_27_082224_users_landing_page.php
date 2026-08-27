<?php

use App\Enums\LandingPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which screen a user lands on after signing in.
     *
     * NOT NULL with a default rather than nullable: unlike `users.locale`,
     * there is nothing to inherit from — the club has no landing page — so a
     * null would only be a second spelling of "dashboard". The existing rows
     * take the default, which is exactly what they did before this column
     * existed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('landing_page', 20)
                ->default(LandingPage::Dashboard->value)
                ->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('landing_page');
        });
    }
};
