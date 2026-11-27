<?php

namespace App\Models\Settings;

use App\Data\NextEventData;

class NextEvent extends Setting
{
    public function casts(): array
    {
        return [
            'value' => NextEventData::class,
        ];
    }
}
