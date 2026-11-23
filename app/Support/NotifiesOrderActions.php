<?php

namespace App\Support;

use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\OrderActionNotification;
use Illuminate\Support\Facades\Notification;

trait NotifiesOrderActions
{
    /**
     * Staff actions go to admins; admin/manager actions go back to staff.
     */
    private function notifyOppositeRole(
        string $module,
        string $orderNumber,
        string $action,
        User $actor,
        ?string $url = null,
    ): void {
        $roles = $actor->isStaff()
            ? [User::ROLE_ADMIN]
            : [User::ROLE_STAFF];

        $recipients = User::query()
            ->active()
            ->whereIn('role', $roles)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new OrderActionNotification(
            module: $module,
            orderNumber: $orderNumber,
            action: $action,
            actor: $actor,
            url: $url,
        ));
    }

    /**
     * Admin decisions are personal: notify the staff member who created the
     * sales order instead of broadcasting the result to every staff account.
     */
    private function notifyOrderCreator(
        SalesOrder $order,
        string $action,
        User $actor,
        ?string $url = null,
    ): void {
        $creator = $order->user;

        if ($creator === null || $creator->id === $actor->id || ! $creator->isStaff() || ! $creator->isActive()) {
            return;
        }

        Notification::send($creator, new OrderActionNotification(
            module: 'Sales order',
            orderNumber: $order->order_number,
            action: $action,
            actor: $actor,
            url: $url,
            forCreator: true,
        ));
    }
}
