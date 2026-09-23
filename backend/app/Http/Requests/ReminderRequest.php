<?php

namespace App\Http\Requests;

use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating and updating a reminder. Which fields are required
 * depends on reminder_type: Product needs product_name / product_category_id
 * / quantity; IT Service needs website_link; Travel / Family Tour Booking
 * needs booking_name; Real Estate Marketing needs property_name / location.
 * The two latter types also require `status` (one of Reminder::MANUAL_STATUSES,
 * picked by hand) rather than it defaulting to Pending. Assigned Person may
 * be left as "Unassigned" (assigned_to null) — it is a deliberate choice,
 * not a gap.
 */
class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('reminder_type');
        $quantity = $this->input('quantity');
        $price = $this->input('price');
        $categoryId = $this->input('product_category_id');
        $assignedTo = $this->input('assigned_to');
        $website = trim((string) $this->input('website_link'));

        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')) ?: 'Not Provided',
            'phone' => trim((string) $this->input('phone')),
            'assigned_to' => $assignedTo === '' || $assignedTo === null ? null : $assignedTo,
            'product_name' => trim((string) $this->input('product_name')) ?: null,
            // Missing quantity on a Product is left null so the "required" rule catches it;
            // for any other type it defaults to 1, since the column itself is not nullable
            // and quantity isn't meaningful (or required) outside Product.
            'quantity' => ($quantity === null || $quantity === '') ? ($type === Reminder::TYPE_PRODUCT ? null : 1) : $quantity,
            'price' => ($price === null || $price === '') ? null : $price,
            'product_category_id' => ($categoryId === null || $categoryId === '') ? null : $categoryId,
            'website_link' => $website !== '' ? $website : null,
            'booking_name' => trim((string) $this->input('booking_name')) ?: null,
            'property_name' => trim((string) $this->input('property_name')) ?: null,
            'location' => trim((string) $this->input('location')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A reminder already saved under a since-removed type (e.g. legacy
        // "Hosting" records) keeps working: it can be resaved without being
        // forced into Product or IT Service.
        $allowedTypes = Reminder::TYPES;
        $current = $this->route('reminder');
        if ($current && ! in_array($current->reminder_type, $allowedTypes, true)) {
            $allowedTypes[] = $current->reminder_type;
        }

        $type = $this->input('reminder_type');
        $isProduct = $type === Reminder::TYPE_PRODUCT;
        $isItService = $type === Reminder::TYPE_IT_SERVICE;
        $isTravel = $type === Reminder::TYPE_TRAVEL;
        $isRealEstate = $type === Reminder::TYPE_REAL_ESTATE;
        $isManualStatus = $isTravel || $isRealEstate;

        return [
            'reminder_type' => ['required', Rule::in($allowedTypes)],
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-()]{4,}$/'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'reminder_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:scheduled_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => $isManualStatus
                ? ['required', Rule::in(Reminder::MANUAL_STATUSES)]
                : ['sometimes', Rule::in([Reminder::STATUS_PENDING, Reminder::STATUS_COMPLETED])],

            'product_name' => [Rule::requiredIf($isProduct), 'nullable', 'string', 'max:255'],
            'product_category_id' => [Rule::requiredIf($isProduct), 'nullable', 'integer', 'exists:product_categories,id'],
            'quantity' => [Rule::requiredIf($isProduct), 'nullable', 'integer', 'min:1', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'website_link' => [Rule::requiredIf($isItService), 'nullable', 'url:http,https', 'max:2048'],

            'booking_name' => [Rule::requiredIf($isTravel), 'nullable', 'string', 'max:255'],

            'property_name' => [Rule::requiredIf($isRealEstate), 'nullable', 'string', 'max:255'],
            'location' => [Rule::requiredIf($isRealEstate), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, optionally starting with + and country code).',
            'reminder_date.before_or_equal' => 'The reminder date must be on or before the scheduled / due date.',
            'product_name.required' => 'Enter the product name.',
            'product_category_id.required' => 'Choose a product category.',
            'quantity.required' => 'Enter the quantity.',
            'website_link.required' => 'Enter the website link.',
            'website_link.url' => 'Enter a valid URL, e.g. https://example.com.',
            'booking_name.required' => 'Enter the booking / tour name.',
            'property_name.required' => 'Enter the property / project name.',
            'location.required' => 'Enter the location.',
            'status.required' => 'Choose a status.',
        ];
    }
}
