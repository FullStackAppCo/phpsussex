<?php

namespace Database\Seeders;

use App\Models\Settings\HomeHero;
use App\Models\Settings\ImageSetting;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Settings\NextEvent;
use App\Models\Settings\PortraitJoby;
use App\Models\Settings\PortraitYannick;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        HomeHero::firstOrCreate()
            ->addMedia(resource_path('images/hero.jpg'))
            ->preservingOriginal()
            ->toMediaCollection(ImageSetting::$collection);

        NextEvent::firstOrCreate([
            'value' => [
                'meetup_id' => null,
                'raw' => "We're currently arranging our next event. Join our Meetup group to stay updated!",
            ],
        ]);

        PortraitYannick::firstOrCreate()
            ->addMedia(resource_path('images/yannick.jpg'))
            ->preservingOriginal()
            ->toMediaCollection(ImageSetting::$collection);

        PortraitJoby::firstOrCreate()
            ->addMedia(resource_path('images/joby.jpg'))
            ->preservingOriginal()
            ->toMediaCollection(ImageSetting::$collection);
    }
}
