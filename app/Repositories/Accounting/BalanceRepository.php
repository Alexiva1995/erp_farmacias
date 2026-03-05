<?php

namespace App\Repositories\Accounting;

use App\Contracts\Accounting\BalanceRepositoryInterface;
use App\Models\Furniture;
use App\Models\Invoice;
use App\Models\Loan;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Furniture\FurnitureQueryService;
use App\Services\Invoices\InvoiceQueryService;
use App\Services\Loans\LoanQueryService;
use App\Services\Products\ProductQueryService;
use App\Contracts\Transaction as TransactionContract;

class BalanceRepository implements BalanceRepositoryInterface
{
    public function __construct(
        private ProductQueryService $productQueryService,
        private FurnitureQueryService $furnitureQueryService,
        private InvoiceQueryService $invoiceQueryService,
        private LoanQueryService $loanQueryService,
        private TransactionContract $transactionContract
    ) {}

    public function getAssets(): array
    {
        // 1. Efectivo (Saldo acumulado de todas las billeteras en USD)
        $walletsData = $this->transactionContract->getWallets([]);
        
        // 2. Inventario
        $inventoryValue = $this->productQueryService->calculateInventoryValue();

        // 3. Mobiliario (Valor Bruto)
        $furnitureBruto = Furniture::sum('cost');

        return [
            'cash' => $walletsData['total_usd'] ?? 0,
            'inventory' => $inventoryValue,
            'furniture_bruto' => $furnitureBruto,
        ];
    }

    public function getLiabilities(): array
    {
        // 1. Deudas con Proveedores
        $supplierDebts = $this->invoiceQueryService->calculateSupplierDebts();

        // 2. Préstamos
        $loansBalance = $this->loanQueryService->calculateTotalBalance();

        return [
            'supplier_debts' => $supplierDebts,
            'loans' => $loansBalance,
        ];
    }

    public function getDepreciation(): float
    {
        return $this->furnitureQueryService->calculateTotalDepreciation();
    }
}
