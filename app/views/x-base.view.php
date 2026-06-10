<?php

/**
 * @var string|null $title The webpage's title
 */
?>

<!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <title>{{ $title ?? 'TubeCast' }}</title>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <x-slot name="head"/>
    <x-vite-tags />
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
<x-slot/>
<x-slot name="scripts"/>
</body>
</html>
