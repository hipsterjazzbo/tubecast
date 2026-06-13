<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

final class TitleSanitizer
{
    public function sanitize(string $title, int $maxLength = 120): string
    {
        $sanitized = preg_replace('/[\/\\\\:*?"<>|]/', '', $title) ?? '';
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized)) ?? '';

        if ($sanitized === '') {
            return 'Untitled';
        }

        if (strlen($sanitized) > $maxLength) {
            $sanitized = rtrim(substr($sanitized, 0, $maxLength));
        }

        return $sanitized;
    }
}
