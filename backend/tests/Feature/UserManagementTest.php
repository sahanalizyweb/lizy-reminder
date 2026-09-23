<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Manage Users: Admin can see and manage all users; a User cannot reach it
 * at all. Fields are only Name, Email, Role, New Password, Confirm Password
 * — no Assigned Person and no admin re-authentication on this form, for
 * either role. Password rules: New Password + Confirm Password are required
 * and must match on create; both optional on edit (blank keeps the current
 * password), but Confirm is required and must match if New Password is set.
 *
 * `assigned_person_id` (used elsewhere by the reminder-assignment/visibility
 * scoping for existing seeded accounts) is untouched by this form: it's
 * simply never read from the request, so editing an account never changes
 * it and creating one always leaves it null.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function loginAs(string $name): User
    {
        return tap(User::where('name', $name)->firstOrFail(), fn ($user) => Sanctum::actingAs($user));
    }

    private function gokulId(): int
    {
        return User::where('name', 'Gokul')->value('id');
    }

    public function test_manage_users_requires_authentication(): void
    {
        $this->getJson('/api/manage-users')->assertUnauthorized();
    }

    public function test_a_user_cannot_reach_manage_users(): void
    {
        $this->loginAs('Sahana');
        $gokulId = $this->gokulId();

        $this->getJson('/api/manage-users')->assertForbidden();
        $this->postJson('/api/manage-users', [])->assertForbidden();
        $this->putJson("/api/manage-users/$gokulId", [])->assertForbidden();
        $this->deleteJson("/api/manage-users/$gokulId")->assertForbidden();
    }

    public function test_admin_sees_every_user_with_role(): void
    {
        $this->loginAs('Lizy Reminder');

        $rows = collect($this->getJson('/api/manage-users')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(20, $rows->count()); // 16 staff + Lizy Reminder + Manikandan/Pavithran/Yasmin
        $this->assertSame('user', $rows['Sahana']['role']);
        $this->assertSame('admin', $rows['Lizy Reminder']['role']);
    }

    public function test_creating_an_admin_works_without_an_assigned_person(): void
    {
        $this->loginAs('Lizy Reminder');

        $data = $this->postJson('/api/manage-users', [
            'name' => 'New Admin', 'email' => 'newadmin@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'admin',
        ])->assertCreated()->json('data');

        $this->assertSame('admin', $data['role']);
        $this->assertNull(User::where('email', 'newadmin@lizyweb.in')->value('assigned_person_id'));
    }

    public function test_creating_a_user_works_without_an_assigned_person(): void
    {
        $this->loginAs('Lizy Reminder');

        $data = $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'user',
        ])->assertCreated()->json('data');

        $this->assertSame('user', $data['role']);
        $this->assertNull(User::where('email', 'priya@lizyweb.in')->value('assigned_person_id'));
    }

    public function test_creating_a_user_ignores_a_submitted_assigned_person(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'user', 'assigned_person_id' => $this->gokulId(),
        ])->assertCreated();

        // The field isn't part of this form at all, so it's simply ignored, not used.
        $this->assertNull(User::where('email', 'priya@lizyweb.in')->value('assigned_person_id'));
    }

    public function test_creating_a_user_requires_all_the_usual_fields(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_creating_a_user_requires_new_password_and_confirm_password(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in', 'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_creating_a_user_fails_when_confirm_password_does_not_match(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'somethingelse',
            'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_creating_a_user_never_requires_an_admin_password(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'user',
        ])->assertCreated()->assertJsonMissingValidationErrors(['admin_password']);
    }

    public function test_creating_a_user_saves_the_hashed_password(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Priya', 'email' => 'priya@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'user',
        ])->assertCreated();

        $this->assertTrue(Hash::check('secret123', User::where('email', 'priya@lizyweb.in')->value('password')));
    }

    public function test_email_must_be_unique(): void
    {
        $this->loginAs('Lizy Reminder');

        $this->postJson('/api/manage-users', [
            'name' => 'Dup', 'email' => 'sahana@lizyweb.in',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_editing_a_user_without_a_password_keeps_the_existing_one(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();
        $originalHash = $sahana->password;

        $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email, 'role' => 'user',
        ])->assertOk();

        $this->assertSame($originalHash, $sahana->fresh()->password);
    }

    public function test_editing_a_user_with_a_new_password_requires_confirm_password_only(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();

        $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email, 'password' => 'newpassword1',
            'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['admin_password']);
    }

    public function test_editing_a_user_fails_when_confirm_password_does_not_match(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();

        $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email,
            'password' => 'newpassword1', 'password_confirmation' => 'doesnotmatch',
            'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_editing_a_user_with_a_new_password_changes_it_once_confirmed(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();

        $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email,
            'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
            'role' => 'user',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword1', $sahana->fresh()->password));
    }

    public function test_password_and_confirmation_are_not_saved_as_columns(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();

        $data = $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email,
            'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
            'role' => 'user',
        ])->assertOk()->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('password_confirmation', $data);
    }

    public function test_an_admin_can_change_their_own_password_without_re_entering_it(): void
    {
        $self = $this->loginAs('Lizy Reminder');

        $this->putJson("/api/manage-users/{$self->id}", [
            'name' => 'Lizy Reminder', 'email' => $self->email,
            'password' => 'brandnewpass', 'password_confirmation' => 'brandnewpass',
            'role' => 'admin',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnewpass', $self->fresh()->password));
    }

    public function test_changing_a_users_role_does_not_touch_their_assigned_person(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();
        $originalAssignedPersonId = $sahana->assigned_person_id;

        $data = $this->putJson("/api/manage-users/{$sahana->id}", [
            'name' => 'Sahana', 'email' => $sahana->email, 'role' => 'admin',
        ])->assertOk()->json('data');

        $this->assertSame('admin', $data['role']);
        // Not part of this form, so switching role leaves the existing link exactly as it was —
        // the reminder-assignment/visibility scoping for this account is unaffected.
        $this->assertSame($originalAssignedPersonId, $sahana->fresh()->assigned_person_id);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $self = $this->loginAs('Lizy Reminder');

        $this->deleteJson("/api/manage-users/{$self->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $self->id]);
    }

    public function test_cannot_delete_a_person_still_linked_as_someone_elses_assigned_person(): void
    {
        $this->loginAs('Lizy Reminder');
        $sahana = User::where('name', 'Sahana')->firstOrFail();

        // Sahana is her own Assigned Person (the seeded self-link).
        $this->deleteJson("/api/manage-users/{$sahana->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $sahana->id]);
    }

    public function test_admin_can_delete_an_unlinked_user(): void
    {
        $this->loginAs('Lizy Reminder');
        $priya = User::create([
            'name' => 'Priya', 'email' => 'priya@lizyweb.in', 'password' => 'secret123',
            'role' => 'user',
        ]);

        $this->deleteJson("/api/manage-users/{$priya->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $priya->id]);
    }
}
