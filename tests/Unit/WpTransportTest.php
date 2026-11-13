<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Photos;
use PhotosSh\Transport\Request;
use PhotosSh\WordPress\Tests\Support\WpStubs;
use PhotosSh\WordPress\WpTransport;
use PHPUnit\Framework\TestCase;

final class WpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        WpStubs::reset();
    }

    public function testPassesTheRequestToWordPress(): void
    {
        WpStubs::$response = WpStubs::httpResponse(200);
        $request = new Request(
            'POST',
            'http://engine.test/api/v1/upscale?scale=2',
            ['Authorization' => 'Bearer k', 'User-Agent' => 'photos-sh-php/1.0.0', 'Content-Type' => 'multipart/form-data; boundary=x'],
            'the-body',
            120.0,
        );

        (new WpTransport())->send($request);

        self::assertCount(1, WpStubs::$requests);
        $sent = WpStubs::$requests[0];
        self::assertSame('http://engine.test/api/v1/upscale?scale=2', $sent['url']);
        self::assertSame('POST', $sent['args']['method']);
        self::assertSame('the-body', $sent['args']['body']);
        self::assertSame(120.0, $sent['args']['timeout']);
        self::assertSame(0, $sent['args']['redirection'], 'redirects must not be followed');
        self::assertSame('Bearer k', $sent['args']['headers']['Authorization']);
        self::assertSame('multipart/form-data; boundary=x', $sent['args']['headers']['Content-Type']);
        self::assertMatchesRegularExpression(
            '#^photos-sh-php/1\.0\.0 photos-sh-wordpress/\d+\.\d+\.\d+$#',
            $sent['args']['headers']['User-Agent'],
        );
    }

    public function testSendsNoBodyForAGet(): void
    {
        WpStubs::$response = WpStubs::httpResponse(200);

        (new WpTransport())->send(new Request('GET', 'http://engine.test/api/v1/account'));

        self::assertArrayNotHasKey('body', WpStubs::$requests[0]['args']);
    }

    public function testMapsTheResponse(): void
    {
        WpStubs::$response = WpStubs::httpResponse(
            200,
            ['Content-Type' => 'image/png', 'x-image-width' => '640', 'Server-Timing' => ['decode;dur=1', 'model;dur=2']],
            "\x89PNG",
        );

        $response = (new WpTransport())->send(new Request('POST', 'http://engine.test/api/v1/resize'));

        self::assertSame(200, $response->status);
        self::assertSame("\x89PNG", $response->body);
        self::assertSame('image/png', $response->header('content-type'));
        self::assertSame('640', $response->header('X-Image-Width'));
        self::assertSame('decode;dur=1, model;dur=2', $response->header('server-timing'), 'repeated headers are joined');
    }

    public function testReturnsErrorStatusesAsResponses(): void
    {
        WpStubs::$response = WpStubs::httpResponse(503, ['Retry-After' => '7'], '{"error":{"code":"busy","message":"busy"}}');

        $response = (new WpTransport())->send(new Request('POST', 'http://engine.test/api/v1/upscale'));

        self::assertSame(503, $response->status);
        self::assertSame('7', $response->header('retry-after'));
    }

    public function testAFailedConnectionThrowsConnectionException(): void
    {
        WpStubs::$response = new \WP_Error('http_request_failed', 'cURL error 7: Failed to connect to engine.test port 80');

        try {
            (new WpTransport())->send(new Request('GET', 'http://engine.test/api/v1/account'));
            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $e) {
            self::assertNotInstanceOf(TimeoutException::class, $e);
            self::assertStringContainsString('cURL error 7', $e->getMessage());
        }
    }

    public function testATimeoutThrowsTimeoutException(): void
    {
        WpStubs::$response = new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out after 120001 milliseconds');

        $this->expectException(TimeoutException::class);
        (new WpTransport())->send(new Request('GET', 'http://engine.test/api/v1/account'));
    }

    public function testWorksAsTheLibrarysTransport(): void
    {
        WpStubs::$response = WpStubs::httpResponse(200, ['content-type' => 'application/json'], '{"account":"shop","credits":12}');

        $photos = new Photos('secret-key', 'http://engine.test/api/', transport: new WpTransport());

        self::assertSame(['account' => 'shop', 'credits' => 12], $photos->account());
        self::assertSame('http://engine.test/api/v1/account', WpStubs::$requests[0]['url']);
        self::assertSame('Bearer secret-key', WpStubs::$requests[0]['args']['headers']['Authorization']);
    }
}
