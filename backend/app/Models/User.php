<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Users can log in. `is_staff` decides whether they also show up in the
 * "Assigned Person" lists (see StaffController): removing someone from the
 * assignable list doesn't touch their login or their history on past
 * reminders, it just leaves them out of `is_staff` accounts.
 *
 * `role` is separate from that: an Admin can see and manage every reminder
 * and every user account, and has no Assigned Person. A User is scoped to
 * exactly one Assigned Person (`assigned_person_id`, another row in this
 * table) — they only see reminders assigned to that person, and can never
 * reassign a reminder to anyone else (see ReminderPolicy / ReminderController).
 */
#[Fillable(['name', 'email', 'password', 'is_staff', 'role', 'assigned_person_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_USER];

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'assigned_to');
    }

    /** The staff member this User account is scoped to. Always null for Admins. */
    public function assignedPerson(): BelongsTo
    {
        return $this->belongsTo(self::class, 'assigned_person_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
        ];
    }
}
