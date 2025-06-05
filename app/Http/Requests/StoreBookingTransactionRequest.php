<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'post_code' => 'required|string|max:255',
            'started_time' => 'required|date_format:H:i',
            'schedule_at' => 'required|date_format:Y-m-d', // Added schedule_at as it's in the form
            'proof' => 'required|file|mimes:jpg,jpeg,png|max:2048', // Corrected image extension
            'service_ids' => 'required|array', // This seems to be the array of selected service IDs
            'service_ids.*' => 'integer|exists:home_services,id', // Validates each item in the array
        ];
    }
}
