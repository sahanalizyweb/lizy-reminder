<?php

namespace App\Http\Requests;

use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-string filters for the reminder list and dashboard summary.
 */
class ReminderFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['today', 'upcoming', 'overdue', 'completed'])],
            'search' => ['nullable', 'string', 'max:255'],
            'reminder_type' => ['nullable', Rule::in(Reminder::TYPES)],
            'assigned_to' => ['nullable', 'regex:/^(all|unassigned|\d+)$/'],
            'status' => ['nullable', Rule::in(Reminder::FILTER_STATUSES)],
            'range' => ['nullable', Rule::in(Reminder::RANGES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
