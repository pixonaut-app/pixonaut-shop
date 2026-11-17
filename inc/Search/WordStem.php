<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Search;

/**
 * The stem of a word a shopper searches for: the word without the ending
 * that changes between its forms, so "panda" and "pandor", or "uggla" and
 * "ugglor", share the stem a product's name starts with.
 *
 * The endings are the store's language's, read from its locale; a language
 * without a list keeps every word whole. Only one ending comes off, and
 * only when what remains is still long enough to say something: a short
 * stem would match half the catalogue.
 */
final class WordStem
{
    /**
     * The fewest letters a stem keeps. Three keeps "ros" from "rosor" while
     * "te" or "is" stay whole words.
     */
    public const MIN_LETTERS = 3;

    /** Between a locale's language and its region, as in `sv_SE`. */
    private const LOCALE_SEPARATOR = '_';

    /**
     * The endings that change between a word's forms, longest first, so a
     * longer ending comes off before a shorter one it ends with, by the
     * language part of a WordPress locale.
     *
     * Swedish: the plural and definite endings of nouns ("ugglor",
     * "ugglorna", "ugglan") and the vowel a singular ends with ("uggla",
     * "panda"). English: plural and verb endings ("puppies", "boxes",
     * "owls", "printed").
     */
    private const ENDINGS = [
        'sv' => ['arna', 'erna', 'orna', 'an', 'ar', 'en', 'er', 'et', 'na', 'or', 'a', 'e'],
        'en' => ['ies', 'ing', 'ed', 'es', 's', 'y'],
    ];

    public function __construct(private readonly string $locale)
    {
    }

    /**
     * The word's stem in lower case, or the word itself in lower case when
     * no ending of the language comes off it.
     */
    public function of(string $word): string
    {
        $word = mb_strtolower($word);

        foreach ($this->endings() as $ending) {
            if (! str_ends_with($word, $ending)) {
                continue;
            }

            $stem = mb_substr($word, 0, mb_strlen($word) - mb_strlen($ending));

            if (mb_strlen($stem) >= self::MIN_LETTERS) {
                return $stem;
            }
        }

        return $word;
    }

    /** @return list<string> */
    private function endings(): array
    {
        $language = strtolower(explode(self::LOCALE_SEPARATOR, $this->locale)[0]);

        return self::ENDINGS[$language] ?? [];
    }
}
