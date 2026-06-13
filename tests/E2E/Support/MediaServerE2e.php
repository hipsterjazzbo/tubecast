<?php

declare(strict_types=1);

namespace Tests\E2E\Support;

use App\Commands\Handlers\NotifyMediaServerCommandHandler;
use App\Commands\NotifyMediaServerCommand;
use Tempest\CommandBus\CommandRepository;

final class MediaServerE2e
{
    public static function runPendingNotifyCommands(
        CommandRepository $repository,
        NotifyMediaServerCommandHandler $handler,
    ): void {
        foreach ($repository->getPendingCommands() as $command) {
            if ($command instanceof NotifyMediaServerCommand) {
                $handler->__invoke($command);
            }
        }
    }

    public static function placeTvEpisodeFile(string $showTitle, int $episode, string $episodeTitle, string $ytId): string
    {
        $show = self::sanitizeTitle($showTitle);
        $title = self::sanitizeTitle($episodeTitle);
        $directory = rtrim(getenv('VIDEO_PATH') ?: '/tmp/tubecast-test/video', '/')
            . '/' . $show . '/Season 01';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = sprintf(
            '%s - s01e%02d - %s [%s].mp4',
            $show,
            $episode,
            $title,
            $ytId,
        );
        $path = $directory . '/' . $filename;
        file_put_contents($path, 'tubecast-e2e-video-' . $ytId);

        return $path;
    }

    private static function sanitizeTitle(string $title): string
    {
        $sanitized = preg_replace('/[\\\\\\/:*?"<>|]+/', ' ', $title) ?? $title;
        $sanitized = preg_replace('/\\s+/', ' ', trim($sanitized)) ?? $sanitized;

        return $sanitized !== '' ? $sanitized : 'Untitled';
    }
}
