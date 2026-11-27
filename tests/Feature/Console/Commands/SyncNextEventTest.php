<?php

namespace Tests\Feature\Console\Commands;

use App\Data\NextEventData;
use App\Models\Settings\NextEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

final class SyncNextEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.meetup.group' => 'example-group']);

        (new NextEvent)->forceFill([
            'value' => new NextEventData(meetup_id: null, raw: 'Coming soon.'),
        ])->save();
    }

    private function mockBrowsershot(string $cards): void
    {
        $this->mock(Browsershot::class, function (MockInterface $mock) use ($cards) {
            $mock->shouldReceive('setUrl')->once()->with('https://www.meetup.com/example-group/events/?type=upcoming')->andReturnSelf();
            $mock->shouldReceive('waitUntilNetworkIdle')->andReturnSelf();
            $mock->shouldReceive('bodyHtml')->once()->andReturn("<html><body><main>{$cards}</main></body></html>");
        });
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function card(string $href, array $paragraphs, ?string $datetime = '2026-09-09T18:30:00+01:00[Europe/London]'): string
    {
        $time = $datetime === null ? '' : "<time datetime=\"{$datetime}\">Wed, Sep 9 · 6:30 PM BST</time>";
        $paragraphs = implode('', array_map(fn (string $paragraph) => "<p class=\"mb-ds2-10\">{$paragraph}</p>", $paragraphs));

        return <<<HTML
            <div data-testid="group-events-card">
                <a href="{$href}" data-event-label="Event Card"><h3>Event title</h3>{$time}</a>
                <div class="line-clamp-3">{$paragraphs}</div>
            </div>
            HTML;
    }

    #[Test]
    public function it_saves_the_next_event(): void
    {
        $this->mockBrowsershot(
            $this->card('https://www.meetup.com/example-group/events/123456/?eventOrigin=group_events_list', [
                '  Join us!  ',
                'Pizza &amp; <a href="https://example.com">drinks</a>.',
            ]).
            $this->card('https://www.meetup.com/example-group/events/789/', ['Later event.']),
        );

        $this->artisan('app:sync-next-event')
            ->expectsOutput('Saved next event 123456.')
            ->assertSuccessful();

        $value = NextEvent::query()->sole()->value;
        $this->assertSame('123456', $value->meetup_id);
        $this->assertSame("Join us!\n\nPizza & drinks.", $value->raw);
        $this->assertSame('2026-09-09T18:30:00+01:00', $value->starts_at?->toIso8601String());
    }

    #[Test]
    public function it_saves_the_start_time_of_a_gmt_event(): void
    {
        $this->mockBrowsershot($this->card('https://www.meetup.com/example-group/events/123456/', ['Join us!'], '2026-11-11T18:30:00Z[Europe/London]'));

        $this->artisan('app:sync-next-event')->assertSuccessful();

        $this->assertSame('2026-11-11T18:30:00+00:00', NextEvent::query()->sole()->value->starts_at?->toIso8601String());
    }

    #[Test]
    public function it_saves_without_a_start_time_when_the_card_has_none(): void
    {
        $this->mockBrowsershot($this->card('https://www.meetup.com/example-group/events/123456/', ['Join us!'], null));

        $this->artisan('app:sync-next-event')->assertSuccessful();

        $value = NextEvent::query()->sole()->value;
        $this->assertSame('123456', $value->meetup_id);
        $this->assertNull($value->starts_at);
    }

    #[Test]
    public function it_saves_without_a_start_time_when_the_datetime_is_invalid(): void
    {
        $this->mockBrowsershot($this->card('https://www.meetup.com/example-group/events/123456/', ['Join us!'], 'not a date'));

        $this->artisan('app:sync-next-event')->assertSuccessful();

        $this->assertNull(NextEvent::query()->sole()->value->starts_at);
    }

    #[Test]
    public function it_ignores_cards_linking_to_other_groups(): void
    {
        $this->mockBrowsershot(
            $this->card('https://www.meetup.com/other-group/events/111/', ['Other group.']).
            $this->card('https://example.com/example-group/events/222/', ['Other site.']).
            $this->card('https://www.meetup.com/example-group/events/333/', ['Our event.']),
        );

        $this->artisan('app:sync-next-event')->assertSuccessful();

        $value = NextEvent::query()->sole()->value;
        $this->assertSame('333', $value->meetup_id);
        $this->assertSame('Our event.', $value->raw);
    }

    #[Test]
    public function it_leaves_the_setting_unchanged_when_there_is_no_upcoming_event(): void
    {
        $this->mockBrowsershot('');

        $this->artisan('app:sync-next-event')
            ->expectsOutput('No upcoming event found.')
            ->assertSuccessful();

        $value = NextEvent::query()->sole()->value;
        $this->assertNull($value->meetup_id);
        $this->assertSame('Coming soon.', $value->raw);
    }

    #[Test]
    public function it_fails_without_saving_when_the_description_is_missing(): void
    {
        $this->mockBrowsershot($this->card('https://www.meetup.com/example-group/events/123456/', []));

        $this->artisan('app:sync-next-event')
            ->expectsOutput('No description found for event 123456.')
            ->assertFailed();

        $this->assertSame('Coming soon.', NextEvent::query()->sole()->value->raw);
    }

    #[Test]
    public function it_throws_before_fetching_when_the_setting_is_missing(): void
    {
        NextEvent::query()->delete();
        $this->mock(Browsershot::class)->shouldNotReceive('setUrl');

        $this->expectException(ModelNotFoundException::class);

        $this->artisan('app:sync-next-event')->run();
    }

    #[Test]
    public function it_limits_the_description_to_5000_characters(): void
    {
        $this->mockBrowsershot($this->card('https://www.meetup.com/example-group/events/123456/', [str_repeat('a', 6000)]));

        $this->artisan('app:sync-next-event')->assertSuccessful();

        $this->assertSame(5000, mb_strlen(NextEvent::query()->sole()->value->raw));
    }
}
