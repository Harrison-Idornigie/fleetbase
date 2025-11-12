<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $email = 'admin@example.com';
        $password = 'password'; // dev seed only — change for production/CI

        $user = User::firstOrNew(['email' => $email]);
        $user->name = 'Local Admin';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->type = 'admin';
        $user->email_verified_at = now();
        if (empty($user->uuid)) {
            $user->uuid = (string) \Illuminate\Support\Str::uuid();
        }
        $user->save();

        $this->command->info("Admin user created/updated: {$email} (password: {$password})");
    }
}
