<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Blocks\BlockStyles;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(BlockStyles::class)]
final class BlockStylesTest extends TestCase
{
    /**
     * Every style the theme offers, by block, with its label and the values
     * the Site Editor's Styles start it from. The names are what a store's
     * content saves in its blocks' classes, so they never change.
     */
    private const STYLES = [
        'core/group' => [
            [
                'name'       => 'panel',
                'label'      => 'Panel',
                'style_data' => [
                    'color'   => ['background' => 'var:preset|color|base'],
                    'border'  => [
                        'color'  => 'var:preset|color|border',
                        'radius' => 'var(--wp--custom--radius--large)',
                        'style'  => 'solid',
                        'width'  => '1px',
                    ],
                    'shadow'  => 'var:preset|shadow|card',
                    'spacing' => [
                        'padding' => [
                            'top'    => 'var:preset|spacing|60',
                            'right'  => 'var:preset|spacing|60',
                            'bottom' => 'var:preset|spacing|60',
                            'left'   => 'var:preset|spacing|60',
                        ],
                    ],
                ],
            ],
            [
                'name'       => 'band-tint-1',
                'label'      => 'Band (tint 1)',
                'style_data' => [
                    'color' => [
                        'background' => 'var:preset|color|tint-1',
                        'text'       => 'var:preset|color|contrast',
                    ],
                ],
            ],
            [
                'name'       => 'band-tint-2',
                'label'      => 'Band (tint 2)',
                'style_data' => [
                    'color' => [
                        'background' => 'var:preset|color|tint-2',
                        'text'       => 'var:preset|color|contrast',
                    ],
                ],
            ],
            [
                'name'  => 'notice',
                'label' => 'Notice',
            ],
        ],
        'core/paragraph' => [
            [
                'name'       => 'pill',
                'label'      => 'Pill',
                'style_data' => [
                    'color'      => [
                        'background' => 'var:preset|color|highlight',
                        'text'       => 'var:preset|color|highlight-contrast',
                    ],
                    'border'     => ['radius' => 'var(--wp--custom--radius--small)'],
                    'spacing'    => [
                        'padding' => [
                            'top'    => '0.25rem',
                            'right'  => 'var:preset|spacing|30',
                            'bottom' => '0.25rem',
                            'left'   => 'var:preset|spacing|30',
                        ],
                    ],
                    'typography' => [
                        'fontSize'   => 'var:preset|font-size|small',
                        'fontWeight' => '700',
                    ],
                ],
            ],
        ],
        'woocommerce/add-to-cart-form' => [
            [
                'name'  => 'swatches',
                'label' => 'Use swatches',
            ],
        ],
        'core/button' => [
            [
                'name'       => 'text',
                'label'      => 'Text link',
                'style_data' => [
                    'color'      => [
                        'background' => 'transparent',
                        'text'       => 'var:preset|color|muted',
                    ],
                    'border'     => ['width' => '0'],
                    'spacing'    => [
                        'padding' => [
                            'top'    => '0',
                            'right'  => '0',
                            'bottom' => '0',
                            'left'   => '0',
                        ],
                    ],
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

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
    }

    public function testRegistersTheStylesOnInit(): void
    {
        (new BlockStyles())->register();

        self::assertNotFalse(has_action('init', 'Pixonaut\Shop\Blocks\BlockStyles->register_styles()'));
    }

    public function testRegistersEveryStyleWithItsLabelAndValues(): void
    {
        $registered = [];
        Functions\when('register_block_style')->alias(
            static function (string $block, array $style) use (&$registered): bool {
                $registered[$block][] = $style;

                return true;
            }
        );

        (new BlockStyles())->register_styles();

        self::assertSame(self::STYLES, $registered);
    }
}
