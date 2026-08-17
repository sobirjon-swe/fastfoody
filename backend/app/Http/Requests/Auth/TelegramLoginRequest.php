<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TelegramLoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Telegram bergan satr oʻzgartirilmasdan yuboriladi.
            'init_data' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
