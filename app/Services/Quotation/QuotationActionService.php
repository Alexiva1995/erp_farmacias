<?php

namespace App\Services\Quotation;

use App\Models\Product;
use App\Services\Resources\ResourceService;

class QuotationActionService
{

    public function __construct(
        private ResourceService $resourceService,
    ) {
    }

    public function loadProductDetails(Product $product): Product
    {
        $product->load([
            'laboratory',
        ]);

        $product->loadSum('lots', 'quantity');
        return $product;
    }
}
