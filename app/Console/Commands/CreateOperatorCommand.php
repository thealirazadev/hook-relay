<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateOperatorCommand extends Command
{
    protected $signature = 'app:create-user {email} {--name=}';

    protected $description = 'Create an operator account for the dashboard';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $name = trim((string) $this->option('name')) ?: Str::before($email, '@');

        $password = $this->secret('Password (min 8 characters)');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Operator {$email} created.");

        return self::SUCCESS;
    }
}
