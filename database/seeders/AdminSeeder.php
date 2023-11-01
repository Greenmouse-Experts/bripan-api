<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create(
            // [
            //     'membership_id' =>  config('app.name').'-'. date('YmdHis'),
            //     'account_type' => 'Administrator',
            //     'first_name' => 'BRIPAN',
            //     'last_name' => 'Admin',
            //     'username' => 'super-admin',
            //     'email' => 'admin@bripan.org.ng',
            //     'email_verified_at' => now(),
            //     'password' => bcrypt('Password'),
            //     'current_password' => 'Password',
            //     'role' => 'Super Admin',
            // ], 
            [
                'membership_id' =>  config('app.name').'-'. date('YmdHis'),
                'account_type' => 'Administrator',
                'first_name' => 'BRIPAN',
                'last_name' => 'Support',
                'username' => 'sub-admin',
                'email' => 'admin@org.ng',
                'email_verified_at' => now(),
                'password' => bcrypt('Password'),
                'current_password' => 'Password',
                'role' => 'Super Admin',
            ]
        );
    }
}
