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
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'ci_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'rif' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'residence_letter' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'cv' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.file' => 'Debe subir un archivo de imagen',
            'photo.mimes' => 'El formato de la foto debe ser jpg, jpeg o png',
            'photo.max' => 'La imagen no debe pesar más de 10MB',
            'ci_file.file' => 'Debe subir un archivo',
            'ci_file.mimes' => 'La Cédula debe estar en formato PDF',
            'ci_file.max' => 'La Cédula no debe pesar más de 5MB',
            'rif.file' => 'Debe subir un archivo',
            'rif.mimes' => 'El RIF debe estar en formato PDF',
            'rif.max' => 'El RIF no debe pesar más de 5MB',
            'residence_letter.file' => 'Debe subir un archivo',
            'residence_letter.mimes' => 'La carta de residencia debe estar en formato PDF',
            'residence_letter.max' => 'La carta de residencia no debe pesar más de 5MB',
            'cv.file' => 'Debe subir un archivo',
            'cv.mimes' => 'El Currículum debe estar en formato PDF',
            'cv.max' => 'El Currículum no debe pesar más de 5MB',
        ];
    }
}
