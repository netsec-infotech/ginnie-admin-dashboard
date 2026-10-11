<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Http\Controllers\Api\NotificationApiController;
use Dedoc\Scramble\Attributes\Group;

#[Group('Notifications')]
class DeliveryBoyNotificationApiController extends NotificationApiController
{
    protected NotificationRoleTypeEnum $roleType = NotificationRoleTypeEnum::RIDER;
}
