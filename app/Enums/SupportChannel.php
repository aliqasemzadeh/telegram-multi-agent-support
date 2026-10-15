<?php

namespace App\Enums;

enum SupportChannel: string
{
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Phone => __('app.channel.phone'),
        };
    }
}
