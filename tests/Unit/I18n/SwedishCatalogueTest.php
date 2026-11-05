<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pixonaut\Shop\Tests\TestCase;

/**
 * Every string the theme ships has its Swedish translation (docs/I18N.md):
 * a string that `pnpm i18n` merged in but nobody translated, or that
 * msgmerge marked fuzzy, would reach a Swedish store in English.
 */
#[CoversNothing]
final class SwedishCatalogueTest extends TestCase
{
    private const CATALOGUE = __DIR__ . '/../../../languages/sv_SE.po';

    /** Entries are separated by blank lines; the first is the header. */
    private const ENTRY_SEPARATOR = '/\n\s*\n/';

    public function testTranslatesEveryString(): void
    {
        $untranslated = [];

        foreach ($this->entries() as $entry) {
            if (1 === preg_match('/^msgstr ""\n(?!")/m', $entry . "\n")) {
                $untranslated[] = $entry;
            }
        }

        self::assertSame([], $untranslated);
    }

    public function testHasNoFuzzyTranslation(): void
    {
        $fuzzy = array_filter($this->entries(), static fn (string $entry): bool => str_contains($entry, '#, fuzzy'));

        self::assertSame([], array_values($fuzzy));
    }

    /**
     * @return list<string>
     */
    private function entries(): array
    {
        // No WordPress runtime in a unit test, so no WP_Filesystem.
        $catalogue = file_get_contents(self::CATALOGUE);
        self::assertIsString($catalogue);

        $entries = preg_split(self::ENTRY_SEPARATOR, trim($catalogue));
        self::assertIsArray($entries);

        return \array_slice($entries, 1);
    }
}
