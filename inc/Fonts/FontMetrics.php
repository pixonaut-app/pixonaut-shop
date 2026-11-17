<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Fonts;

/**
 * A typeface's metrics, read from the table `pnpm fonts:metrics` writes: its
 * units per em, ascent, descent and line gap, and the average width of Latin
 * text at each weight it comes in.
 *
 * @phpstan-type Row array{0: bool, 1: int, 2: int, 3: int, 4: int, 5: array<int, int>}
 */
final class FontMetrics
{
    private const TABLE = __DIR__ . '/font-metrics.php';

    /** @var array<string, Row>|null */
    private ?array $table;

    /**
     * @param array<string, Row>|null $table The rows by family name in lower case; the generated table when null.
     */
    public function __construct(?array $table = null)
    {
        $this->table = $table;
    }

    /**
     * @return Row|null
     */
    public function row(string $family): ?array
    {
        if (null === $this->table) {
            /** @var array<string, Row> $table */
            $table       = require self::TABLE;
            $this->table = $table;
        }

        return $this->table[strtolower(trim($family))] ?? null;
    }

    public function is_serif(string $family): bool
    {
        return (bool) ($this->row($family)[0] ?? false);
    }

    /**
     * The overrides that make a fallback typeface's face of `$local_weight`
     * take the room of `$family` at `$weight`: its `size-adjust`, then its
     * ascent, descent and line gap over that scale, as fractions. Null when
     * either typeface is unknown.
     *
     * @return array{size: float, ascent: float, descent: float, line_gap: float}|null
     */
    public function overrides(string $family, int $weight, string $fallback, int $local_weight): ?array
    {
        $font  = $this->row($family);
        $local = $this->row($fallback);

        if (null === $font || null === $local) {
            return null;
        }

        $font_width  = self::width($font, $weight);
        $local_width = self::width($local, $local_weight);

        if (0 === $font_width || 0 === $local_width || 0 === $font[1] || 0 === $local[1]) {
            return null;
        }

        $size = ($font_width / $font[1]) / ($local_width / $local[1]);

        return [
            'size'     => $size,
            'ascent'   => $font[2] / $font[1] / $size,
            'descent'  => $font[3] / $font[1] / $size,
            'line_gap' => $font[4] / $font[1] / $size,
        ];
    }

    /**
     * The average width at the weight nearest `$weight`, the lighter one on a tie.
     *
     * @param Row $row
     */
    private static function width(array $row, int $weight): int
    {
        $widths = $row[5];
        ksort($widths);
        $nearest = null;

        foreach ($widths as $candidate => $width) {
            if (null === $nearest || abs($candidate - $weight) < abs($nearest[0] - $weight)) {
                $nearest = [$candidate, $width];
            }
        }

        return $nearest[1] ?? 0;
    }
}
