<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;
use App\Support\DiskTempFile;
use App\Support\OutboundUrl;
use App\Support\TerminalChannel;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * An interactive shell on a monitored host, bridged to a browser.
 *
 * This is the one place in the module where a command does NOT come from a
 * fixed script: the whole point is that the person at the keyboard decides. The
 * compensations that make that defensible live at the edges — a password
 * step-up on every open, a session that cannot outlive its ceiling, an idle
 * timeout, and the fact that a key restricted with a forced command cannot open
 * a shell at all. See the security register.
 *
 * Runs only in the queue worker. Never call it from a request.
 */
class ServerTerminal
{
    /** How long to wait between pumping the two directions. Fast enough to feel live. */
    private const TICK_US = 25_000;

    public function __construct(private ServerProbe $probe) {}

    public function run(Server $server, TerminalChannel $channel, int $cols, int $rows): void
    {
        if (! OutboundUrl::hostAllowed($server->host)) {
            $channel->close('unsafe_host');

            return;
        }

        $hostKey = (string) $server->host_key;
        if ($hostKey === '') {
            $channel->close('no_host_key');

            return;
        }

        $target = ServerTarget::fromServer($server);
        $pem = $this->probe->privateKeyFor($target);
        if ($pem === null) {
            $channel->close('no_credentials');

            return;
        }

        // RAII: both files are unlinked when this method returns, so the key is
        // on disk only while the session is actually open.
        $keyFile = DiskTempFile::create('ll-termkey');
        $knownHosts = DiskTempFile::create('ll-termhosts');

        try {
            file_put_contents($keyFile->path(), $pem);
            chmod($keyFile->path(), 0600);
            file_put_contents($knownHosts->path(), $this->probe->knownHostsFor($target, $hostKey));

            $input = new InputStream;
            $process = new Process($this->argv($target, $keyFile->path(), $knownHosts->path()));
            $process->setInput($input);
            // The channel governs the lifetime, not Symfony: an interactive
            // session has no natural runtime to guess at.
            $process->setTimeout(null);
            $process->start();

            $channel->markReady();
            // ssh gives the remote shell 80x24 because there is no local terminal
            // to take a size from. Ask for the real one, then clear so the reader
            // does not start their session looking at our housekeeping.
            $input->write(sprintf("stty rows %d cols %d 2>/dev/null; clear\n", $rows, $cols));

            $this->pump($process, $input, $channel);
        } catch (Throwable $e) {
            $channel->close('failed');
        } finally {
            $channel->close($channel->closedReason() ?? 'closed');
        }
    }

    /**
     * Move bytes in both directions until somebody stops caring.
     */
    private function pump(Process $process, InputStream $input, TerminalChannel $channel): void
    {
        $inputCursor = 0;
        $started = time();

        while (true) {
            if (! $process->isRunning()) {
                $channel->close('exited');
                break;
            }
            if ($channel->closedReason() !== null) {
                break;
            }
            // Two independent ceilings. The idle one is what actually ends most
            // sessions: a closed browser tab stops polling, and a shell nobody is
            // watching should not stay open on someone's server.
            if (time() - $started > TerminalChannel::MAX_LIFETIME) {
                $channel->close('expired');
                break;
            }
            if ($channel->idleSeconds() > TerminalChannel::IDLE_TIMEOUT) {
                $channel->close('idle');
                break;
            }

            $pending = $channel->readInput($inputCursor);
            if ($pending['data'] !== '') {
                $input->write($pending['data']);
                $inputCursor = $pending['cursor'];
            }

            // Both streams: a shell writes prompts and errors to either, and the
            // reader wants them interleaved as they would be on a real terminal.
            $out = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();
            if ($out !== '') {
                $channel->pushOutput($out);
            }

            usleep(self::TICK_US);
        }

        $input->close();
        // Give the shell a moment to notice, then insist.
        $process->stop(2);
    }

    /**
     * @return list<string>
     */
    private function argv(ServerTarget $target, string $keyPath, string $knownHostsPath): array
    {
        return [
            'ssh',
            // -tt forces a pty on the remote side even though our stdin is a
            // pipe. Without it there is no line editing, no job control and no
            // prompt — a shell, but not a terminal.
            '-tt',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile='.$knownHostsPath,
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'IdentityAgent=none',
            '-o', 'PasswordAuthentication=no',
            '-o', 'KbdInteractiveAuthentication=no',
            // No forwarding of any kind: this session is a shell on that host and
            // must not become a route into or out of it.
            '-o', 'ClearAllForwardings=yes',
            '-o', 'ConnectTimeout=8',
            '-o', 'ServerAliveInterval=20',
            '-o', 'LogLevel=ERROR',
            '-i', $keyPath,
            '-p', (string) $target->port,
            $target->username.'@'.$target->host,
        ];
    }
}
