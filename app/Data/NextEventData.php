<?php

namespace App\Data;

use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class NextEventData extends Data
{
    public function __construct(
        public ?string $meetup_id,
        #[Max(5000)]
        public string $raw,
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
