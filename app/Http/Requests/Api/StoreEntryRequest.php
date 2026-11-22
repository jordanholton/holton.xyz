<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50', 'distinct'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $tags = $this->validated('tags', []);

        // Re-index in case the client sent a JSON object instead of an array.
        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }
}
