<?php

namespace App\Enums;

enum SupportChannel: string
{
    case Phone = 'phone';
    case Chat = 'chat';

    public function label(): string
    {
        return match ($this) {
            self::Phone => __('app.channel.phone'),
            self::Chat => __('app.channel.chat'),
        };
    }
}
