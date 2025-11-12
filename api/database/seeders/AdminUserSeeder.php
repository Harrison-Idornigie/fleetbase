<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Fleetbase\Models\User;
use Fleetbase\Models\Company;
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
        $password = 'MySecur3P@ssw0rd!'; // dev seed only — change for production/CI

        $user = User::firstOrNew(['email' => $email]);
        $isNew = !$user->exists;

        $user->name = 'Local Admin';
        $user->email = $email;

        // Only hash password if user is new or password field is empty
        if ($isNew || empty($user->password)) {
            $user->password = Hash::make($password);
        }

        $user->type = 'admin';
        $user->email_verified_at = now();

        if (empty($user->uuid)) {
            $user->uuid = (string) \Illuminate\Support\Str::uuid();
        }

        // Ensure company exists
        if (empty($user->company_uuid)) {
            $company = Company::firstOrCreate(
                ['name' => 'Admin Company'],
                [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'owner_uuid' => $user->uuid,
                    'status' => 'active'
                ]
            );
            $user->company_uuid = $company->uuid;
        }

        $user->save();

        $this->command->info("Admin user created/updated: {$email} (password: {$password})");
        $this->command->info("Company: {$user->company_name}");
    }
}
