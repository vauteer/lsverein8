<?php

namespace App\Http\Requests;

use App\Concerns\ItemValidationRules;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ItemUpdateRequest extends FormRequest
{
    use ItemValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Item $item */
        $item = $this->route('item');

        return $this->itemRules($item->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->itemMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->itemAttributes();
    }
}
