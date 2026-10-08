<?php

namespace App\Observers;

use App\Models\IphoneTransfer;
use App\Services\FcmService;

class IphoneTransferObserver
{
    /**
     * Handle the IphoneTransfer "created" event.
     */
    public function created(IphoneTransfer $transfer): void
    {
        try {
            $transfer->loadMissing(['iphone', 'toAffiliate', 'fromAffiliate']);
            app(FcmService::class)->notifyIphoneTransfer($transfer);
        } catch (\Throwable $e) {
            logger()->error('FCM iPhone transfer notification failed: ' . $e->getMessage());
        }
    }
}
