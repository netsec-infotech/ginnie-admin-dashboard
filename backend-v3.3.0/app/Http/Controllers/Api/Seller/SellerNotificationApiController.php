<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Http\Controllers\Api\NotificationApiController;
use Dedoc\Scramble\Attributes\Group;

#[Group('Seller Notifications')]
class SellerNotificationApiController extends NotificationApiController
{
    protected NotificationRoleTypeEnum $roleType = NotificationRoleTypeEnum::SELLER;
}
