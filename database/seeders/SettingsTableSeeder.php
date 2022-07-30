<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Setting::insert([
            "key" => "us_address",
            "value" => json_encode([
                "address" => "1430 Katy Flewellen Rd",
                "address2" => "Unit 5034",
                "phone" => "+1 832 974 1951",
                "city" => "Katy",
                "state" => "Texas (TX)",
                "zipcode" => "77494",
            ]),
        ]);
    }
}
