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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/htmx.org@2.0.4"></script>
    <style>
        @keyframes tc-bar-slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(400%); }
        }
        .tc-bar-indeterminate {
            width: 35%;
            animation: tc-bar-slide 1.4s ease-in-out infinite;
        }
    </style>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
<x-slot/>
<x-slot name="scripts"/>
</body>
</html>
