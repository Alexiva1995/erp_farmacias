<?php

namespace App\Observers;

use App\Models\ExpiredLog;

class ExpiredLogObserver
{
    /**
     * 
     */
    public function created(ExpiredLog $expiredLog): void
    {
        ProductObserver::handleExpiredProduct($expiredLog);
    }
}
