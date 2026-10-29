<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Fictional demo data only. The passwords below are dev-only and are not used anywhere else. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Test Borrower One', 'borrower1@example.test', User::ROLE_BORROWER],
            ['Test Borrower Two', 'borrower2@example.test', User::ROLE_BORROWER],
            ['Test Approver', 'approver@example.test', User::ROLE_APPROVER],
            ['Test Staff', 'staff@example.test', User::ROLE_STAFF],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = 'DevPass123!';
            $user->role = $role;
            $user->email_verified_at = now();
            $user->save();
        }

        $items = [
            ['Projector A', 'EQ-001'], ['Projector B', 'EQ-002'], ['Laptop Cart 1', 'EQ-003'],
            ['Extension Cord Set', 'EQ-004'], ['Digital Camera 1', 'EQ-005'], ['Portable Speaker', 'EQ-006'],
        ];

        foreach ($items as [$name, $tag]) {
            Equipment::firstOrCreate(['asset_tag' => $tag], ['name' => $name, 'status' => Equipment::AVAILABLE]);
        }
    }
}
