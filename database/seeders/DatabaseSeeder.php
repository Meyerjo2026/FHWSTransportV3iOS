<?php

namespace Database\Seeders;

use App\Models\ClinicalSite;
use App\Models\GroupAssignment;
use App\Models\User;
use App\Support\TransportOptions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demo = [
            ['name' => 'J Meyer', 'email' => 'admin@cput.ac.za', 'number' => '0210000000', 'password' => 'admin123', 'role' => 'admin'],
            ['name' => 'N September', 'email' => 'staff@cput.ac.za', 'number' => '0210000001', 'password' => 'staff123', 'role' => 'staff'],
            ['name' => 'Thandi Nkosi', 'email' => 'student@mycput.ac.za', 'number' => '0821234567', 'password' => 'student123', 'role' => 'student'],
        ];

        // Demo accounts use well-known passwords: never create them in production
        // unless SEED_DEMO_USERS=true is set deliberately.
        $seedDemo = ! app()->environment('production') || filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN);

        foreach ($seedDemo ? $demo : [] as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'number' => $u['number'],
                    'role' => $u['role'],
                    'password' => bcrypt($u['password']),
                ]
            );
        }

        foreach (TransportOptions::SITE_SEED as $name => [$address, $lat, $lng]) {
            ClinicalSite::updateOrCreate(
                ['name' => $name],
                ['address' => $address, 'lat' => $lat, 'lng' => $lng, 'type' => TransportOptions::deriveType($name)]
            );
        }

        // Demo assignment so the staff-side group filtering has
        // something to show out of the box: the demo staff account is
        // responsible for Year 2.
        $demoStaff = User::where('email', 'staff@cput.ac.za')->first();
        if ($demoStaff) {
            GroupAssignment::updateOrCreate(['type' => 'year', 'value' => 'Year 2'], ['staff_id' => $demoStaff->id]);
        }
    }
}
