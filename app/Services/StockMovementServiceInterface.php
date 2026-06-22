<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ServiceJob;
use App\Models\StockMovement;

interface StockMovementServiceInterface
{
    /**
     */
    public function getCurrentStock(int $shopId, int $itemId): int;

    /**
     */
    public function createManualMovement(array $data): StockMovement;

    /**
     */
    public function recordSaleMovementsForInvoice(Invoice $invoice): void;

    /**
     */
    public function recordConsumptionForServiceJob(ServiceJob $serviceJob): void;
}
