<?php


namespace App\Services;

use App\Contracts\SocialBenefit;
use App\Models\Employee;
use App\Repository\SocialBenefitRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SocialBenefitServices implements SocialBenefit
{

  public function __construct(
    protected SocialBenefitRepository $socialBenefitRepository
  ) {
  }

  public function index(array $data): LengthAwarePaginator
  {
    return $this->socialBenefitRepository->index($data);
  }

  public function payment(Employee $employee, array $data): bool
  {
    return $this->socialBenefitRepository->payment($employee, $data);
  }

  public function getSettlementData(Employee $employee, array $overrides = []): array
  {
    return $this->socialBenefitRepository->getSettlementData($employee, $overrides);
  }

  public function fire(Employee $employee, array $data): bool
  {
    return $this->socialBenefitRepository->fire($employee, $data);
  }

  public function generatePdf(Employee $employee, array $overrides = []): \Barryvdh\DomPDF\PDF
  {
    return $this->socialBenefitRepository->generatePdf($employee, $overrides);
  }
}
