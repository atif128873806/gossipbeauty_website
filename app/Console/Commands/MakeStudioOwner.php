<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeStudioOwner extends Command
{
    protected $signature = 'gossips:make-owner {name} {email}';

    protected $description = 'Create or promote a protected Gossips Beauty owner account.';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a name and valid email address.');

            return self::FAILURE;
        }
        $password = $this->secret('Set a strong password');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password must contain at least 12 characters.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $name, $password): void {
            $user = User::query()->where('email', $email)->first();
            if ($user?->staff_id) {
                Staff::query()->whereKey($user->staff_id)->update(['user_id' => null]);
            }
            if ($user) {
                $user->update(['name' => $name, 'password' => $password, 'role' => 'owner', 'staff_id' => null]);
            } else {
                User::query()->create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'owner']);
            }
        });
        $this->info('Owner account saved. Sign in at /admin/login.');

        return self::SUCCESS;
    }
}
