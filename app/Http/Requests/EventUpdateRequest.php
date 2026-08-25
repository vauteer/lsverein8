<?php

namespace App\Http\Requests;

use App\Concerns\EventValidationRules;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventUpdateRequest extends FormRequest
{
    use EventValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return $this->eventRules($event->id);
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
