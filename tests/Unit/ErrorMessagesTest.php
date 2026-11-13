<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\AccountSuspendedException;
use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Exception\UnauthorizedException;
use PhotosSh\WordPress\ErrorMessages;
use PhotosSh\WordPress\NotConfiguredException;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\SkippedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{\Throwable, string}>
     */
    public static function errors(): iterable
    {
        yield 'no key' => [new NotConfiguredException('x'), 'has no API key yet'];
        yield 'key refused' => [new UnauthorizedException(401, 'unauthorized', 'bad key'), 'refused the API key'];
        yield 'no credits' => [new PaymentRequiredException(402, 'payment_required', 'x'), 'out of credits'];
        yield 'suspended' => [new AccountSuspendedException(403, 'account_suspended', 'x'), 'suspended'];
        yield 'busy' => [new BusyException(503, 'busy', 'x'), 'busy right now. Try again'];
        yield 'proxy 503' => [new ApiException(503, null, '<html>'), 'busy right now. Try again'];
        yield 'timeout' => [new TimeoutException('x'), 'took too long'];
        yield 'connection' => [new ConnectionException('x'), 'Could not reach Photos.sh'];
        yield 'too large' => [new ApiException(413, 'payload_too_large', 'x'), 'too large'];
        yield 'format' => [new ApiException(415, 'unsupported_format', 'x'), 'cannot read this image format'];
        yield 'bad request' => [new ApiException(400, 'bad_request', 'scale must be 1-4'), 'could not process this image: scale must be 1-4'];
        yield 'internal' => [new ApiException(500, 'internal', 'x'), 'HTTP 500'];
        yield 'local' => [new ProcessingException('The image file is not on this server.'), 'The image file is not on this server.'];
        yield 'nothing done' => [new SkippedException('No face was found. No new image was saved.'), 'No face was found. No new image was saved.'];
        yield 'tool not installed' => [new ApiException(503, 'model_unavailable', 'x'), 'This tool is not available'];
        yield 'over 12 megapixels' => [new ApiException(413, 'payload_too_large', 'cleaning takes at most 12 megapixels'), 'too large'];
        yield 'anything else' => [new \LogicException('secret detail'), 'Something went wrong'];
    }

    #[DataProvider('errors')]
    public function testDescribe(\Throwable $error, string $expected): void
    {
        self::assertStringContainsString($expected, ErrorMessages::describe($error));
    }

    public function testAnUnknownErrorsDetailIsNotShown(): void
    {
        self::assertStringNotContainsString('secret detail', ErrorMessages::describe(new \LogicException('secret detail')));
    }
}
