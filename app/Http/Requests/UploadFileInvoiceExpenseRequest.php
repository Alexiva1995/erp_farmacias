<?php

namespace App\Http\Requests;

use App\Data\UploadFileInvoiceExpenseData;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UploadFileInvoiceExpenseRequest extends FormRequest
{

    public UploadFileInvoiceExpenseData $data;

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
            "id"                     =>    "required|numeric|exists:expenses,id",
            "file_invoice"           =>    "required|file|mimes:jpg,jpeg,png",

        ];
    }

    public function messages(): array
    {
        return [
            // Reglas para 'id'
            'id.required' => 'El Id del Gasto es obligatoria.',
            'id.numeric' => 'El Id del Gasto debe ser un valor numérico.',
            'id.exists' => 'El Gasto seleccionado no es válida.',

            // Reglas para 'file'
            'file_invoice.required'     => 'El file invoice del Gasto es obligatoria.',
            'file_invoice.file_invoice' =>  'El file invoice del Gasto debe ser un valor del tipo File.',
            'file_invoice.mimes'        => 'Solo se aceptan los siguinetes tipo de archivos => jpg,jpeg,png .',



        ];
    }


    protected function failedValidation(Validator $validator): JsonResponse
    {
        $errors = $validator->errors();
        $response = ApiResponse::error("Error", 422, $errors);
        throw new HttpResponseException($response);
    }

    protected function passedValidation()
    {
        $this->data = UploadFileInvoiceExpenseData::from([
            "id"                      =>    $this->id,
            "file_invoice"            =>    $this->file_invoice,
        ]);
    }
}
