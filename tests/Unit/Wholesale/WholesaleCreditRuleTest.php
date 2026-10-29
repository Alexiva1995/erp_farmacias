<?php

declare(strict_types=1);

namespace Tests\Unit\Wholesale;

use App\Models\Client;
use App\Models\Laboratory;
use App\Models\Product;
use App\Services\Credits\WholesaleCreditRuleService;
use PHPUnit\Framework\TestCase;

class WholesaleCreditRuleTest extends TestCase
{
    protected WholesaleCreditRuleService $ruleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleService = new WholesaleCreditRuleService();
    }

    public function test_product_credit_days_precedence_over_laboratory_and_client(): void
    {
        $client = new Client();
        $client->default_credit_days = 30;

        $laboratory = new Laboratory();
        $laboratory->default_credit_days = 15;

        $product = new Product();
        $product->credit_days = 7;
        $product->setRelation('laboratory', $laboratory);

        $resolvedDays = $this->ruleService->resolveProductCreditDays($product, $client);
        $this->assertEquals(7, $resolvedDays);
    }

    public function test_laboratory_credit_days_used_when_product_has_no_days(): void
    {
        $client = new Client();
        $client->default_credit_days = 30;

        $laboratory = new Laboratory();
        $laboratory->default_credit_days = 15;

        $product = new Product();
        $product->credit_days = null;
        $product->setRelation('laboratory', $laboratory);

        $resolvedDays = $this->ruleService->resolveProductCreditDays($product, $client);
        $this->assertEquals(15, $resolvedDays);
    }

    public function test_client_credit_days_used_when_product_and_lab_have_no_days(): void
    {
        $client = new Client();
        $client->default_credit_days = 21;

        $product = new Product();
        $product->credit_days = null;
        $product->setRelation('laboratory', null);

        $resolvedDays = $this->ruleService->resolveProductCreditDays($product, $client);
        $this->assertEquals(21, $resolvedDays);
    }

    public function test_zero_days_defaults_when_nothing_configured(): void
    {
        $client = new Client();
        $client->default_credit_days = 0;

        $product = new Product();
        $product->credit_days = null;
        $product->setRelation('laboratory', null);

        $resolvedDays = $this->ruleService->resolveProductCreditDays($product, $client);
        $this->assertEquals(0, $resolvedDays);
    }
}
