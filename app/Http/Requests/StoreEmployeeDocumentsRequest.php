<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeDocumentsRequest extends FormRequest
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
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'rif' => ['nullable', 'file', 'mimes:pdf'],
            'residence_letter' => ['nullable', 'file', 'mimes:pdf'],
            'cv' => ['nullable', 'file', 'mimes:pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.file' => 'Debe subir un archivo',
            'photo.mimes' => 'El formato debe ser jpg, jpeg o png',
            'photo.max' => 'La imagen no debe pesar más de 2MB',
            'rif.file' => 'Debe subir un archivo',
            'rif.mimes' => 'El formato debe ser pdf',
            'residence_letter.file' => 'Debe subir un archivo',
            'residence_letter.mimes' => 'El formato debe ser pdf',
            'cv.file' => 'Debe subir un archivo',
            'cv.mimes' => 'El formato debe ser pdf',
        ];
    }
}
