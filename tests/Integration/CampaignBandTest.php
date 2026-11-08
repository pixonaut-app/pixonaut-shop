<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Campaign\CampaignBand;

/**
 * The campaign band as WordPress renders the header: shown in the
 * announcement part between the dates of the `pixonaut_shop_campaign`
 * option, on the site's clock, and never without them.
 */
final class CampaignBandTest extends TestCase
{
    private const TEXT = 'Black Friday: 20% off everything';

    private const HIGHLIGHT_GROUND = 'has-highlight-background-color';

    private const SCRIPT = '<script>alert("x")</script>';

    /** A day in the site's zone, formatted as the option holds it. */
    private const DATE_FORMAT = 'Y-m-d\TH:i:s';

    protected function tearDown(): void
    {
        delete_option(CampaignBand::OPTION);
    }

    public function testBandShowsBetweenItsDates(): void
    {
        update_option(CampaignBand::OPTION, self::campaign('-1 day', '+1 day'));

        $header = self::header();

        self::assertStringContainsString(self::TEXT, $header);
        self::assertStringContainsString(self::HIGHLIGHT_GROUND, $header);
        self::assertLessThan((int) strpos($header, 'wp-block-site-title'), (int) strpos($header, self::TEXT));
    }

    public function testBandHiddenBeforeStartAndAfterEnd(): void
    {
        update_option(CampaignBand::OPTION, self::campaign('+1 day', '+2 days'));
        self::assertStringNotContainsString(self::TEXT, self::header());

        update_option(CampaignBand::OPTION, self::campaign('-2 days', '-1 day'));
        $header = self::header();
        self::assertStringNotContainsString(self::TEXT, $header);
        self::assertStringNotContainsString(self::HIGHLIGHT_GROUND, $header);
    }

    public function testBandWithoutDatesNeverShows(): void
    {
        update_option(CampaignBand::OPTION, ['text' => self::TEXT, 'url' => home_url('/shop/'), 'starts' => '', 'ends' => '']);
        self::assertStringNotContainsString(self::TEXT, self::header());

        update_option(CampaignBand::OPTION, ['text' => self::TEXT, 'url' => home_url('/shop/')]);
        self::assertStringNotContainsString(self::TEXT, self::header());
    }

    public function testBandEscapesItsTextAndUrl(): void
    {
        update_option(CampaignBand::OPTION, ['text' => self::SCRIPT, 'url' => 'javascript:alert(1)'] + self::campaign('-1 day', '+1 day'));

        $header = self::header();

        self::assertStringNotContainsString('<script>alert', $header);
        self::assertStringContainsString('&lt;script&gt;', $header);
        self::assertStringNotContainsString('javascript:', $header);
    }

    public function testBandLinksToItsAddress(): void
    {
        $shop = home_url('/shop/');
        update_option(CampaignBand::OPTION, self::campaign('-1 day', '+1 day', $shop));

        self::assertStringContainsString('<a href="' . $shop . '">' . self::TEXT . '</a>', self::header());
    }

    /** @return array{text: string, url: string, starts: string, ends: string} */
    private static function campaign(string $starts, string $ends, string $url = ''): array
    {
        $now = current_datetime();

        return [
            'text' => self::TEXT,
            'url' => $url,
            'starts' => $now->modify($starts)->format(self::DATE_FORMAT),
            'ends' => $now->modify($ends)->format(self::DATE_FORMAT),
        ];
    }

    private static function header(): string
    {
        return do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
    }
}
