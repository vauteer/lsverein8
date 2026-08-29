<?php

namespace App\Console\Commands;

use App\Enums\ClubRole;
use App\Enums\LandingPage;
use App\Models\Club;
use App\Models\User;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app:user {name} {email} {--password=} {--club=1} {--role=admin}')]
#[Description('Create or edit a user of a club')]
class UserCommand extends Command
{
    /**
     * Ported from lscraft5, where a user was just name/email/password. Here an
     * account is worthless without a club: every scope hangs off `users.club_id`
     * and the permissions off the `club_user` role, so both are set as well.
     * The club defaults to 1, which is what `currentClubId()` resolves to on the
     * CLI anyway.
     */
    public function handle(): int
    {
        $club = Club::find((int) $this->option('club'));

        if (! $club) {
            $this->error("No club found with id {$this->option('club')}.");

            return self::FAILURE;
        }

        $role = $this->role();

        if (! $role) {
            $this->error("Unknown role {$this->option('role')}. Use basic, advanced or admin.");

            return self::FAILURE;
        }

        $email = $this->argument('email');
        $password = $this->option('password');

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->argument('name');
        $user->club_id = $club->id;

        // The column is NOT NULL with a default, but a model created without
        // the value never reads it back — same trap as in UserFactory.
        $user->landing_page ??= LandingPage::Dashboard;

        if ($password) {
            $user->password = Hash::make($password);
        } elseif (! $user->password) {
            $password = Str::random(8);
            $user->password = Hash::make($password);
        }

        try {
            $user->save();
            // Without detaching: an account may belong to several clubs, and
            // this command speaks for one of them only.
            $user->clubs()->syncWithoutDetaching([$club->id => ['role' => $role->value]]);
        } catch (Exception $e) {
            $this->error('User creation failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $info = "Updated user {$user->name} in {$club->name} as {$role->name}";

        if ($password) {
            $info .= ". The password is {$password}";
        }

        $this->info($info);

        return self::SUCCESS;
    }

    private function role(): ?ClubRole
    {
        return match (strtolower((string) $this->option('role'))) {
            'basic' => ClubRole::Basic,
            'advanced' => ClubRole::Advanced,
            'admin' => ClubRole::Admin,
            default => null,
        };
    }
}
