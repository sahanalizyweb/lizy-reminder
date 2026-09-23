<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks that a user has opened the notification-bell alert for a reminder
 * due on a given Reminder Date. See NotificationController — a reminder due
 * today is excluded from the bell (count and list) once a matching row
 * exists here for the signed-in user and today's date.
 */
#[Fillable(['user_id', 'reminder_id', 'reminder_date', 'read_at'])]
class NotificationRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $read) => $read->read_at ??= now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
