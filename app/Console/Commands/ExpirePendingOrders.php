<?php

namespace App\Console\Commands;

use App\Actions\Checkout\ExpirePendingOrdersAction;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Expire pending orders older than 24h and release their reserved ticket-type quantities';

    public function handle(ExpirePendingOrdersAction $action): int
    {
        $count = $action->handle();

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
