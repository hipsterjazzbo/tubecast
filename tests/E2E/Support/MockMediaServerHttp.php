<?php

declare(strict_types=1);

namespace Tests\E2E\Support;

use Symfony\Component\Process\Process;

final class MockMediaServerHttp
{
    private ?Process $process = null;

    private string $logFile = '';

    private int $port = 0;

    public function start(): void
    {
        if ($this->process !== null) {
            return;
        }

        $this->logFile = sys_get_temp_dir() . '/tubecast-media-server-' . uniqid('', true) . '.log';
        file_put_contents($this->logFile, '');

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new \RuntimeException('Could not bind mock media server port: ' . $errorMessage, $errorCode);
        }

        $address = stream_socket_get_name($socket, false);

        if (! is_string($address) || ! str_contains($address, ':')) {
            fclose($socket);

            throw new \RuntimeException('Could not resolve mock media server port.');
        }

        $parts = explode(':', $address, 2);
        $this->port = (int) ($parts[1] ?? 0);
        fclose($socket);

        $router = __DIR__ . '/mock-media-server-router.php';

        $this->process = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $this->port,
            $router,
        ]);
        $this->process->setEnv([
            'MOCK_MEDIA_SERVER_LOG' => $this->logFile,
        ]);
        $this->process->start();

        usleep(150_000);

        $this->waitUntilReady();
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        $this->process->stop(0, SIGTERM);
        $this->process = null;

        if ($this->logFile !== '' && is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /** @return list<array{method: string, uri: string, path: string, query: array<string, mixed>}> */
    public function requests(): array
    {
        if ($this->logFile === '' || ! is_file($this->logFile)) {
            return [];
        }

        $requests = [];

        foreach (file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            /** @var array{method: string, uri: string, path: string, query: array<string, mixed>} $decoded */
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $requests[] = $decoded;
        }

        return $requests;
    }

    public function clearRequests(): void
    {
        if ($this->logFile !== '') {
            file_put_contents($this->logFile, '');
        }
    }

    /** @param callable(array{method: string, uri: string, path: string, query: array<string, mixed>}): bool $matcher */
    public function assertRequestReceived(callable $matcher, string $message = ''): void
    {
        foreach ($this->requests() as $request) {
            if ($matcher($request)) {
                expect(true)->toBeTrue();

                return;
            }
        }

        $logged = json_encode($this->requests(), JSON_PRETTY_PRINT);
        $detail = $message !== '' ? $message . ' ' : '';

        throw new \RuntimeException($detail . 'Expected HTTP request was not received. Logged requests: ' . $logged);
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 10.0;

        while (microtime(true) < $deadline) {
            if ($this->process !== null && ! $this->process->isRunning()) {
                throw new \RuntimeException(
                    'Mock media server exited early: ' . trim($this->process->getErrorOutput() . ' ' . $this->process->getOutput()),
                );
            }

            $response = $this->probeHttp('/library/sections');

            if ($response !== null && str_contains($response, 'MediaContainer')) {
                $this->clearRequests();

                return;
            }

            usleep(100_000);
        }

        throw new \RuntimeException('Mock media server did not become ready on ' . $this->baseUrl());
    }

    private function probeHttp(string $path): ?string
    {
        set_error_handler(static fn (): bool => true);

        try {
            $socket = stream_socket_client(
                'tcp://127.0.0.1:' . $this->port,
                $errorCode,
                $errorMessage,
                1,
            );
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            return null;
        }

        $request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n";
        fwrite($socket, $request);
        $response = stream_get_contents($socket);
        fclose($socket);

        if (! is_string($response)) {
            return null;
        }

        $bodySeparator = strpos($response, "\r\n\r\n");

        if ($bodySeparator === false) {
            return null;
        }

        return substr($response, $bodySeparator + 4);
    }
}
