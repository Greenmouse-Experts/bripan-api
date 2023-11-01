<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createSubscriptions = [
            [
                'type' =>  'Fellow',
                'amount' => '255000',
                'created_at' => now(),
                'updated_at' =>now()
            ], 
            [
                'type' =>  'Associate',
                'amount' => '95000',
                'created_at' => now(),
                'updated_at' =>now()
            ]
        ];

        Subscription::insert($createSubscriptions);
    }
}
