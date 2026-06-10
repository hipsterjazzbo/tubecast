<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Source\MediaItemPresenter;
use App\Services\Core\ModelId;
use Tempest\Database\Direction;
use App\Middleware\RequireAuthMiddleware;
use Tempest\Router\Get;
use Tempest\Router\WithMiddleware;
use Tempest\View\View;

use function Tempest\View\view;

#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class DashboardController
{
    #[Get('/')]
    public function __invoke(): View
    {
        $recentItems = MediaItem::select()->orderBy('id', Direction::DESC)->limit(10)->all();
        $recentRows = [];

        foreach ($recentItems as $item) {
            $presentation = MediaItemPresenter::for($item);
            $recentRows[] = (object) [
                'title' => $item->title ?? $item->ytId,
                'sourceId' => ModelId::int($item->sourceId),
                'thumbnailUrl' => $presentation->thumbnailUrl,
                'statusLabel' => $presentation->statusLabel,
                'statusColorClass' => $presentation->statusColorClass,
                'durationLabel' => $presentation->durationLabel,
            ];
        }

        return view('views/dashboard.view.php', ...[
            'sourceCount' => Source::count()->execute(),
            'mediaCount' => MediaItem::count()->execute(),
            'completedCount' => MediaItem::count()->where('status = ?', MediaItemStatus::Completed->value)->execute(),
            'failedCount' => MediaItem::count()->where('status = ?', MediaItemStatus::Failed->value)->execute(),
            'recentRows' => $recentRows,
        ]);
    }
}
