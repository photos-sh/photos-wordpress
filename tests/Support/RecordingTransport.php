<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Support;

use PhotosSh\Transport\Request;
use PhotosSh\Transport\Response;
use PhotosSh\Transport\Transport;

/**
 * Answers every request with the same response and keeps the requests.
 */
final class RecordingTransport implements Transport
{
    /** @var list<Request> */
    public array $requests = [];

    public function __construct(private readonly Response $response) {}

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        return $this->response;
    }
}
