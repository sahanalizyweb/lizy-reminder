<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single field change on a reminder, made after it was created (creation
 * itself is never logged — see ReminderChangeLogger). `previous_value` /
 * `new_value` / `changed_by_name` are the display strings as they were at
 * the moment of the change, kept even if the underlying record they came
 * from (a staff member, a category) is later renamed or deleted.
 */
#[Fillable(['reminder_id', 'field', 'field_label', 'previous_value', 'new_value', 'changed_by_id', 'changed_by_name'])]
class ReminderChange extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $change) => $change->created_at ??= now());
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
