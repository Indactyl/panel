<?php

namespace DASHDACTYL\Http\Requests\Api\Client\Servers\Schedules;

use DASHDACTYL\Models\Permission;

class DeleteScheduleRequest extends ViewScheduleRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SCHEDULE_DELETE;
    }
}