<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Blocks\BlockLoader;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(BlockLoader::class)]
final class BlockLoaderTest extends TestCase
{
    private string $theme = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->theme = sys_get_temp_dir() . '/pixonaut-shop-' . bin2hex(random_bytes(4));
        mkdir($this->theme);
        Functions\when('get_theme_file_path')->alias(fn (string $file = ''): string => "{$this->theme}/{$file}");
    }

    protected function tearDown(): void
    {
        $files = glob("{$this->theme}/build/blocks/*/block.json");

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
            rmdir(\dirname($file));
        }

        foreach (["{$this->theme}/build/blocks", "{$this->theme}/build", $this->theme] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function testRegistersTheBlocksOnInit(): void
    {
        (new BlockLoader())->register();

        self::assertNotFalse(has_action('init', 'Pixonaut\Shop\Blocks\BlockLoader->register_blocks()'));
    }

    public function testRegistersEveryBuiltBlockFromItsDirectory(): void
    {
        $this->build('icon');
        $this->build('product-categories');

        $registered = $this->registrations();

        (new BlockLoader())->register_blocks();

        self::assertSame(
            ["{$this->theme}/build/blocks/icon", "{$this->theme}/build/blocks/product-categories"],
            $registered->getArrayCopy()
        );
    }

    /** A theme uploaded without its build still boots, without the blocks. */
    public function testRegistersNothingWithoutABuild(): void
    {
        $registered = $this->registrations();

        (new BlockLoader())->register_blocks();

        self::assertSame([], $registered->getArrayCopy());
    }

    /**
     * The directories `register_block_type()` is called with, as it is called.
     *
     * @return ArrayObject<int, string>
     */
    private function registrations(): ArrayObject
    {
        $registered = new ArrayObject();
        Functions\when('register_block_type')->alias(
            static function (string $directory) use ($registered): bool {
                $registered[] = $directory;

                return true;
            }
        );

        return $registered;
    }

    private function build(string $block): void
    {
        mkdir("{$this->theme}/build/blocks/{$block}", 0o777, true);
        file_put_contents("{$this->theme}/build/blocks/{$block}/block.json", '{}');
    }
}
