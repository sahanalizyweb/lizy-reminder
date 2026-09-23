<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsStaff(string $name = 'Lizy Reminder'): User
    {
        return tap(User::where('name', $name)->firstOrFail(), fn ($user) => Sanctum::actingAs($user));
    }

    private function pumpCategory(): int
    {
        return ProductCategory::where('name', 'Pump')->value('id');
    }

    private function makeProduct(array $overrides = []): Reminder
    {
        return Reminder::create($overrides + [
            'reminder_type' => Reminder::TYPE_PRODUCT,
            'customer_name' => 'Acme Traders',
            'phone' => '9876543210',
            'product_name' => 'Deep Well Pump',
            'product_category_id' => $this->pumpCategory(),
            'quantity' => 1,
            'scheduled_date' => '2026-09-21',
            'reminder_date' => '2026-09-21',
            'assigned_to' => User::where('name', 'Sahana')->value('id'),
        ]);
    }

    private function makeItService(array $overrides = []): Reminder
    {
        return Reminder::create($overrides + [
            'reminder_type' => Reminder::TYPE_IT_SERVICE,
            'customer_name' => 'Acme Traders',
            'phone' => '9876543210',
            'website_link' => 'https://acme-traders.example.com',
            'scheduled_date' => '2026-09-21',
            'reminder_date' => '2026-09-21',
            'assigned_to' => User::where('name', 'Sahana')->value('id'),
        ]);
    }

    private function ids(string $query): array
    {
        return collect($this->getJson("/api/reminders?$query")->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/reminders')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/product-categories')->assertUnauthorized();
    }

    public function test_staff_login_and_shared_team_login_work(): void
    {
        $token = $this->postJson('/api/login', ['email' => 'sahana@lizyweb.in', 'password' => 'Lizy@2026'])
            ->assertOk()->assertJsonPath('user.name', 'Sahana')->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.name', 'Sahana');

        $this->postJson('/api/login', ['email' => 'omsreminder@gmail.com', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.email', 'omsreminder@gmail.com');

        $this->postJson('/api/login', ['email' => 'sahana@lizyweb.in', 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_staff_list_is_exactly_the_new_list(): void
    {
        $this->actingAsStaff();

        $names = $this->getJson('/api/users')->assertOk()->json('data.*.name');
        sort($names);

        $expected = [
            'Shreen Rozan', 'Asma', 'Aafrin', 'Suliha', 'Sahana', 'Sathika', 'Gokul', 'Rakesh',
            'Anas', 'Ashif', 'Safron', 'Rashik', 'Rahmath', 'Ravikumar', 'Rafeek', 'Vazeem',
        ];
        sort($expected);
        $this->assertSame($expected, $names);
        $this->assertNotContains('Lizy Reminder', $names);
        $this->assertNotContains('Yasmin', $names);
        $this->assertNotContains('Manikandan', $names);
        $this->assertNotContains('Pavithran', $names);

        // Removed from the assignable list, but still able to log in.
        $this->postJson('/api/login', ['email' => 'yasmin@lizyweb.in', 'password' => 'Lizy@2026'])->assertOk();
    }

    public function test_product_categories_endpoint(): void
    {
        $this->actingAsStaff();

        $names = $this->getJson('/api/product-categories')->assertOk()->json('data.*.name');
        $this->assertEqualsCanonicalizing(['Pump', 'Motor', 'Electrical', 'Hardware', 'Accessories', 'Other'], $names);
    }

    public function test_seeded_reminders_are_product_type_with_categories(): void
    {
        $this->actingAsStaff();

        $rows = $this->getJson('/api/reminders?view=upcoming')->assertOk()->json('data');
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame('Product', $row['reminder_type']);
            $this->assertContains($row['product_category_name'], ['Pump', 'Motor']);
            $this->assertNull($row['website_link']);
        }
    }

    public function test_reminder_type_dropdown_only_accepts_product_and_it_service(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'Hosting', 'phone' => '9876543210',
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reminder_type']);

        $this->getJson('/api/reminders?reminder_type=Hosting')->assertUnprocessable();
    }

    public function test_create_product_reminder_requires_product_fields(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'phone' => '9876543210',
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['product_name', 'product_category_id', 'quantity']);

        $data = $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'customer_name' => 'Blue Bay', 'phone' => '9876500000',
            'product_name' => 'V-Type Motor', 'product_category_id' => ProductCategory::where('name', 'Motor')->value('id'),
            'quantity' => 2, 'price' => 4500.50,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ])->assertCreated()->json('data');

        $this->assertSame('V-Type Motor', $data['product_name']);
        $this->assertSame('Motor', $data['product_category_name']);
        $this->assertSame(2, $data['quantity']);
        $this->assertEquals(4500.50, $data['price']);
        $this->assertNull($data['website_link']);
        $this->assertNull($data['assigned_to']);
        $this->assertNull($data['assigned_to_name']);
    }

    public function test_create_it_service_reminder_requires_website_link_and_normalises_scheme(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'IT Service', 'phone' => '9876543210',
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['website_link']);

        $data = $this->postJson('/api/reminders', [
            'reminder_type' => 'IT Service', 'customer_name' => 'Blue Bay', 'phone' => '9876500000',
            'website_link' => 'example.com', 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ])->assertCreated()->json('data');

        $this->assertSame('https://example.com', $data['website_link']);
        $this->assertNull($data['product_name']);
        $this->assertNull($data['product_category_id']);
        $this->assertNull($data['price']);
    }

    public function test_it_service_does_not_require_product_fields_and_vice_versa(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'IT Service', 'phone' => '9876543210', 'website_link' => 'https://example.com',
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertCreated();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump', 'quantity' => 1,
            'product_category_id' => $this->pumpCategory(),
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertCreated()->assertJsonMissingValidationErrors(['website_link']);
    }

    public function test_website_link_is_a_clickable_new_tab_link_source_and_search_matches_it(): void
    {
        $this->actingAsStaff();
        $service = $this->makeItService(['website_link' => 'https://lizyweb.in']);

        $this->getJson("/api/reminders/{$service->id}")->assertOk()->assertJsonPath('data.website_link', 'https://lizyweb.in');
        $this->assertSame([$service->id], $this->ids('search=lizyweb.in'));
    }

    public function test_search_matches_product_name_customer_phone_and_notes(): void
    {
        $this->actingAsStaff();
        $product = $this->makeProduct(['notes' => 'Client wants a quote for the annual plan']);

        $this->assertSame([$product->id], $this->ids('search=Acme'));
        $this->assertSame([$product->id], $this->ids('search=Deep+Well'));
        $this->assertSame([$product->id], $this->ids('search=annual'));
        $this->assertCount(1, $this->ids('search=98765'));
    }

    public function test_state_follows_the_current_date(): void
    {
        $this->actingAsStaff();

        Carbon::setTestNow('2026-09-23 09:00:00');
        $this->assertCount(2, $this->ids('view=today'));
        $this->getJson('/api/reminders/summary')->assertJson(['today' => 2, 'upcoming' => 1, 'overdue' => 0]);

        Carbon::setTestNow('2026-09-25 09:00:00');
        $overdue = $this->getJson('/api/reminders?view=overdue')->assertOk()->json('data');
        $this->assertCount(2, $overdue);
        $this->assertSame(['Overdue', 'Overdue'], array_column($overdue, 'status'));
    }

    public function test_completing_removes_it_from_overdue_and_persists(): void
    {
        $this->actingAsStaff();
        Carbon::setTestNow('2026-09-25 09:00:00');

        $id = $this->ids('view=overdue')[0];
        $this->postJson("/api/reminders/$id/complete")->assertOk()->assertJsonPath('data.status', 'Completed');

        $this->assertDatabaseHas('reminders', ['id' => $id, 'status' => 'Completed']);
        $this->assertNotContains($id, $this->ids('view=overdue'));
        $this->assertContains($id, $this->ids('view=completed'));
    }

    public function test_multiple_filters_work_together(): void
    {
        $this->actingAsStaff();
        $gokul = User::where('name', 'Gokul')->value('id');
        $sahana = User::where('name', 'Sahana')->value('id');

        $match = $this->makeItService(['scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25', 'assigned_to' => $gokul]);
        $this->makeItService(['scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25', 'assigned_to' => $sahana]);
        $this->makeProduct(['scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25', 'assigned_to' => $gokul]);
        $this->makeItService(['scheduled_date' => '2026-11-25', 'reminder_date' => '2026-11-25', 'assigned_to' => $gokul]);

        $this->assertSame(
            [$match->id],
            $this->ids("view=upcoming&reminder_type=IT+Service&assigned_to=$gokul&range=next7"),
        );

        // The 3 seeded reminders are unassigned too, so check containment rather than exact equality.
        $unassigned = $this->makeProduct(['assigned_to' => null]);
        $this->assertContains($unassigned->id, $this->ids('assigned_to=unassigned'));
        $this->assertNotContains($match->id, $this->ids('assigned_to=unassigned'));
    }

    public function test_assigned_to_is_optional_unassigned_is_allowed(): void
    {
        $this->actingAsStaff();

        $data = $this->postJson('/api/reminders', [
            'reminder_type' => 'IT Service', 'phone' => '9876543210', 'website_link' => 'https://example.com',
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-10-01',
        ])->assertCreated()->json('data');

        $this->assertNull($data['assigned_to']);
        $this->assertDatabaseHas('reminders', ['id' => $data['id'], 'assigned_to' => null]);
    }

    public function test_blank_customer_is_stored_as_not_provided(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'phone' => '9444661618', 'product_name' => 'Pump', 'quantity' => 1,
            'product_category_id' => $this->pumpCategory(),
            'scheduled_date' => '2026-09-30', 'reminder_date' => '2026-09-30',
        ])->assertCreated()->assertJsonPath('data.customer_name', 'Not Provided');
    }

    public function test_show_update_and_delete(): void
    {
        $this->actingAsStaff();
        $reminder = $this->makeProduct();

        $this->getJson("/api/reminders/{$reminder->id}")->assertOk()->assertJsonPath('data.phone', $reminder->phone);

        $rafeek = User::where('name', 'Rafeek')->value('id');
        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'Product', 'customer_name' => 'Ravi', 'phone' => $reminder->phone,
            'product_name' => 'Openwell Pump', 'product_category_id' => $this->pumpCategory(), 'quantity' => 4,
            'scheduled_date' => '2026-09-24', 'reminder_date' => '2026-09-24', 'assigned_to' => $rafeek,
            'status' => 'Completed',
        ])->assertOk()->assertJsonPath('data.status', 'Completed')->assertJsonPath('data.assigned_to_name', 'Rafeek');

        $this->assertNotNull($reminder->fresh()->completed_at);

        $this->deleteJson("/api/reminders/{$reminder->id}")->assertNoContent();
        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
        $this->getJson("/api/reminders/{$reminder->id}")->assertNotFound();
    }

    public function test_a_legacy_typed_reminder_can_still_be_saved_without_forcing_a_type_change(): void
    {
        $this->actingAsStaff();

        $legacy = Reminder::create([
            'reminder_type' => 'Hosting', 'customer_name' => 'Old Co', 'phone' => '9000000000',
            'product_name' => 'Shared hosting', 'quantity' => 1,
            'scheduled_date' => '2026-09-10', 'reminder_date' => '2026-09-10',
        ]);

        $this->getJson("/api/reminders/{$legacy->id}")->assertOk()->assertJsonPath('data.reminder_type', 'Hosting');

        $this->putJson("/api/reminders/{$legacy->id}", [
            'reminder_type' => 'Hosting', 'customer_name' => 'Old Co', 'phone' => '9000000000',
            'scheduled_date' => '2026-09-10', 'reminder_date' => '2026-09-10', 'notes' => 'still here',
        ])->assertOk()->assertJsonPath('data.reminder_type', 'Hosting')->assertJsonPath('data.notes', 'still here');
    }

    public function test_deleting_staff_unassigns_their_reminders(): void
    {
        $user = User::where('name', 'Vazeem')->firstOrFail();
        $reminder = $this->makeProduct(['assigned_to' => $user->id]);

        $user->delete();

        $this->assertNull($reminder->fresh()->assigned_to);
    }

    public function test_pagination_is_applied_in_the_database(): void
    {
        $this->actingAsStaff();
        foreach (range(1, 20) as $i) {
            $this->makeProduct(['product_name' => "Item $i"]);
        }

        $response = $this->getJson('/api/reminders?per_page=10&page=2')->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(23, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }
}
