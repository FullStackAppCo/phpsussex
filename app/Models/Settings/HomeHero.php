<?php

namespace App\Models\Settings;

use Database\Factories\Settings\HomeHeroFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HomeHero extends Setting implements HasMedia
{
    /** @use HasFactory<HomeHeroFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('pixellated')
            ->performOnCollections('image')
            ->withResponsiveImages()
            ->nonQueued()
            ->pixelate(3);
    }

    public function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getFirstMedia('image'),
        );
    }
}
