<?php

namespace IstvanMolitor\ArticleScraper\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Az URL megadasa kotelezo.',
            'url.url' => 'A megadott URL formatuma ervenytelen.',
            'url.max' => 'A megadott URL tul hosszu.',
        ];
    }
}
