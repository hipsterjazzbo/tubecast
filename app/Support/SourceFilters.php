<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DownloadMode;
use App\Models\Source;

final readonly class SourceFilters
{
    public function __construct(
        public DownloadMode $downloadMode = DownloadMode::Auto,
        public ?int $minDurationSeconds = null,
        public ?int $maxDurationSeconds = null,
        public ?string $titleRegex = null,
    ) {
    }

    public static function fromSource(Source $source): self
    {
        return self::fromJson($source->filtersJson);
    }

    public static function fromJson(?string $json): self
    {
        if ($json === null || trim($json) === '') {
            return new self();
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new self();
        }

        $mode = DownloadMode::tryFrom((string) ($data['downloadMode'] ?? '')) ?? DownloadMode::Auto;

        return new self(
            downloadMode: $mode,
            minDurationSeconds: self::nullableInt($data['minDurationSeconds'] ?? null),
            maxDurationSeconds: self::nullableInt($data['maxDurationSeconds'] ?? null),
            titleRegex: self::nullableNonEmptyString($data['titleRegex'] ?? null),
        );
    }

    /** @param array<string, mixed> $input */
    public static function fromForm(array $input): self
    {
        $mode = DownloadMode::tryFrom((string) ($input['downloadMode'] ?? '')) ?? DownloadMode::Auto;
        $titleRegex = trim((string) ($input['titleRegex'] ?? ''));

        if ($titleRegex !== '' && @preg_match($titleRegex, '') === false) {
            $titleRegex = '';
        }

        return new self(
            downloadMode: $mode,
            minDurationSeconds: self::minutesToSeconds($input['minDurationMinutes'] ?? null),
            maxDurationSeconds: self::minutesToSeconds($input['maxDurationMinutes'] ?? null),
            titleRegex: $titleRegex !== '' ? $titleRegex : null,
        );
    }

    public function toJson(): string
    {
        $data = array_filter([
            'downloadMode' => $this->downloadMode->value,
            'minDurationSeconds' => $this->minDurationSeconds,
            'maxDurationSeconds' => $this->maxDurationSeconds,
            'titleRegex' => $this->titleRegex,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($data === []) {
            return '';
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function minDurationMinutes(): ?int
    {
        return $this->minDurationSeconds !== null ? intdiv($this->minDurationSeconds, 60) : null;
    }

    public function maxDurationMinutes(): ?int
    {
        return $this->maxDurationSeconds !== null ? intdiv($this->maxDurationSeconds, 60) : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableNonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function minutesToSeconds(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $minutes = (int) $value;

        return $minutes > 0 ? $minutes * 60 : null;
    }
}
