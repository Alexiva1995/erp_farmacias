<?php

namespace App\Providers;

use App\Contracts\AutoOrder;
use App\Contracts\Client;
use App\Contracts\Company;
use App\Contracts\Doctor;
use App\Contracts\Laboratory;
use App\Contracts\Lottery;
use App\Contracts\PurchaseOrder;
use App\Contracts\Role;
use App\Contracts\Transaction;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\LaboratoryController;
use App\Http\Controllers\Api\LotteryController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Services\ClientServices;
use App\Services\CompanyServices;
use App\Services\DoctorServices;
use App\Services\LaboratoryServices;
use App\Services\LotteryServices;
use App\Contracts\ExchangeRate;
use App\Contracts\Order;
use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Contracts\Profitability;
use App\Http\Controllers\api\ExchangeRateController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfitabilityController;
use App\Http\Controllers\Api\
  ;
use App\Http\Controllers\Api\SuppliersIaOrderAssistantController;
use App\Services\AutoOrderServices;
use App\Services\ExchangeRateServices;
use App\Services\OrderServices;
use App\Services\ProductServices;

use App\Services\ProductSupplierServices;
use App\Services\ProfitabilityServices;
use App\Services\PurchaseOrderServices;
use App\Services\RoleServices;
use App\Services\TransactionServices;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(ProfitabilityController::class)
            ->needs(Profitability::class)
            ->give(ProfitabilityServices::class);

        $this->app->when(ExchangeRateController::class)
            ->needs(ExchangeRate::class)
            ->give(ExchangeRateServices::class);
        $this->app->when(ClientController::class)
            ->needs(Client::class)
            ->give(ClientServices::class);

        $this->app->when(OrderController::class) // Cuando el OrderController
            ->needs(Client::class)               // necesite una instancia de Client
            ->give(ClientServices::class);

        $this->app->when(CompanyController::class)
            ->needs(Company::class)
            ->give(CompanyServices::class);


        $this->app->when(DoctorController::class)
            ->needs(Doctor::class)
            ->give(DoctorServices::class);

        $this->app->when(LotteryController::class)
            ->needs(Lottery::class)
            ->give(LotteryServices::class);

        $this->app->when(LaboratoryController::class)
            ->needs(Laboratory::class)
            ->give(LaboratoryServices::class);

        $this->app->when(OrderController::class)
            ->needs(Order::class)
            ->give(OrderServices::class);

        $this->app->when(SuppliersIaOrderAssistantController::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(SuppliersIaOrderAssistantController::class)
            ->needs(ProductSupplier::class)
            ->give(ProductSupplierServices::class);

        $this->app->when(SuppliersIaOrderAssistantController::class)
            ->needs(AutoOrder::class)
            ->give(AutoOrderServices::class);

        $this->app->when(PurchaseOrderController::class)
            ->needs(PurchaseOrder::class)
            ->give(PurchaseOrderServices::class);
      
        $this->app->when(SupplierIaAssistantReportController::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(InventoryStockController::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(TransactionController::class)
            ->needs(Transaction::class)
            ->give(TransactionServices::class);

        $this->app->when(RoleController::class)
            ->needs(Role::class)
            ->give(RoleServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
