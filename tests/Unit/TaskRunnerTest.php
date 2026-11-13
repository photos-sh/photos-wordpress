<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\BusyException;
use PhotosSh\Transport\Response;
use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\Queue\Outcome;
use PhotosSh\WordPress\Queue\Task;
use PhotosSh\WordPress\Queue\TaskRunner;
use PhotosSh\WordPress\Settings;
use PhotosSh\WordPress\Tests\Support\RecordingTransport;
use PhotosSh\WordPress\Tests\Support\WpStubs;
use PHPUnit\Framework\TestCase;

/**
 * The bulk actions' tasks that end without a new image: alt text, and
 * images in which no face was found.
 */
final class TaskRunnerTest extends TestCase
{
    private const ALT = '_wp_attachment_image_alt';

    private string $image;

    private RecordingTransport $transport;

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

    private function runner(Response $response): TaskRunner
    {
        $settings = new Settings();
        $this->transport = new RecordingTransport($response);
        $clients = new ClientFactory($settings, $this->transport);

        return new TaskRunner(new Processor($clients, $settings), new AltText($clients));
    }

    private static function caption(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], '{"caption":"A red shoe on a white table.","detail":"short","width":800,"height":600}');
    }

    private static function task(string $operation, int $item = 12): Task
    {
        return new Task('b1', Task::KIND_ATTACHMENT, $item, $operation, 1);
    }

    public function testWritesAltTextWhereThereIsNone(): void
    {
        WpStubs::image(12, $this->image);

        $outcome = $this->runner(self::caption())->run(self::task('alt-text'));

        self::assertSame(Outcome::DONE, $outcome->status);
        self::assertSame(0, $outcome->result, 'no new attachment is made');
        self::assertSame('A red shoe on a white table.', WpStubs::$meta[12][self::ALT]);
        self::assertCount(1, $this->transport->requests);
    }

    public function testSkipsAnImageThatHasAltText(): void
    {
        WpStubs::image(12, $this->image, 'Text an editor wrote');

        $outcome = $this->runner(self::caption())->run(self::task('alt-text'));

        self::assertSame(Outcome::SKIPPED, $outcome->status);
        self::assertSame('The image already has alternative text. It was kept.', $outcome->message);
        self::assertSame('Text an editor wrote', WpStubs::$meta[12][self::ALT]);
        self::assertSame([], $this->transport->requests);
    }

    public function testReplacesAltTextWhenTheBulkActionSaysSo(): void
    {
        WpStubs::image(12, $this->image, 'Text an editor wrote');

        $outcome = $this->runner(self::caption())->run(self::task('alt-text-replace'));

        self::assertSame(Outcome::DONE, $outcome->status);
        self::assertSame('A red shoe on a white table.', WpStubs::$meta[12][self::ALT]);
    }

    public function testAnImageWithNoFaceIsSkippedWithTheReason(): void
    {
        WpStubs::image(12, $this->image);

        $outcome = $this->runner(new Response(200, ['Content-Type' => 'image/jpeg', 'X-Faces' => '0'], 'jpg'))
            ->run(self::task('blur-faces'));

        self::assertSame(Outcome::SKIPPED, $outcome->status);
        self::assertSame(0, $outcome->result);
        self::assertSame('No face was found. No new image was saved.', $outcome->message);
        self::assertSame([], WpStubs::$uploads);
    }

    public function testABusyServerIsLeftToTheWorkerToRetry(): void
    {
        WpStubs::image(12, $this->image);
        $busy = new Response(503, ['Content-Type' => 'application/json', 'Retry-After' => '30'], '{"error":{"code":"busy","message":"busy"}}');

        try {
            $this->runner($busy)->run(self::task('alt-text'));
            self::fail('Expected the error to reach the worker.');
        } catch (BusyException $error) {
            self::assertSame(30, $error->retryAfter);
        }

        self::assertCount(1, $this->transport->requests, 'the library does not retry inside a task');
        self::assertArrayNotHasKey(12, WpStubs::$meta);
    }

    public function testRefusesAnOperationItDoesNotKnow(): void
    {
        WpStubs::image(12, $this->image);

        $this->expectException(ProcessingException::class);
        $this->runner(self::caption())->run(self::task('caption'));
    }
}
