<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Transport\Response;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\Settings;
use PhotosSh\WordPress\SkippedException;
use PhotosSh\WordPress\Tests\Support\RecordingTransport;
use PhotosSh\WordPress\Tests\Support\WpStubs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What happens before a result is saved. Saving itself needs WordPress:
 * the stubbed wp_upload_bits() records the file name and fails.
 */
final class ProcessorTest extends TestCase
{
    private string $image;

    protected function setUp(): void
    {
        WpStubs::reset();
        putenv('PHOTOS_API_KEY');
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'sk-0123456789abcdef', 'base_url' => 'http://engine.test/api'];

        $this->image = sys_get_temp_dir() . '/photos-sh-test-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->image, 'image-bytes');
        WpStubs::image(12, $this->image, 'Text an editor wrote');
    }

    protected function tearDown(): void
    {
        @unlink($this->image);
    }

    /**
     * @param array<string, string> $headers
     */
    private function processor(array $headers): Processor
    {
        $settings = new Settings();
        $transport = new RecordingTransport(new Response(200, ['Content-Type' => 'image/jpeg'] + $headers, 'jpg'));

        return new Processor(new ClientFactory($settings, $transport), $settings);
    }

    /**
     * @return iterable<string, array{Operation, array<string, string>, string}>
     */
    public static function nothingFound(): iterable
    {
        yield 'no face to hide' => [
            Operation::blur_faces(),
            ['X-Faces' => '0'],
            'No face was found. No new image was saved.',
        ];
        yield 'no face to restore' => [
            Operation::restore_faces(),
            ['X-Faces' => '0', 'X-Faces-Skipped' => '0'],
            'No face was found. No new image was saved.',
        ];
        yield 'every face is sharp already' => [
            Operation::restore_faces(),
            ['X-Faces' => '0', 'X-Faces-Skipped' => '2'],
            'No face was restored. 2 faces are large enough already and were left as they are. No new image was saved.',
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('nothingFound')]
    public function testAnImageWithNoFaceIsNotSavedAgain(Operation $operation, array $headers, string $message): void
    {
        try {
            $this->processor($headers)->process(12, $operation);
            self::fail('Expected the image to be skipped.');
        } catch (SkippedException $skipped) {
            self::assertSame($message, $skipped->getMessage());
        }

        self::assertSame([], WpStubs::$uploads);
        self::assertSame(['_wp_attachment_image_alt' => 'Text an editor wrote'], WpStubs::$meta[12], 'the source is not touched');
    }

    /**
     * @return iterable<string, array{Operation, array<string, string>, string}>
     */
    public static function saved(): iterable
    {
        $name = 'photos-sh-test-[0-9a-f]{8}';

        yield 'faces hidden' => [Operation::blur_faces(), ['X-Faces' => '3'], $name . '-faces-hidden\.jpg'];
        yield 'faces restored' => [Operation::restore_faces(), ['X-Faces' => '1', 'X-Faces-Skipped' => '1'], $name . '-faces-restored\.jpg'];
        yield 'a face count the API did not send' => [Operation::blur_faces(), [], $name . '-faces-hidden\.jpg'];
        yield 'light and colour' => [Operation::enhance(), [], $name . '-enhanced\.jpg'];
        yield 'noise' => [Operation::denoise(), [], $name . '-no-noise\.jpg'];
        yield 'colour' => [Operation::colorize(), [], $name . '-colourised\.jpg'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('saved')]
    public function testAResultIsSavedUnderANewName(Operation $operation, array $headers, string $pattern): void
    {
        try {
            $this->processor($headers)->process(12, $operation);
            self::fail('The stubbed upload always fails.');
        } catch (ProcessingException $error) {
            self::assertStringContainsString('The new image could not be saved', $error->getMessage());
        }

        self::assertCount(1, WpStubs::$uploads);
        self::assertMatchesRegularExpression('/^' . $pattern . '$/', WpStubs::$uploads[0]);
        self::assertSame('image-bytes', file_get_contents($this->image), 'the original file is not overwritten');
    }

    public function testResultNoteReadsTheResultsMeta(): void
    {
        WpStubs::$meta[40][Processor::META_RESULT] = ['faces' => 3];

        self::assertSame('3 faces were hidden.', Processor::result_note(40, Operation::blur_faces()));
        self::assertSame('', Processor::result_note(41, Operation::blur_faces()), 'a result without the meta');
        self::assertSame('', Processor::result_note(40, Operation::enhance()));
    }
}
