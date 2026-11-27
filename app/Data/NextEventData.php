<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class NextEventData extends Data
{
    public function __construct(
        public ?string $meetup_id,
        #[Max(5000)]
        public string $raw,
        public ?CarbonImmutable $starts_at = null,
    ) {
        //
    }

    /**
     * @return list<string>
     */
    public function paragraphs(): array
    {
        return Str::of($this->raw)
            ->trim()
            ->split('/\R\s*\R/')
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->all();
    }
}
