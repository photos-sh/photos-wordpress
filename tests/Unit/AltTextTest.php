<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Transport\Response;
use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\NotConfiguredException;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Settings;
use PhotosSh\WordPress\SkippedException;
use PhotosSh\WordPress\Tests\Support\RecordingTransport;
use PhotosSh\WordPress\Tests\Support\WpStubs;
use PHPUnit\Framework\TestCase;

final class AltTextTest extends TestCase
{
    private const ALT = '_wp_attachment_image_alt';

    private string $image;

    protected function setUp(): void
    {
        WpStubs::reset();
        putenv('PHOTOS_API_KEY');
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'sk-0123456789abcdef', 'base_url' => 'http://engine.test/api'];

        $this->image = (string) tempnam(sys_get_temp_dir(), 'photos-sh-test');
        file_put_contents($this->image, 'image-bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->image);
    }

    private static function caption(string $text): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['caption' => $text, 'detail' => 'short', 'width' => 512, 'height' => 512]),
        );
    }

    private function altText(RecordingTransport $transport): AltText
    {
        return new AltText(new ClientFactory(new Settings(), $transport));
    }

    public function testWritesTheShortCaptionAsTheAltText(): void
    {
        WpStubs::image(12, $this->image);
        $transport = new RecordingTransport(self::caption('A woman in a space suit posing for a photo.'));

        $text = $this->altText($transport)->write(12);

        self::assertSame('A woman in a space suit posing for a photo.', $text);
        self::assertSame($text, WpStubs::$meta[12][self::ALT]);
        self::assertCount(1, $transport->requests);
        self::assertSame('POST', $transport->requests[0]->method);
        self::assertSame('http://engine.test/api/v1/caption?detail=short', $transport->requests[0]->url);
        self::assertStringContainsString('image-bytes', (string) $transport->requests[0]->body);
        self::assertSame([['photos_sh_alt_text_written', [12, $text, '']]], WpStubs::$actions);
    }

    public function testTextAnEditorWroteIsKeptAndTheApiIsNotCalled(): void
    {
        WpStubs::image(12, $this->image, 'Eileen Collins in her orange launch suit');
        $transport = new RecordingTransport(self::caption('A woman in a space suit posing for a photo.'));

        try {
            $this->altText($transport)->write(12);
            self::fail('Expected the image to be skipped.');
        } catch (SkippedException $skipped) {
            self::assertStringContainsString('already has alternative text', $skipped->getMessage());
        }

        self::assertSame('Eileen Collins in her orange launch suit', WpStubs::$meta[12][self::ALT]);
        self::assertSame([], $transport->requests, 'no credit is spent on an image that is skipped');
        self::assertSame([], WpStubs::$actions);
    }

    public function testExistingTextIsReplacedOnlyWhenAsked(): void
    {
        WpStubs::image(12, $this->image, 'Eileen Collins in her orange launch suit');
        $transport = new RecordingTransport(self::caption('A woman in a space suit posing for a photo.'));

        $text = $this->altText($transport)->write(12, true);

        self::assertSame('A woman in a space suit posing for a photo.', $text);
        self::assertSame($text, WpStubs::$meta[12][self::ALT]);
        self::assertCount(1, $transport->requests);
        self::assertSame(
            [['photos_sh_alt_text_written', [12, $text, 'Eileen Collins in her orange launch suit']]],
            WpStubs::$actions,
        );
    }

    public function testAltTextOfOnlySpacesCountsAsNone(): void
    {
        WpStubs::image(12, $this->image, "  \n ");
        $transport = new RecordingTransport(self::caption('A red shoe on a white table.'));

        self::assertSame('', AltText::current(12));
        self::assertSame('A red shoe on a white table.', $this->altText($transport)->write(12));
        self::assertSame('A red shoe on a white table.', WpStubs::$meta[12][self::ALT]);
    }

    public function testTheTextIsStoredAsPlainTextOnOneLine(): void
    {
        WpStubs::image(12, $this->image);
        $transport = new RecordingTransport(self::caption(" A sign that says \"Open\" on a baker's <b>door</b>.\n"));

        $text = $this->altText($transport)->write(12);

        self::assertSame('A sign that says "Open" on a baker\'s door.', $text);
        self::assertSame($text, WpStubs::$meta[12][self::ALT]);
    }

    public function testAnEmptyDescriptionChangesNothing(): void
    {
        WpStubs::image(12, $this->image, 'Old text');
        $transport = new RecordingTransport(self::caption('  '));

        try {
            $this->altText($transport)->write(12, true);
            self::fail('Expected an exception.');
        } catch (ProcessingException $error) {
            self::assertStringContainsString('did not return a description', $error->getMessage());
        }

        self::assertSame('Old text', WpStubs::$meta[12][self::ALT]);
        self::assertSame([], WpStubs::$actions);
    }

    public function testAnApiErrorChangesNothing(): void
    {
        WpStubs::image(12, $this->image, 'Old text');
        $transport = new RecordingTransport(new Response(
            402,
            ['Content-Type' => 'application/json'],
            '{"error":{"code":"payment_required","message":"no credits left"}}',
        ));

        try {
            $this->altText($transport)->write(12, true);
            self::fail('Expected an exception.');
        } catch (PaymentRequiredException) {
        }

        self::assertSame('Old text', WpStubs::$meta[12][self::ALT]);
        self::assertSame([], WpStubs::$actions);
    }

    public function testRefusesWhatIsNotAnImageOnThisServer(): void
    {
        $transport = new RecordingTransport(self::caption('A red shoe.'));

        try {
            $this->altText($transport)->write(99);
            self::fail('Expected an exception for a missing attachment.');
        } catch (ProcessingException $error) {
            self::assertSame('This is not an image in the media library.', $error->getMessage());
        }

        WpStubs::image(13, $this->image . '-gone');
        try {
            $this->altText($transport)->write(13);
            self::fail('Expected an exception for a missing file.');
        } catch (ProcessingException $error) {
            self::assertSame('The image file is not on this server.', $error->getMessage());
        }

        self::assertSame([], $transport->requests);
    }

    public function testNeedsAnApiKey(): void
    {
        WpStubs::$options = [];
        WpStubs::image(12, $this->image);
        $transport = new RecordingTransport(self::caption('A red shoe.'));

        try {
            $this->altText($transport)->write(12);
            self::fail('Expected an exception.');
        } catch (NotConfiguredException) {
        }

        self::assertSame([], $transport->requests);
        self::assertArrayNotHasKey(12, WpStubs::$meta);
    }

    public function testTheNoticeSaysWhoWroteTheText(): void
    {
        self::assertSame('The text is written by AI, in English. Read it before publishing.', AltText::notice());
    }
}
