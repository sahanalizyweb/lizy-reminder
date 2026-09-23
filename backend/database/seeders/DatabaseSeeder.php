<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed product categories, staff, the shared team login and the three
     * real starting reminders. Safe to run more than once.
     */
    public function run(): void
    {
        $password = env('SEED_STAFF_PASSWORD', 'Lizy@2026');

        foreach (['Pump', 'Motor', 'Electrical', 'Hardware', 'Accessories', 'Other'] as $name) {
            ProductCategory::firstOrCreate(['name' => $name]);
        }

        // The assignable staff list, exactly as given (is_staff accounts only).
        $staff = [
            'Shreen Rozan' => 'shreenrozan@lizyweb.in',
            'Asma' => 'asma@lizyweb.in',
            'Aafrin' => 'aafrin@lizyweb.in',
            'Suliha' => 'suliha@lizyweb.in',
            'Sahana' => 'sahana@lizyweb.in',
            'Sathika' => 'sathika@lizyweb.in',
            'Gokul' => 'gokul@lizyweb.in',
            'Rakesh' => 'rakesh@lizyweb.in',
            'Anas' => 'anas@lizyweb.in',
            'Ashif' => 'ashif@lizyweb.in',
            'Safron' => 'safron@lizyweb.in',
            'Rashik' => 'rashik@lizyweb.in',
            'Rahmath' => 'rahmath@lizyweb.in',
            'Ravikumar' => 'ravikumar@lizyweb.in',
            'Rafeek' => 'rafeek@lizyweb.in',
            'Vazeem' => 'vazeem@lizyweb.in',
        ];

        // Each staff member's own login is a User account scoped to themselves: they
        // see and manage only reminders assigned to them, and can never reassign one
        // to anyone else (see ReminderController/ReminderPolicy).
        foreach ($staff as $name => $email) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'is_staff' => true, 'role' => User::ROLE_USER],
            );
            $user->update(['assigned_person_id' => $user->id]);
        }

        // Shared login for the team. Not an assignable staff member; Admin, so it
        // needs no Assigned Person and can see/manage every reminder and every user.
        User::updateOrCreate(
            ['email' => 'omsreminder@gmail.com'],
            ['name' => 'Lizy Reminder', 'password' => 'password', 'is_staff' => false, 'role' => User::ROLE_ADMIN],
        );

        // Earlier staff no longer on the assignable list. Kept (not deleted) so
        // their login and any reminders already assigned to them still work. Not
        // being on the assignable list, they have no Assigned Person to link to,
        // so they're Admins too rather than a User account with nothing to see.
        foreach (['Manikandan' => 'manikandan@lizyweb.in', 'Pavithran' => 'pavithran@lizyweb.in', 'Yasmin' => 'yasmin@lizyweb.in'] as $name => $email) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'is_staff' => false, 'role' => User::ROLE_ADMIN],
            );
        }

        // Phone numbers are stored exactly as supplied.
        $reminders = [
            ['+91 99620 82959', '2026-09-23', 'Openwell Pump', 1, 'Pump'],
            ['9444661618', '2026-09-23', 'Openwell Pump', 2, 'Pump'],
            ['97540002086', '2026-09-29', 'V-Type Motor', 3, 'Motor'],
        ];

        foreach ($reminders as [$phone, $date, $product, $quantity, $categoryName]) {
            $category = ProductCategory::where('name', $categoryName)->first();

            Reminder::updateOrCreate(
                ['phone' => $phone, 'product_name' => $product, 'scheduled_date' => $date],
                [
                    'reminder_type' => Reminder::TYPE_PRODUCT,
                    'customer_name' => 'Not Provided',
                    'quantity' => $quantity,
                    'product_category_id' => $category?->id,
                    'reminder_date' => $date,
                    'status' => Reminder::STATUS_PENDING,
                ],
            );
        }
    }
}
