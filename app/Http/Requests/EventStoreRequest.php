<?php

namespace App\Http\Requests;

use App\Concerns\EventValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventStoreRequest extends FormRequest
{
    use EventValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->eventRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->eventMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->eventAttributes();
    }
}
