<?php
namespace App\Data;

use DateTime;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class CreateExpenseData extends Data
{

    public function __construct(
        public string $name,
        public int $category_id,
        public float $amount,
        public float $amount_usd,
        public string $currency,
        public bool|null $has_invoice,
        public bool|null $is_deductible,
        public bool $iva = false,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public DateTime $expense_date,
        public int $user_id,
        public string $account,
        public ?string $status,
        public string $type_of_expense,
        public ?float $amount_bs = null,
        public ?float $conversion_rate = null,
        public ?float $exempt_amount = null,
        public ?float $taxable_base = null,
        public ?float $tax_amount = null,
        public ?float $exchange_rate = null,
        public ?float $total_usd = null,
        public float $total_amount,
    ) {
    }
}
