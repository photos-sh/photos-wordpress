<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\WordPress\Naming;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function filenames(): iterable
    {
        yield 'plain' => ['shoe.jpg', 'no-bg', 'png', 'shoe-no-bg.png'];
        yield 'full path' => ['/var/www/wp-content/uploads/2026/11/red-shoe.jpeg', 'upscaled-2x', 'jpg', 'red-shoe-upscaled-2x.jpg'];
        yield 'scaled copy of a large upload' => ['photo-scaled.jpg', 'amazon', 'jpg', 'photo-amazon.jpg'];
        yield 'scaled only at the end' => ['scaled-photo.jpg', 'no-text', 'png', 'scaled-photo-no-text.png'];
        yield 'a result processed again' => ['shoe-no-bg.png', 'upscaled-4x', 'png', 'shoe-no-bg-upscaled-4x.png'];
        yield 'dots in the name' => ['shoe.v2.final.webp', 'no-bg', 'png', 'shoe.v2.final-no-bg.png'];
        yield 'no extension' => ['shoe', 'no-bg', 'png', 'shoe-no-bg.png'];
        yield 'extension with a dot and capitals' => ['shoe.jpg', 'no-bg', '.PNG', 'shoe-no-bg.png'];
        yield 'windows path' => ['C:\\uploads\\shoe.jpg', 'no-bg', 'png', 'shoe-no-bg.png'];
        yield 'nothing left' => ['.jpg', 'no-bg', 'png', 'image-no-bg.png'];
    }

    #[DataProvider('filenames')]
    public function testFilename(string $source, string $suffix, string $extension, string $expected): void
    {
        self::assertSame($expected, Naming::filename($source, $suffix, $extension));
    }

    public function testTitle(): void
    {
        self::assertSame('Red shoe (background removed)', Naming::title('Red shoe', 'background removed'));
        self::assertSame('Red shoe (upscaled 2×)', Naming::title('  Red shoe ', 'upscaled 2×'));
        self::assertSame('Background removed', Naming::title('', 'background removed'));
    }
}
