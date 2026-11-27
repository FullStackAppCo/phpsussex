<?php

namespace App\Console\Commands;

use App\Data\NextEventData;
use App\Models\Settings\NextEvent;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\XPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

#[Signature('app:sync-next-event')]
#[Description('Fetch the next upcoming Meetup event and save it to the next event setting')]
class SyncNextEvent extends Command
{
    private string $eventsPath;

    public function handle(
        Browsershot $browser,
        #[Config('services.meetup.group')]
        string $group,
    ): int {
        $setting = NextEvent::firstOrFail();
        $this->eventsPath = "/$group/events/";

        $html = $browser->setUrl("https://www.meetup.com{$this->eventsPath}?type=upcoming")
            ->waitUntilNetworkIdle()
            ->bodyHtml();

        $event = $this->firstEvent($html);

        if ($event === null) {
            $this->info('No upcoming event found.');

            return self::SUCCESS;
        }

        $description = Str::of($event['description'])->trim()->limit(5000, '');

        if ($description->isEmpty()) {
            $this->error("No description found for event {$event['id']}.");

            return self::FAILURE;
        }

        $setting->forceFill([
            'value' => new NextEventData(meetup_id: $event['id'], raw: $description->value()),
        ])->save();

        $this->info("Saved next event {$event['id']}.");

        return self::SUCCESS;
    }

    /**
     * Finds the first event card on the listing that links to an event in the group.
     *
     * @return array{id: string, description: string}|null
     */
    private function firstEvent(string $html): ?array
    {
        $xpath = new XPath(HTMLDocument::createFromString($html, LIBXML_NOERROR));
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        $eventsUrl = "https://www.meetup.com{$this->eventsPath}";
        $card = "*[@data-testid='group-events-card']";

        $link = $xpath->query("//{$card}//h:a[starts-with(@href, '{$eventsUrl}')]")->item(0);

        if (! $link instanceof Element) {
            return null;
        }

        $id = Str::of($link->getAttribute('href'))->after($eventsUrl)->match('/^\d+/')->value();

        if ($id === '') {
            return null;
        }

        $description = collect($xpath->query("ancestor::{$card}[1]//h:p", $link))
            ->map(fn (Node $paragraph): string => trim($paragraph->textContent ?? ''))
            ->implode("\n\n");

        return ['id' => $id, 'description' => $description];
    }
}
