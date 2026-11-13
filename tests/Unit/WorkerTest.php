<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\WordPress\Queue\Outcome;
use PhotosSh\WordPress\Queue\Progress;
use PhotosSh\WordPress\Queue\RetryPolicy;
use PhotosSh\WordPress\Queue\Runner;
use PhotosSh\WordPress\Queue\Scheduler;
use PhotosSh\WordPress\Queue\Task;
use PhotosSh\WordPress\Queue\Worker;
use PHPUnit\Framework\TestCase;

final class WorkerTest extends TestCase
{
    /** @var list<array{Task, int}> */
    private array $scheduled = [];

    /** @var list<array{Task, Outcome}> */
    private array $recorded = [];

    /** @var list<\Throwable> */
    private array $logged = [];

    private function worker(Outcome|\Throwable $result): Worker
    {
        $runner = new class ($result) implements Runner {
            public int $runs = 0;

            public function __construct(private readonly Outcome|\Throwable $result) {}

            public function run(Task $task): Outcome
            {
                ++$this->runs;
                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }

                return $this->result;
            }
        };
        $scheduler = new class ($this->scheduled) implements Scheduler {
            /** @param list<array{Task, int}> $scheduled */
            public function __construct(private array &$scheduled) {}

            public function schedule(Task $task, int $delay = 0): void
            {
                $this->scheduled[] = [$task, $delay];
            }
        };
        $progress = new class ($this->recorded) implements Progress {
            /** @param list<array{Task, Outcome}> $recorded */
            public function __construct(private array &$recorded) {}

            public function record(Task $task, Outcome $outcome): void
            {
                $this->recorded[] = [$task, $outcome];
            }
        };

        return new Worker($runner, $scheduler, $progress, new RetryPolicy(), function (\Throwable $error): void {
            $this->logged[] = $error;
        });
    }

    /**
     * @return array{batch: string, kind: string, item: int, operation: string, user: int, attempt: int}
     */
    private static function task(int $attempt = 0): array
    {
        return (new Task('b1', Task::KIND_ATTACHMENT, 12, 'remove-background', 1, $attempt))->to_array();
    }

    public function testRecordsASuccess(): void
    {
        $this->worker(new Outcome(Outcome::DONE, 99))->handle(self::task());

        self::assertSame([], $this->scheduled);
        self::assertCount(1, $this->recorded);
        self::assertSame(12, $this->recorded[0][0]->item);
        self::assertSame(Outcome::DONE, $this->recorded[0][1]->status);
        self::assertSame(99, $this->recorded[0][1]->result);
        self::assertSame([], $this->logged);
    }

    public function testABusyServerReschedulesTheTask(): void
    {
        $this->worker(new BusyException(503, 'busy', 'busy', 30))->handle(self::task());

        self::assertSame([], $this->recorded, 'the task is still pending');
        self::assertSame([], $this->logged);
        self::assertCount(1, $this->scheduled);
        [$task, $delay] = $this->scheduled[0];
        self::assertSame(30, $delay);
        self::assertSame(1, $task->attempt);
        self::assertSame('b1', $task->batch);
        self::assertSame(12, $task->item);
        self::assertSame('remove-background', $task->operation);
    }

    public function testAFailedConnectionReschedulesWithBackoff(): void
    {
        $this->worker(new ConnectionException('down'))->handle(self::task(2));

        self::assertCount(1, $this->scheduled);
        self::assertSame(240, $this->scheduled[0][1]);
        self::assertSame(3, $this->scheduled[0][0]->attempt);
    }

    public function testGivesUpAfterTheLastRetry(): void
    {
        $this->worker(new BusyException(503, 'busy', 'busy', 30))->handle(self::task(RetryPolicy::MAX_RETRIES));

        self::assertSame([], $this->scheduled);
        self::assertCount(1, $this->recorded);
        self::assertSame(Outcome::FAILED, $this->recorded[0][1]->status);
        self::assertSame('Photos.sh is busy right now. Try again in a moment.', $this->recorded[0][1]->message);
        self::assertCount(1, $this->logged);
    }

    public function testAnErrorThatWouldRepeatFailsAtOnce(): void
    {
        $this->worker(new PaymentRequiredException(402, 'payment_required', 'no credits'))->handle(self::task());

        self::assertSame([], $this->scheduled);
        self::assertCount(1, $this->recorded);
        self::assertSame(Outcome::FAILED, $this->recorded[0][1]->status);
        self::assertStringContainsString('out of credits', $this->recorded[0][1]->message);
        self::assertCount(1, $this->logged);
    }

    public function testIgnoresWhatIsNotATask(): void
    {
        $worker = $this->worker(new Outcome(Outcome::DONE, 99));
        $worker->handle('nonsense');
        $worker->handle(['batch' => 'b1']);
        $worker->handle(['batch' => 'b1', 'kind' => 'page', 'item' => 12, 'operation' => 'upscale-2', 'user' => 1, 'attempt' => 0]);
        $worker->handle(['batch' => 'b1', 'kind' => 'attachment', 'item' => 0, 'operation' => 'upscale-2', 'user' => 1, 'attempt' => 0]);

        self::assertSame([], $this->recorded);
        self::assertSame([], $this->scheduled);
    }

    public function testATaskSurvivesTheSchedulersJson(): void
    {
        $stored = json_decode((string) json_encode(self::task(2)), true);
        $task = Task::from_array($stored);

        self::assertNotNull($task);
        self::assertSame(self::task(2), $task->to_array());
    }
}
