<?php


namespace App\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UploadFileInvoiceExpenseData extends Data
{
    public function __construct(
        public int $id,
        public UploadedFile $file_invoice,
    ) {}
}
