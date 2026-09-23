<?php

namespace App\Http\Resources;

use App\Models\ReminderChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReminderChange
 */
class ReminderChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field' => $this->field,
            'field_label' => $this->field_label,
            'previous_value' => $this->previous_value,
            'new_value' => $this->new_value,
            'changed_by_name' => $this->changed_by_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
