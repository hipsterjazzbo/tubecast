<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Config\TubecastConfig;
use Symfony\Component\Process\Process;

final class DependencyChecker
{
    public function __construct(
        private TubecastConfig   $config,
        private BootstrapService $bootstrap,
    )
    {
    }

    public function missingRequired(): bool
    {
        foreach ($this->checkAll() as $status) {
            if ($status->required() && !$status->available) {
                return true;
            }
        }

        return false;
    }

    /** @return list<DependencyStatus> */
    public function checkAll(): array
    {
        return [
            $this->checkYtDlp(),
            $this->checkBinary(ExternalDependency::Ffmpeg, 'ffmpeg'),
            $this->checkBinary(ExternalDependency::Ffprobe, 'ffprobe'),
            $this->checkBinary(ExternalDependency::Python3, 'python3'),
            $this->checkBinary(ExternalDependency::Deno, 'deno'),
        ];
    }

    public function checkYtDlp(): DependencyStatus
    {
        foreach ($this->ytDlpCandidates() as $candidate) {
            $status = $this->probe(ExternalDependency::YtDlp, $candidate);

            if ($status->available) {
                return $status;
            }
        }

        return new DependencyStatus(
            dependency: ExternalDependency::YtDlp,
            available: false,
            path: null,
            version: null,
        );
    }

    /** @return list<string> */
    private function ytDlpCandidates(): array
    {
        $configured = $this->config->ytDlpBinary;
        $candidates = [$configured, $this->bootstrap->ytDlpInstallPath(), 'yt-dlp'];

        return array_values(array_unique($candidates));
    }

    private function probe(ExternalDependency $dependency, string $binary): DependencyStatus
    {
        $command = $this->resolveCommand($binary);

        if ($command === null) {
            return new DependencyStatus(
                dependency: $dependency,
                available: false,
                path: $binary,
                version: null,
            );
        }

        $process = new Process([$command, '--version']);
        $process->setTimeout(15);
        $process->run();

        if (!$process->isSuccessful()) {
            return new DependencyStatus(
                dependency: $dependency,
                available: false,
                path: $command,
                version: null,
            );
        }

        $version = trim($process->getOutput() !== '' ? $process->getOutput() : $process->getErrorOutput());
        $version = $version !== '' ? strtok($version, "\n") : null;

        return new DependencyStatus(
            dependency: $dependency,
            available: true,
            path: $command,
            version: $version !== false ? $version : null,
        );
    }

    private function resolveCommand(string $binary): ?string
    {
        if ($binary === '') {
            return null;
        }

        if (str_contains($binary, '/')) {
            return is_executable($binary) ? $binary : null;
        }

        return $this->which($binary);
    }

    private function which(string $binary): ?string
    {
        $process = new Process(['sh', '-c', 'command -v ' . escapeshellarg($binary)]);
        $process->setTimeout(5);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $path = trim($process->getOutput());

        return $path !== '' && is_executable($path) ? $path : null;
    }

    public function checkBinary(ExternalDependency $dependency, string $binary): DependencyStatus
    {
        return $this->probe($dependency, $binary);
    }
}
