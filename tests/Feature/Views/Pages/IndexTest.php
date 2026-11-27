<?php

namespace Tests\Feature\Views\Pages;

use App\Data\NextEventData;
use App\Models\Settings\NextEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('public');
        $this->seed();
    }

    private function nextEventBlurb(string $blurb, ?string $meetupId = null): void
    {
        NextEvent::query()->firstOrFail()->forceFill([
            'value' => new NextEventData(meetup_id: $meetupId, raw: $blurb),
        ])->save();
    }

    #[Test]
    public function it_renders_the_page(): void
    {
        $this->get('/')->assertOk();
    }

    #[Test]
    public function it_splits_the_next_event_blurb_on_blank_lines(): void
    {
        $this->nextEventBlurb("First paragraph.\n\nSecond paragraph.\n\n\nThird paragraph.");

        $this->get('/')
            ->assertSee('>First paragraph.</p>', escape: false)
            ->assertSee('>Second paragraph.</p>', escape: false)
            ->assertSee('>Third paragraph.</p>', escape: false)
            ->assertSeeInOrder(['First paragraph.', 'Second paragraph.', 'Third paragraph.']);
    }

    #[Test]
    public function it_splits_on_blank_lines_containing_only_whitespace(): void
    {
        $this->nextEventBlurb("First paragraph.\n \t \nSecond paragraph.");

        $this->get('/')
            ->assertSee('>First paragraph.</p>', escape: false)
            ->assertSee('>Second paragraph.</p>', escape: false);
    }

    #[Test]
    public function it_splits_on_windows_line_endings(): void
    {
        $this->nextEventBlurb("First paragraph.\r\n\r\nSecond paragraph.");

        $this->get('/')
            ->assertSee('>First paragraph.</p>', escape: false)
            ->assertSee('>Second paragraph.</p>', escape: false);
    }

    #[Test]
    public function it_trims_the_blurb_and_each_paragraph(): void
    {
        $this->nextEventBlurb("\n\n   First paragraph.   \n\n   Second paragraph.   \n\n");

        $this->get('/')
            ->assertSee('>First paragraph.</p>', escape: false)
            ->assertSee('>Second paragraph.</p>', escape: false);
    }

    #[Test]
    public function it_keeps_single_line_breaks_within_a_paragraph(): void
    {
        $this->nextEventBlurb("First line\nsecond line.");

        $this->get('/')->assertSee(">First line\nsecond line.</p>", escape: false);
    }

    #[Test]
    public function it_escapes_the_blurb(): void
    {
        $this->nextEventBlurb('Pizza & <b>drinks</b>');

        $this->get('/')
            ->assertSee('>Pizza &amp; &lt;b&gt;drinks&lt;/b&gt;</p>', escape: false)
            ->assertDontSee('<b>drinks</b>', escape: false);
    }

    #[Test]
    public function it_links_to_the_meetup_group_when_there_is_no_event(): void
    {
        $this->nextEventBlurb('Next event coming soon.');

        $this->get('/')
            ->assertSee('data-event="meetup group click"', escape: false)
            ->assertDontSee('data-event="meetup event click"', escape: false)
            ->assertDontSee('https://www.meetup.com/php-sussex/events/', escape: false);
    }

    #[Test]
    public function it_links_to_the_meetup_event_when_there_is_an_event(): void
    {
        $this->nextEventBlurb('Join us for our next event.', meetupId: '317013388');

        $this->get('/')
            ->assertSee('data-event="meetup event click"', escape: false)
            ->assertSee('href="https://www.meetup.com/php-sussex/events/317013388/"', escape: false)
            ->assertDontSee('data-event="meetup group click"', escape: false);
    }
}
