<?php

declare(strict_types=1);

namespace App\Support;

use Ytdlphp\Exception\YtDlpException;
use Ytdlphp\Metadata\VideoInfo;

/** Parses yt-dlp JSON without booting the ytdlphp package Tempest kernel (breaks in Docker). */
final class VideoInfoParser
{
    public static function fromJsonLine(string $json): VideoInfo
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new YtDlpException('Failed to parse yt-dlp JSON output.', previous: $exception);
        }

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): VideoInfo
    {
        return new VideoInfo(
            id: (string) ($data['id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            duration: is_numeric($data['duration'] ?? null) ? (float) $data['duration'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            uploader: isset($data['uploader']) ? (string) $data['uploader'] : null,
            raw: $data,
        );
    }
}
