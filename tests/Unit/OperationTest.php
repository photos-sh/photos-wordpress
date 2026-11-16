<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\BlurFacesResult;
use PhotosSh\FixResult;
use PhotosSh\Photos;
use PhotosSh\RemoveTextResult;
use PhotosSh\RestoreFacesResult;
use PhotosSh\Transport\Response;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Tests\Support\RecordingTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase
{
    private string $image;

    protected function setUp(): void
    {
        $this->image = (string) tempnam(sys_get_temp_dir(), 'photos-sh-test');
        file_put_contents($this->image, 'image-bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->image);
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function calls(): iterable
    {
        yield 'remove background' => [Operation::remove_background(), '/api/v1/remove-background'];
        yield 'remove background, a studio shot' => [Operation::remove_background(true), '/api/v1/remove-background?studio=true'];
        yield 'remove background, not a studio shot' => [Operation::remove_background(false), '/api/v1/remove-background'];
        yield 'upscale 2x' => [Operation::upscale(2), '/api/v1/upscale?scale=2'];
        yield 'upscale 4x' => [Operation::upscale(4), '/api/v1/upscale?scale=4'];
        yield 'remove text' => [Operation::remove_text(), '/api/v1/remove-text'];
        yield 'fix light and colour' => [Operation::enhance(), '/api/v1/enhance?auto=true'];
        yield 'remove noise' => [Operation::denoise(), '/api/v1/denoise'];
        yield 'colourise' => [Operation::colorize(), '/api/v1/colorize'];
        yield 'restore faces' => [Operation::restore_faces(), '/api/v1/restore-faces'];
        yield 'hide faces' => [Operation::blur_faces(), '/api/v1/blur-faces?mode=blur'];
        yield 'fix for amazon' => [Operation::fix('amazon'), '/api/v1/fix?marketplace=amazon'];
        yield 'fix for zalando' => [Operation::fix('Zalando'), '/api/v1/fix?marketplace=zalando'];
    }

    #[DataProvider('calls')]
    public function testRunCallsTheApi(Operation $operation, string $expected): void
    {
        $transport = new RecordingTransport(new Response(200, ['Content-Type' => 'image/png'], 'png'));
        $photos = new Photos('key', 'http://engine.test/api', transport: $transport);

        $result = $operation->run($photos, $this->image);

        self::assertSame('png', $result->bytes);
        self::assertCount(1, $transport->requests);
        self::assertSame('POST', $transport->requests[0]->method);
        self::assertSame('http://engine.test' . $expected, $transport->requests[0]->url);
        self::assertStringContainsString('image-bytes', (string) $transport->requests[0]->body);
    }

    public function testKeysRoundTrip(): void
    {
        $keys = [];
        foreach (Operation::all() as $operation) {
            $again = Operation::from_key($operation->key());
            self::assertNotNull($again);
            self::assertSame($operation->name, $again->name);
            self::assertSame($operation->params(), $again->params());
            $keys[] = $operation->key();
        }

        self::assertSame(
            [
                'remove-background', 'upscale-2', 'upscale-4', 'remove-text',
                'enhance', 'denoise', 'colorize', 'restore-faces', 'blur-faces',
                'fix-amazon', 'fix-ebay', 'fix-etsy', 'fix-shopify', 'fix-google', 'fix-allegro', 'fix-zalando',
                'alt-text', 'alt-text-replace',
            ],
            $keys,
        );
    }

    public function testOnlyAltTextMakesNoImage(): void
    {
        $without = [];
        foreach (Operation::all() as $operation) {
            if (!$operation->makes_image()) {
                $without[] = $operation->key();
            }
        }
        self::assertSame(['alt-text', 'alt-text-replace'], $without);

        self::assertFalse(Operation::alt_text()->replace, 'existing text is kept unless asked otherwise');
        self::assertFalse(Operation::from_key('alt-text')?->replace);
        self::assertTrue(Operation::from_key('alt-text-replace')?->replace);
    }

    public function testAltTextIsNotRunAsAnImageOperation(): void
    {
        $transport = new RecordingTransport(new Response(200, ['Content-Type' => 'image/png'], 'png'));
        $photos = new Photos('key', 'http://engine.test/api', transport: $transport);

        try {
            Operation::alt_text()->run($photos, $this->image);
            self::fail('Expected an exception.');
        } catch (\LogicException) {
        }

        self::assertSame([], $transport->requests);
    }

    #[DataProvider('unknownKeys')]
    public function testUnknownKeysNameNoOperation(string $key): void
    {
        self::assertNull(Operation::from_key($key));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unknownKeys(): iterable
    {
        yield [''];
        yield ['upscale'];
        yield ['upscale-3'];
        yield ['fix'];
        yield ['fix-walmart'];
        yield ['resize'];
        yield ['remove-background '];
        yield ['restore_faces'];
        yield ['blur-faces-fill'];
        yield ['alt-text-keep'];
        yield ['caption'];
    }

    public function testRefusesOtherScalesAndMarketplaces(): void
    {
        try {
            Operation::upscale(3);
            self::fail('Expected an exception for scale 3.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        Operation::fix('walmart');
    }

    public function testParamsSuffixesAndLabels(): void
    {
        self::assertSame([], Operation::remove_background()->params());
        self::assertSame([], Operation::remove_background(false)->params());
        self::assertSame(['studio' => true], Operation::remove_background(true)->params());
        self::assertSame('remove-background', Operation::remove_background(true)->key(), 'studio is not part of the key');
        self::assertSame(['scale' => 4], Operation::upscale(4)->params());
        self::assertSame(['marketplace' => 'ebay'], Operation::fix('ebay')->params());

        self::assertSame('no-bg', Operation::remove_background()->suffix());
        self::assertSame('upscaled-2x', Operation::upscale(2)->suffix());
        self::assertSame('no-text', Operation::remove_text()->suffix());
        self::assertSame('amazon', Operation::fix('amazon')->suffix());
        self::assertSame('enhanced', Operation::enhance()->suffix());
        self::assertSame('no-noise', Operation::denoise()->suffix());
        self::assertSame('colourised', Operation::colorize()->suffix());
        self::assertSame('faces-restored', Operation::restore_faces()->suffix());
        self::assertSame('faces-hidden', Operation::blur_faces()->suffix());

        self::assertSame('Upscale 4×', Operation::upscale(4)->label());
        self::assertSame('Fix for Google Merchant Center', Operation::fix('google')->label());
        self::assertSame('fixed for eBay', Operation::fix('ebay')->done_label());

        self::assertSame('Fix light and colour', Operation::enhance()->label());
        self::assertSame('Remove noise', Operation::denoise()->label());
        self::assertSame('Colourise', Operation::colorize()->label());
        self::assertSame('Restore faces', Operation::restore_faces()->label());
        self::assertSame('Hide faces', Operation::blur_faces()->label());
        self::assertSame('light and colour fixed', Operation::enhance()->done_label());
        self::assertSame('faces hidden', Operation::blur_faces()->done_label());
    }

    public function testTheNewOperationsStoreTheirNameAsTheirKey(): void
    {
        // The edit screen finds a result's operation again from the name in its meta.
        foreach ([Operation::enhance(), Operation::denoise(), Operation::colorize(), Operation::restore_faces(), Operation::blur_faces()] as $operation) {
            self::assertSame($operation->name, $operation->key());
            self::assertSame([], $operation->params());
        }
    }

    public function testAltTextLabelsSayWhatHappensToExistingText(): void
    {
        self::assertSame('Write alt text', Operation::alt_text()->label());
        self::assertSame('Replace alt text', Operation::alt_text(true)->label());
        self::assertSame('Write alt text (keep existing text)', Operation::alt_text()->bulk_label());
        self::assertSame('Write alt text (replace existing text)', Operation::alt_text(true)->bulk_label());
        self::assertSame(['replace' => false], Operation::alt_text()->params());
        self::assertSame(['replace' => true], Operation::alt_text(true)->params());

        self::assertSame('Remove noise', Operation::denoise()->bulk_label(), 'other operations keep their name');
    }

    public function testCautions(): void
    {
        self::assertStringContainsString('12 megapixels', Operation::denoise()->caution());
        self::assertStringContainsString('slow', Operation::denoise()->caution());
        self::assertStringContainsString('guesses the colours', Operation::colorize()->caution());
        self::assertStringContainsString('invents the detail', Operation::restore_faces()->caution());
        self::assertStringContainsString('identity photos or evidence', Operation::restore_faces()->caution());
        self::assertStringContainsString('a face it does not find still shows', Operation::blur_faces()->caution());

        foreach ([Operation::remove_background(), Operation::upscale(2), Operation::remove_text(), Operation::enhance(), Operation::fix('amazon'), Operation::alt_text()] as $operation) {
            self::assertSame('', $operation->caution(), $operation->key());
        }
    }

    public function testDetailsOfAResult(): void
    {
        $fix = new FixResult(new Response(200, [
            'Content-Type' => 'image/jpeg',
            'X-Marketplace-Passed' => 'false',
            'X-Marketplace-Findings' => 'min_longest, no_text',
        ], 'jpg'));
        self::assertSame(['passed' => false, 'findings' => ['min_longest', 'no_text']], Operation::details($fix));

        $text = new RemoveTextResult(new Response(200, ['X-Text-Removed' => '3', 'X-Text-Kept' => '1'], 'png'));
        self::assertSame(['removed' => 3, 'kept' => 1], Operation::details($text));

        $plain = Operation::upscale(2)->run(
            new Photos('key', 'http://engine.test/api', transport: new RecordingTransport(new Response(200, [], 'x'))),
            $this->image,
        );
        self::assertSame([], Operation::details($plain));

        $hidden = new BlurFacesResult(new Response(200, ['Content-Type' => 'image/jpeg', 'X-Faces' => '3'], 'jpg'));
        self::assertSame(['faces' => 3], Operation::details($hidden));

        $restored = new RestoreFacesResult(new Response(200, ['X-Faces' => '2', 'X-Faces-Skipped' => '1'], 'jpg'));
        self::assertSame(['faces' => 2, 'skipped' => 1], Operation::details($restored));
    }

    public function testAFaceCountTheApiDidNotSendIsNotReadAsNone(): void
    {
        // The library turns a missing X-Faces into 0.
        $hidden = new BlurFacesResult(new Response(200, ['Content-Type' => 'image/jpeg'], 'jpg'));
        self::assertSame(0, $hidden->faces);
        self::assertSame([], Operation::details($hidden));
        self::assertFalse(Operation::blur_faces()->found_nothing(Operation::details($hidden)));
        self::assertSame('', Operation::blur_faces()->result_note(Operation::details($hidden)));

        $restored = new RestoreFacesResult(new Response(200, ['Content-Type' => 'image/jpeg'], 'jpg'));
        self::assertSame([], Operation::details($restored));
        self::assertFalse(Operation::restore_faces()->found_nothing(Operation::details($restored)));
    }

    /**
     * @return iterable<string, array{Operation, array<string, mixed>, string, bool}>
     */
    public static function notes(): iterable
    {
        yield 'one face hidden' => [Operation::blur_faces(), ['faces' => 1], '1 face was hidden.', false];
        yield 'faces hidden' => [Operation::blur_faces(), ['faces' => 5], '5 faces were hidden.', false];
        yield 'no face to hide' => [Operation::blur_faces(), ['faces' => 0], 'No face was found.', true];
        yield 'one face restored' => [Operation::restore_faces(), ['faces' => 1, 'skipped' => 0], '1 face was restored.', false];
        yield 'faces restored, one left alone' => [
            Operation::restore_faces(),
            ['faces' => 2, 'skipped' => 1],
            '2 faces were restored. 1 face is large enough already and was left as it is.',
            false,
        ];
        yield 'no face to restore' => [Operation::restore_faces(), ['faces' => 0, 'skipped' => 0], 'No face was found.', true];
        yield 'every face left alone' => [
            Operation::restore_faces(),
            ['faces' => 0, 'skipped' => 2],
            'No face was restored. 2 faces are large enough already and were left as they are.',
            true,
        ];
        yield 'the meta as WordPress hands it back' => [Operation::blur_faces(), ['faces' => '2'], '2 faces were hidden.', false];
        yield 'an operation without faces' => [Operation::upscale(2), ['faces' => 0], '', false];
        yield 'remove text says nothing here' => [Operation::remove_text(), ['removed' => 3, 'kept' => 1], '', false];
        yield 'nothing known' => [Operation::blur_faces(), [], '', false];
    }

    /**
     * @param array<string, mixed> $details
     */
    #[DataProvider('notes')]
    public function testWhatTheEditorIsToldAboutFaces(Operation $operation, array $details, string $note, bool $nothing): void
    {
        self::assertSame($note, $operation->result_note($details));
        self::assertSame($nothing, $operation->found_nothing($details));
    }
}
