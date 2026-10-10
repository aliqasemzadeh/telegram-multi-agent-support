<?php

namespace App\Enums;

enum ScheduleSlotSource: string
{
    case Self = 'self';
    case Admin = 'admin';
}
