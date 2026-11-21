<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

#[Signature('user:create {name : Display name} {email : Sign-in email for the mobile app}')]
#[Description('Create an account that can publish posts through the API')]
class MakeUser extends Command
{
    public function handle(): int
    {
        $input = [
            'name' => (string) $this->argument('name'),
            'email' => (string) $this->argument('email'),
            'password' => password('Password', required: true),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create($input);

        $this->components->info("Created [{$input['email']}].");

        return self::SUCCESS;
    }
}
