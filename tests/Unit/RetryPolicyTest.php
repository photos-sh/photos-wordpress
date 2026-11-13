<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Exception\UnauthorizedException;
use PhotosSh\WordPress\NotConfiguredException;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Queue\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testABusyServerIsRetriedWhenItSays(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(20, $policy->delay(new BusyException(503, 'busy', 'busy', 20), 0));
        self::assertSame(5, $policy->delay(new BusyException(503, 'busy', 'busy', 1), 0), 'never sooner than 5 s');
        self::assertSame(RetryPolicy::MAX_DELAY, $policy->delay(new BusyException(503, 'busy', 'busy', 86400), 0));
    }

    public function testABusyServerWithoutRetryAfterBacksOff(): void
    {
        $policy = new RetryPolicy();
        $busy = new BusyException(503, 'busy', 'busy');

        self::assertSame(60, $policy->delay($busy, 0));
        self::assertSame(120, $policy->delay($busy, 1));
        self::assertSame(240, $policy->delay($busy, 2));
    }

    public function testAProxys503IsRetriedLikeBusy(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(60, $policy->delay(new ApiException(503, null, 'Service Unavailable'), 0));
        self::assertNull($policy->delay(new ApiException(503, ApiException::MODEL_UNAVAILABLE, 'no model'), 0));
    }

    public function testAFailedConnectionBacksOff(): void
    {
        $policy = new RetryPolicy();
        $error = new ConnectionException('could not connect');

        self::assertSame(60, $policy->delay($error, 0));
        self::assertSame(120, $policy->delay($error, 1));
        self::assertSame(240, $policy->delay($error, 2));
    }

    public function testRetriesAreBounded(): void
    {
        $policy = new RetryPolicy();

        self::assertNull($policy->delay(new ConnectionException('down'), RetryPolicy::MAX_RETRIES));
        self::assertNull($policy->delay(new BusyException(503, 'busy', 'busy', 5), RetryPolicy::MAX_RETRIES));
        self::assertNull($policy->delay(new BusyException(503, 'busy', 'busy', 5), RetryPolicy::MAX_RETRIES + 5));
    }

    public function testATimeoutIsNotRetried(): void
    {
        self::assertNull((new RetryPolicy())->delay(new TimeoutException('timed out'), 0));
    }

    public function testOtherErrorsAreNotRetried(): void
    {
        $policy = new RetryPolicy();

        self::assertNull($policy->delay(new UnauthorizedException(401, 'unauthorized', 'no'), 0));
        self::assertNull($policy->delay(new PaymentRequiredException(402, 'payment_required', 'no credits'), 0));
        self::assertNull($policy->delay(new ApiException(400, 'bad_request', 'bad'), 0));
        self::assertNull($policy->delay(new ApiException(500, 'internal', 'oops'), 0));
        self::assertNull($policy->delay(new ProcessingException('not an image'), 0));
        self::assertNull($policy->delay(new NotConfiguredException('no key'), 0));
        self::assertNull($policy->delay(new \RuntimeException('anything'), 0));
    }
}
