<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Campaign;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Campaign\CampaignBand;
use Pixonaut\Shop\Tests\TestCase;

/**
 * The campaign band the `pixonaut_shop_campaign` option describes: shown in
 * the announcement part only while the site's clock is between its dates.
 */
#[CoversClass(CampaignBand::class)]
final class CampaignBandTest extends TestCase
{
    private const ZONE = 'Europe/Stockholm';

    private const NOW = '2026-11-27 12:00:00';

    private const TEXT = 'Black Friday: 20% off everything';

    private const URL = 'https://store.example/shop/';

    private const STARTS = '2026-11-27T00:00:00';

    private const ENDS = '2026-11-30T00:00:00';

    private const EMPTY_PART = '<div class="wp-block-template-part"></div>';

    private const HAND_WRITTEN_PART = '<div class="wp-block-template-part"><div class="pixonaut-shop-announcement"><p>Free delivery</p></div></div>';

    /** A text that would run as a script if it were printed as markup. */
    private const SCRIPT = '<script>alert("x")</script>';

    private const ANNOUNCEMENT_BLOCK = ['blockName' => 'core/template-part', 'attrs' => ['slug' => 'announcement']];

    private const HEADER_BLOCK = ['blockName' => 'core/template-part', 'attrs' => ['slug' => 'header']];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\when('wp_timezone')->alias(static fn (): DateTimeZone => new DateTimeZone(self::ZONE));
        Functions\when('esc_url')->alias(static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES));
        Functions\when('esc_url_raw')->alias(static fn (string $url): string => 1 === preg_match('#^https?://#', $url) ? $url : '');
    }

    /** @param array<string, mixed> $option */
    private function band(array|string|null $option, string $now = self::NOW): CampaignBand
    {
        Functions\when('get_option')->justReturn($option ?? false);

        return new CampaignBand(static fn (): DateTimeImmutable => new DateTimeImmutable($now, new DateTimeZone(self::ZONE)));
    }

    /** @return array<string, mixed> */
    private static function campaign(array $over = []): array
    {
        return $over + ['text' => self::TEXT, 'url' => self::URL, 'starts' => self::STARTS, 'ends' => self::ENDS];
    }

    private function render(CampaignBand $band, string $part = self::EMPTY_PART, array $block = self::ANNOUNCEMENT_BLOCK): string
    {
        return $band->render($part, $block);
    }

    public function testBandShowsBetweenItsDates(): void
    {
        $html = $this->render($this->band(self::campaign()));

        self::assertStringContainsString('pixonaut-shop-announcement', $html);
        self::assertStringContainsString('has-highlight-background-color', $html);
        self::assertStringContainsString('<a href="' . self::URL . '">' . self::TEXT . '</a>', $html);
        self::assertStringStartsWith('<div class="wp-block-template-part">', $html);
        self::assertStringEndsWith('</div>', $html);
    }

    public function testBandShowsFromItsStartInstant(): void
    {
        self::assertStringContainsString(self::TEXT, $this->render($this->band(self::campaign(), '2026-11-27 00:00:00')));
    }

    /** @return iterable<string, array{string}> */
    public static function outsideTheDates(): iterable
    {
        yield 'before it starts' => ['2026-11-26 23:59:59'];
        yield 'at its end' => ['2026-11-30 00:00:00'];
        yield 'after it ends' => ['2026-12-01 09:00:00'];
    }

    #[DataProvider('outsideTheDates')]
    public function testBandHiddenBeforeStartAndAfterEnd(string $now): void
    {
        self::assertSame(self::EMPTY_PART, $this->render($this->band(self::campaign(), $now)));
    }

    public function testBandComparesInTheSitesTimeZone(): void
    {
        // 23:30 UTC on the 26th is 00:30 on the 27th in Stockholm.
        $utc = new DateTimeImmutable('2026-11-26 23:30:00', new DateTimeZone('UTC'));
        Functions\when('get_option')->justReturn(self::campaign());
        $band = new CampaignBand(static fn (): DateTimeImmutable => $utc->setTimezone(new DateTimeZone(self::ZONE)));

        self::assertStringContainsString(self::TEXT, $this->render($band));
    }

    /** @return iterable<string, array{mixed}> */
    public static function campaignsWithoutDates(): iterable
    {
        yield 'no option' => [null];
        yield 'no dates' => [['text' => self::TEXT, 'url' => self::URL]];
        yield 'empty dates' => [self::campaign(['starts' => '', 'ends' => ''])];
        yield 'no end' => [['text' => self::TEXT, 'url' => self::URL, 'starts' => self::STARTS]];
        yield 'no start' => [['text' => self::TEXT, 'url' => self::URL, 'ends' => self::ENDS]];
        yield 'unparsable start' => [self::campaign(['starts' => 'tomorrow'])];
        yield 'unparsable end' => [self::campaign(['ends' => 'next week'])];
        yield 'impossible date' => [self::campaign(['starts' => '2026-02-31T00:00:00'])];
        yield 'dates that are not strings' => [self::campaign(['starts' => 1_795_000_000, 'ends' => 1_796_000_000])];
        yield 'ends before it starts' => [self::campaign(['starts' => self::ENDS, 'ends' => self::STARTS])];
        yield 'no text' => [self::campaign(['text' => '  '])];
        yield 'text that is not a string' => [self::campaign(['text' => 5])];
        yield 'not an array' => ['Black Friday'];
    }

    #[DataProvider('campaignsWithoutDates')]
    public function testBandWithoutDatesNeverShows(mixed $option): void
    {
        self::assertSame(self::EMPTY_PART, $this->render($this->band($option)));
    }

    public function testBandAcceptsADateAloneAsMidnightInTheSitesZone(): void
    {
        $option = self::campaign(['starts' => '2026-11-27', 'ends' => '2026-11-30']);

        self::assertStringContainsString(self::TEXT, $this->render($this->band($option)));
        self::assertSame(self::EMPTY_PART, $this->render($this->band($option, '2026-11-30 00:00:00')));
    }

    public function testBandEscapesItsTextAndUrl(): void
    {
        $option = self::campaign(['text' => self::SCRIPT, 'url' => 'https://store.example/?a=1&b="x"']);

        $html = $this->render($this->band($option));

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('"x"', $html);
        self::assertStringContainsString('&quot;x&quot;', $html);
    }

    public function testBandWithAnUnsafeUrlShowsItsTextWithoutALink(): void
    {
        $html = $this->render($this->band(self::campaign(['url' => 'javascript:alert(1)'])));

        self::assertStringContainsString(self::TEXT, $html);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('javascript', $html);
    }

    public function testBandWithoutAUrlShowsItsTextWithoutALink(): void
    {
        $html = $this->render($this->band(self::campaign(['url' => ''])));

        self::assertStringContainsString(self::TEXT, $html);
        self::assertStringNotContainsString('<a ', $html);
    }

    public function testBandLeavesOtherPartsAlone(): void
    {
        self::assertSame(self::EMPTY_PART, $this->render($this->band(self::campaign()), self::EMPTY_PART, self::HEADER_BLOCK));
    }

    public function testBandKeepsAHandWrittenAnnouncementWhileNoCampaignShows(): void
    {
        self::assertSame(self::HAND_WRITTEN_PART, $this->render($this->band(null), self::HAND_WRITTEN_PART));
        self::assertSame(self::HAND_WRITTEN_PART, $this->render($this->band(self::campaign(['ends' => ''])), self::HAND_WRITTEN_PART));
    }

    public function testBandTakesTheAnnouncementsPlaceWhileItShows(): void
    {
        $html = $this->render($this->band(self::campaign()), self::HAND_WRITTEN_PART);

        self::assertStringContainsString(self::TEXT, $html);
        self::assertStringNotContainsString('Free delivery', $html);
    }

    public function testRegistersOnTheTemplatePartBlock(): void
    {
        $band = $this->band(null);
        $band->register();

        self::assertNotFalse(has_filter('render_block_core/template-part', [$band, 'render']));
    }
}
