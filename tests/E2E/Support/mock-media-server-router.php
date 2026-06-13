<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in web server used in media-server E2E tests.
 * Logs every request as JSON (one object per line) to MOCK_MEDIA_SERVER_LOG.
 */

$logFile = getenv('MOCK_MEDIA_SERVER_LOG');

if (! is_string($logFile) || $logFile === '') {
    http_response_code(500);
    echo 'MOCK_MEDIA_SERVER_LOG is not set';

    return true;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

file_put_contents($logFile, json_encode([
    'method' => $method,
    'uri' => $uri,
    'path' => $path,
    'query' => $_GET,
], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);

header('Content-Type: application/json');

if ($method === 'GET' && $path === '/library/sections') {
    echo json_encode([
        'MediaContainer' => [
            'Directory' => [[
                'key' => '42',
                'title' => 'E2E TV Library',
                'type' => 'show',
                'Location' => [['path' => '/mock/nas/e2e-tv']],
            ], [
                'key' => '7',
                'title' => 'E2E Music Library',
                'type' => 'artist',
                'Location' => [['path' => '/mock/nas/e2e-music']],
            ]],
        ],
    ], JSON_THROW_ON_ERROR);

    return true;
}

if ($method === 'GET' && preg_match('#^/library/sections/[^/]+/refresh$#', $path) === 1) {
    echo '{}';

    return true;
}

if ($method === 'GET' && $path === '/Library/VirtualFolders') {
    echo json_encode([[
        'ItemId' => 'jf-show-1',
        'Name' => 'E2E Jellyfin TV',
        'CollectionType' => 'tvshows',
        'Locations' => ['/mock/jellyfin/e2e-tv'],
    ]], JSON_THROW_ON_ERROR);

    return true;
}

if ($method === 'POST' && $path === '/Library/Media/Updated') {
    http_response_code(204);

    return true;
}

http_response_code(404);
echo json_encode(['error' => 'not found'], JSON_THROW_ON_ERROR);

return true;
