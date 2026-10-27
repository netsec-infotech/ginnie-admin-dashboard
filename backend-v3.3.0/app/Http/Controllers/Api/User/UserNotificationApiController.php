<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Http\Controllers\Api\NotificationApiController;
use Dedoc\Scramble\Attributes\Group;

#[Group('Notifications')]
class UserNotificationApiController extends NotificationApiController
{
    protected NotificationRoleTypeEnum $roleType = NotificationRoleTypeEnum::CUSTOMER;
}
