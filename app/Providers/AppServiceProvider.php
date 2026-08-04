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
use App\Contracts\SocialBenefit;
use App\Contracts\Specialty;
use App\Contracts\Transaction;
use App\Contracts\Location as LocationContract;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\LaboratoryController;
use App\Http\Controllers\Api\LotteryController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ResignationController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Api\SocialBenefitController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\LocationController;
use App\Models\Payslip;
use App\Services\ClientServices;
use App\Services\CompanyServices;
use App\Services\DoctorServices;
use App\Services\LaboratoryServices;
use App\Services\LotteryServices;
use App\Services\ResignationServices;
use App\Contracts\Resignation;
use App\Contracts\ExchangeRate;
use App\Contracts\ExpenseCategory;
use App\Contracts\Expenses;
use App\Contracts\Order;
use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Contracts\Profitability;
use App\Contracts\User;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpensesController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfitabilityController;
use App\Http\Controllers\Api\SupplierIaAssistantReportController;
use App\Http\Controllers\Api\SuppliersIaOrderAssistantController;
use App\Http\Controllers\Api\UserController;
use App\Services\AutoOrderServices;
use App\Services\ExchangeRateServices;
use App\Services\ExpenseCategoryServices;
use App\Services\UserServices;
use App\Services\ExpensesServices;
use App\Services\OrderServices;
use App\Services\ProductServices;
use App\Services\ProductSupplierServices;
use App\Services\ProfitabilityServices;
use App\Services\PurchaseOrderServices;
use App\Services\RoleServices;
use App\Services\PayslipServices;
use App\Services\SocialBenefitServices;
use App\Services\SpecialtyServices;
use App\Services\TransactionServices;
use App\Services\LocationServices;
use App\Contracts\Accounting\BalanceRepositoryInterface;
use App\Repositories\Accounting\BalanceRepository;
use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\LocationRepository;
use App\Http\Controllers\Api\Accounting\BalanceController;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(\App\Http\Controllers\Api\ProductFailureController::class)
            ->needs(\App\Contracts\ProductFailure::class)
            ->give(\App\Services\ProductFailureServices::class);

        $this->app->when(ProfitabilityController::class)
            ->needs(Profitability::class)
            ->give(ProfitabilityServices::class);

        $this->app->when(ExchangeRateController::class)
            ->needs(ExchangeRate::class)
            ->give(ExchangeRateServices::class);
        $this->app->when(ClientController::class)
            ->needs(Client::class)
            ->give(ClientServices::class);

        $this->app->when(OrderController::class)
            ->needs(Client::class)
            ->give(ClientServices::class);

        $this->app->when(CompanyController::class)
            ->needs(Company::class)
            ->give(CompanyServices::class);

        $this->app->when(CompanyController::class)
            ->needs(Client::class)
            ->give(ClientServices::class);



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

        $this->app->when(\App\Services\Reports\IaAssistantReportService::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(\App\Services\Reports\IaAssistantReportService::class)
            ->needs(ProductSupplier::class)
            ->give(ProductSupplierServices::class);

        $this->app->when(UserController::class)
            ->needs(User::class)
            ->give(UserServices::class);

        $this->app->when(InventoryStockController::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(\App\Services\InventoryStockService::class)
            ->needs(Product::class)
            ->give(ProductServices::class);

        $this->app->when(TransactionController::class)
            ->needs(Transaction::class)
            ->give(TransactionServices::class);

        $this->app->when(RoleController::class)
            ->needs(Role::class)
            ->give(RoleServices::class);

        $this->app->when(ResignationController::class)
            ->needs(Resignation::class)
            ->give(ResignationServices::class);

        $this->app->when(PayslipController::class)
            ->needs(Payslip::class)
            ->give(PayslipServices::class);

        $this->app->when(SocialBenefitController::class)
            ->needs(SocialBenefit::class)
            ->give(SocialBenefitServices::class);

        $this->app->bind(Doctor::class, DoctorServices::class);
        $this->app->bind(Specialty::class, SpecialtyServices::class);
        $this->app->bind(Transaction::class, TransactionServices::class);
        $this->app->bind(Lottery::class, LotteryServices::class);

        $this->app->bind(
            BalanceRepositoryInterface::class,
            BalanceRepository::class
        );

        $this->app->bind(
            SupplierRepositoryInterface::class,
            SupplierRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\MarketOpportunityRepositoryInterface::class,
            \App\Repositories\MarketOpportunityRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\AbcReportRepositoryInterface::class,
            \App\Repositories\AbcReportRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\SkuReportRepositoryInterface::class,
            \App\Repositories\SkuReportRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ProductMasterReportRepositoryInterface::class,
            \App\Repositories\ProductMasterReportRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ExpiryReportRepositoryInterface::class,
            \App\Repositories\ExpiryReportRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\GeneralSettingRepositoryInterface::class,
            \App\Repositories\Eloquent\GeneralSettingRepository::class
        );

        $this->app->bind(LocationContract::class, LocationRepository::class);



        $this->app->bind(
            \App\Contracts\CustomerAnalytics::class,
            \App\Repositories\Bi\CustomerAnalyticsRepository::class
        );

        $this->app->bind(
            \App\Contracts\EmployeeAnalytics::class,
            \App\Repositories\Bi\EmployeeAnalyticsRepository::class
        );

        $this->app->bind(Expenses::class, ExpensesServices::class);
        $this->app->bind(ExpenseCategory::class, ExpenseCategoryServices::class);


        $this->app->bind(
            \App\Contracts\ProductVariantRepositoryInterface::class,
            \App\Repositories\EloquentProductVariantRepository::class
        );

        $this->app->bind(
            \App\Contracts\Fiscal\FiscalCommandRepositoryInterface::class,
            \App\Repositories\Fiscal\FiscalCommandRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\GeneralPromotionRepositoryInterface::class,
            \App\Repositories\GeneralPromotionRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\IndividualOfferRepositoryInterface::class,
            \App\Repositories\IndividualOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\CategoryOfferRepositoryInterface::class,
            \App\Repositories\CategoryOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ProductPackRepositoryInterface::class,
            \App\Repositories\ProductPackRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\CompanyOfferRepositoryInterface::class,
            \App\Repositories\CompanyOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\DoctorOfferRepositoryInterface::class,
            \App\Repositories\DoctorOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\PrescriptionOfferRepositoryInterface::class,
            \App\Repositories\PrescriptionOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ExpirationOfferRepositoryInterface::class,
            \App\Repositories\ExpirationOfferRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\FinancialStatementRepositoryInterface::class,
            \App\Repositories\FinancialStatementRepository::class
        );

        $this->app->when(LocationController::class)
            ->needs(LocationContract::class)
            ->give(LocationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS en todas las URLs generadas por Laravel
        if ($this->app->environment('production') || str_starts_with(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }

        // Configurar rate limiter para la API (1000 peticiones por minuto para TPV y uso intensivo)
        RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });

        // Configurar rate limiter restrictivo para login (5 intentos por minuto por IP)
        RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
