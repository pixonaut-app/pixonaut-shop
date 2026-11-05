<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

/**
 * The block styles templates, patterns and a store's own content build pages
 * from: the white panel, the tinted bands, the notice and the pill, and the
 * text-link button, and the add-to-cart form's swatches.
 *
 * Each style's values are presets by slug, so a store's Styles changes them
 * in the Site Editor like any other block's. What
 * theme.json cannot express — the panel's padding on phones, the notice's
 * edge and icon, the pill's width — is in `src/styles/_components.css`.
 * A style's name is saved in the class of every block that uses it, so it
 * never changes; its label may.
 */
final class BlockStyles
{
    /**
     * The add-to-cart form's style that shows a variable product's
     * attributes as chips rather than menus (`Product\VariationSwatches`).
     */
    public const SWATCHES = 'swatches';

    /** The tight vertical padding of a pill: a quarter of a line, below the smallest spacing step. */
    private const PILL_PADDING_BLOCK = '0.25rem';

    public function register(): void
    {
        add_action('init', [$this, 'register_styles']);
    }

    public function register_styles(): void
    {
        foreach ($this->styles() as $block => $styles) {
            foreach ($styles as $style) {
                register_block_style($block, $style);
            }
        }
    }

    /**
     * @return array<string, list<array{name: string, label: string, style_data?: array<string, mixed>}>>
     */
    private function styles(): array
    {
        return [
            'core/group' => [
                [
                    'name'       => 'panel',
                    'label'      => __('Panel', 'pixonaut-shop'),
                    'style_data' => [
                        'color'   => ['background' => 'var:preset|color|base'],
                        'border'  => [
                            'color'  => 'var:preset|color|border',
                            'radius' => 'var(--wp--custom--radius--large)',
                            'style'  => 'solid',
                            'width'  => '1px',
                        ],
                        'shadow'  => 'var:preset|shadow|card',
                        'spacing' => ['padding' => $this->padding('var:preset|spacing|60')],
                    ],
                ],
                $this->band('band-tint-1', __('Band (tint 1)', 'pixonaut-shop'), 'tint-1'),
                $this->band('band-tint-2', __('Band (tint 2)', 'pixonaut-shop'), 'tint-2'),
                // Its look is WooCommerce's notices', so one rule styles both.
                [
                    'name'  => 'notice',
                    'label' => __('Notice', 'pixonaut-shop'),
                ],
            ],
            'core/paragraph' => [
                [
                    'name'       => 'pill',
                    'label'      => __('Pill', 'pixonaut-shop'),
                    'style_data' => [
                        'color'      => [
                            'background' => 'var:preset|color|highlight',
                            'text'       => 'var:preset|color|highlight-contrast',
                        ],
                        'border'     => ['radius' => 'var(--wp--custom--radius--small)'],
                        'spacing'    => ['padding' => $this->padding(self::PILL_PADDING_BLOCK, 'var:preset|spacing|30')],
                        'typography' => [
                            'fontSize'   => 'var:preset|font-size|small',
                            'fontWeight' => '700',
                        ],
                    ],
                ],
            ],
            'woocommerce/add-to-cart-form' => [
                [
                    'name'  => self::SWATCHES,
                    'label' => __('Use swatches', 'pixonaut-shop'),
                ],
            ],
            'core/button' => [
                [
                    'name'       => 'text',
                    'label'      => __('Text link', 'pixonaut-shop'),
                    'style_data' => [
                        'color'      => [
                            'background' => 'transparent',
                            'text'       => 'var:preset|color|muted',
                        ],
                        'border'     => ['width' => '0'],
                        'spacing'    => ['padding' => $this->padding('0')],
                        'typography' => [
                            'fontFamily'     => 'var:preset|font-family|body',
                            'fontSize'       => 'var:preset|font-size|small',
                            'fontWeight'     => '400',
                            'textDecoration' => 'underline',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * A full-width section on a tinted ground, always with `contrast` text.
     *
     * @return array{name: string, label: string, style_data: array<string, mixed>}
     */
    private function band(string $name, string $label, string $tint): array
    {
        return [
            'name'       => $name,
            'label'      => $label,
            'style_data' => [
                'color' => [
                    'background' => "var:preset|color|{$tint}",
                    'text'       => 'var:preset|color|contrast',
                ],
            ],
        ];
    }

    /**
     * @return array{top: string, right: string, bottom: string, left: string}
     */
    private function padding(string $block, ?string $inline = null): array
    {
        $inline ??= $block;

        return ['top' => $block, 'right' => $inline, 'bottom' => $block, 'left' => $inline];
    }
}
