<?php

namespace IstvanMolitor\ArticleScraper\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapeAndSaveArticleRequest extends FormRequest
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
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'publish' => ['nullable', 'boolean'],
            'layout' => ['nullable', 'string', 'max:64'],
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
            'language_id.integer' => 'A nyelv azonositojanak egesz szamnak kell lennie.',
            'language_id.exists' => 'A megadott nyelv nem letezik.',
            'publish.boolean' => 'A publikalas mezo csak igaz vagy hamis ertek lehet.',
            'layout.string' => 'A layout mezo karakterlancnak kell lennie.',
        ];
    }
}
