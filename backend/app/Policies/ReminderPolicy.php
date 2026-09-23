<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

/**
 * Admins can view/update/delete any reminder. A User can only do so for a
 * reminder assigned to their linked Assigned Person (see User::assignedPerson).
 */
class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        return $this->ownsOrAdmin($user, $reminder);
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->ownsOrAdmin($user, $reminder);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->ownsOrAdmin($user, $reminder);
    }

    private function ownsOrAdmin(User $user, Reminder $reminder): bool
    {
        return $user->isAdmin() || (
            $reminder->assigned_to !== null && $reminder->assigned_to === $user->assigned_person_id
        );
    }
}
