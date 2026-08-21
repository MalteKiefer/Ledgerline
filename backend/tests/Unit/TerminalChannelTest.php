<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TerminalChannel;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The channel is the seam between two processes, and everything crossing it is
 * a cached value — so these tests are mostly about the shapes a cache store is
 * allowed to hand back.
 */
class TerminalChannelTest extends TestCase
{
    #[Test]
    public function output_is_read_forward_and_never_twice(): void
    {
        $channel = TerminalChannel::open(1, 2);
        $channel->pushOutput('one ');
        $channel->pushOutput('two');

        $first = $channel->readOutput(0);
        $this->assertSame('one two', $first['data']);

        $this->assertSame('', $channel->readOutput($first['cursor'])['data']);

        $channel->pushOutput('three');
        $this->assertSame('three', $channel->readOutput($first['cursor'])['data']);
    }

    #[Test]
    public function a_counter_returned_as_a_string_still_reads(): void
    {
        // Redis stores numeric values unserialized and returns them as strings.
        // The test cache driver returns real integers, so a type check on the
        // counter passes here and fails on the host — which is exactly what
        // happened: the cursor stayed at zero and no terminal output was ever
        // delivered. Reproduce the production shape explicitly.
        $channel = TerminalChannel::open(1, 2);
        $channel->pushOutput('payload');

        Cache::put('srv-term:'.$channel->id.':out:seq', '1', 60);

        $this->assertSame('payload', $channel->readOutput(0)['data']);
    }

    #[Test]
    public function a_timestamp_returned_as_a_string_still_measures_idleness(): void
    {
        // Same shape, and this one is a control rather than a convenience: if
        // the last-seen time does not parse, the idle timeout never fires and a
        // shell nobody is watching stays open on someone's server.
        $channel = TerminalChannel::open(1, 2);

        Cache::put('srv-term:'.$channel->id.':seen', (string) (time() - 300), 600);

        $this->assertGreaterThanOrEqual(300, $channel->idleSeconds());
    }

    #[Test]
    public function a_session_with_no_last_seen_counts_as_abandoned(): void
    {
        // Absent is not "just seen". Reading a missing timestamp as zero idle
        // would keep a shell open precisely when nothing is polling it.
        $channel = new TerminalChannel(str_repeat('a', 48));

        $this->assertGreaterThan(TerminalChannel::IDLE_TIMEOUT, $channel->idleSeconds());
    }

    #[Test]
    public function closing_is_visible_to_the_other_side(): void
    {
        $channel = TerminalChannel::open(1, 2);
        $this->assertNull($channel->closedReason());

        $channel->close('idle');

        $this->assertSame('idle', (new TerminalChannel($channel->id))->closedReason());
    }

    #[Test]
    public function meta_binds_the_session_to_one_user_and_one_server(): void
    {
        $channel = TerminalChannel::open(7, 9);

        $meta = (new TerminalChannel($channel->id))->meta();
        $this->assertSame(7, $meta['user_id']);
        $this->assertSame(9, $meta['server_id']);

        $this->assertNull((new TerminalChannel(str_repeat('z', 48)))->meta());
    }
}
