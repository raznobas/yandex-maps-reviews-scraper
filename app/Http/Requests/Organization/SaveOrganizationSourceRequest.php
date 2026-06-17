<?php

namespace App\Http\Requests\Organization;

use App\Services\YandexOrganizationSourceUrlResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveOrganizationSourceRequest extends FormRequest
{
    private ?string $normalized = null;

    private ?string $yandexId = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('source_url')) {
            $this->merge([
                'source_url' => trim($this->source_url),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'source_url' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_url.required' => 'Вставьте ссылку на карточку организации.',
            'source_url.string' => 'Ссылка должна быть строкой.',
            'source_url.max' => 'Ссылка слишком длинная.',
        ];
    }

    public function after(YandexOrganizationSourceUrlResolver $resolver): array
    {
        return [
            function (Validator $validator) use ($resolver) {
                if ($validator->errors()->has('source_url')) {
                    return;
                }

                $source = $resolver->resolve((string) $this->input('source_url'));

                if (($source['error'] ?? null) === 'invalid_url') {
                    $validator->errors()->add('source_url', 'Введите корректную ссылку на организацию в Яндекс.Картах.');

                    return;
                }

                if (($source['error'] ?? null) === 'invalid_domain') {
                    $validator->errors()->add('source_url', 'Ссылка должна вести на yandex.ru или yandex.com.');

                    return;
                }

                if (($source['error'] ?? null) === 'invalid_path') {
                    $validator->errors()->add('source_url', 'Ссылка должна вести на карточку организации в Яндекс.Картах.');

                    return;
                }

                $this->normalized = $source['normalized_url'];
                $this->yandexId = $source['yandex_organization_id'];
            },
        ];
    }

    public function normalizedUrl(): string
    {
        return $this->normalized;
    }

    public function yandexOrganizationId(): string
    {
        return $this->yandexId;
    }
}
