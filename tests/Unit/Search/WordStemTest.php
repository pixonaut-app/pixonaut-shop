<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Search\WordStem;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(WordStem::class)]
final class WordStemTest extends TestCase
{
    private const SWEDISH = 'sv_SE';

    private const ENGLISH = 'en_US';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function words(): iterable
    {
        yield 'a Swedish singular' => [self::SWEDISH, 'panda', 'pand'];
        yield 'another Swedish singular' => [self::SWEDISH, 'Uggla', 'uggl'];
        yield 'a Swedish plural' => [self::SWEDISH, 'ugglor', 'uggl'];
        yield 'a Swedish definite plural' => [self::SWEDISH, 'ugglorna', 'uggl'];
        yield 'a Swedish definite singular' => [self::SWEDISH, 'ugglan', 'uggl'];
        yield 'a Swedish plural in -er' => [self::SWEDISH, 'kaniner', 'kanin'];
        yield 'a Swedish word with no ending' => [self::SWEDISH, 'kanin', 'kanin'];
        yield 'a Swedish word with letters beyond ASCII' => [self::SWEDISH, 'FÅGLAR', 'fågl'];
        yield 'an ending that would leave too short a stem' => [self::SWEDISH, 'tea', 'tea'];
        yield 'an English plural' => [self::ENGLISH, 'owls', 'owl'];
        yield 'an English plural in -ies' => [self::ENGLISH, 'puppies', 'pupp'];
        yield 'an English singular in -y' => [self::ENGLISH, 'puppy', 'pupp'];
        yield 'a Swedish ending in an English store' => [self::ENGLISH, 'panda', 'panda'];
        yield 'a language without endings' => ['de_DE', 'Pandas', 'pandas'];
    }

    #[DataProvider('words')]
    public function testStemsAWordByItsLanguagesEndings(string $locale, string $word, string $stem): void
    {
        self::assertSame($stem, (new WordStem($locale))->of($word));
    }

    public function testTakesOffOneEndingOnly(): void
    {
        // "orna" comes off, not "a" first and then "orn".
        self::assertSame('blomm', (new WordStem(self::SWEDISH))->of('blommorna'));
    }

    public function testKeepsAStemOfTheFewestLettersItMay(): void
    {
        self::assertSame(WordStem::MIN_LETTERS, mb_strlen((new WordStem(self::SWEDISH))->of('rosor')));
    }
}
