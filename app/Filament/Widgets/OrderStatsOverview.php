<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\StaffRole;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        /** @var \App\Models\Staff|null $staff */
        $staff = Filament::auth()->user();

        return in_array($staff?->role, [StaffRole::SuperAdmin, StaffRole::EventManager, StaffRole::Support], true);
    }

    protected function getStats(): array
    {
        $totalOrders = Order::query()->count();
        $paidOrders = Order::query()->where('status', OrderStatus::Paid)->count();
        $revenue = Order::query()->where('status', OrderStatus::Paid)->sum('total_amount');
        $pendingOrders = Order::query()->where('status', OrderStatus::Pending)->count();

        return [
            Stat::make('Total Orders', $totalOrders),
            Stat::make('Paid Orders', $paidOrders),
            Stat::make('Revenue (MZN)', number_format((float) $revenue, 2)),
            Stat::make('Pending Orders', $pendingOrders),
        ];
    }
}
