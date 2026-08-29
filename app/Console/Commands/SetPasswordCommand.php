<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:set-password {email} {password}')]
#[Description('Set the password for an existing user')]
class SetPasswordCommand extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        // Password::default(), like PasswordValidationRules: the strength rules
        // are only switched on in production (AppServiceProvider), so a locally
        // set password is not held to them either way.
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::default()]],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->update(['password' => Hash::make($password)]);

        $this->info("Password updated for {$email}.");

        return self::SUCCESS;
    }
}
