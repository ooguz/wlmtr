<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\File;

class PhotoUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isWikimediaConnected();
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Bu işlemi yapmak için Wikimedia hesabınızla giriş yapmanız gerekiyor.',
            ], 403)
        );
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Lütfen tüm alanları doğru şekilde doldurun.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->min(10)
                    ->max(100 * 1024),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'date' => 'required|date',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:255',
            'monument_id' => 'required|exists:monuments,id',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Lütfen bir fotoğraf seçin.',
            'photo.image' => 'Yüklenen dosya bir görsel olmalıdır.',
            'photo.min' => 'Fotoğraf en az 10 KB olmalıdır.',
            'photo.max' => 'Fotoğraf en fazla 100 MB olabilir.',
            'title.required' => 'Fotoğraf başlığı zorunludur.',
            'title.max' => 'Başlık en fazla 255 karakter olabilir.',
            'description.max' => 'Açıklama en fazla 5000 karakter olabilir.',
            'date.required' => 'Fotoğraf tarihi zorunludur.',
            'date.date' => 'Geçerli bir tarih giriniz.',
            'categories.*.max' => 'Kategori adı en fazla 255 karakter olabilir.',
            'monument_id.required' => 'Anıt bilgisi eksik.',
            'monument_id.exists' => 'Geçersiz anıt.',
        ];
    }
}
