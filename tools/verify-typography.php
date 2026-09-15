<?php

$css = file_get_contents(__DIR__ . '/../assets/css/typography.css');
$required = [
    '--type-scale', '--fs-micro', '--fs-label', '--fs-caption',
    '--fs-body-sm', '--fs-body', '--fs-body-lg', '--fs-subtitle',
    '--fs-heading', '--fs-title', '--fs-display', '--lh-body', '--lh-tight',
];
foreach ($required as $token) {
    if (substr_count($css, $token . ':') !== 1) {
        fwrite(STDERR, "FAIL: token {$token} must have one definition\n");
        exit(1);
    }
}

$semanticSizeTokens = [
    '--fs-micro', '--fs-label', '--fs-caption', '--fs-body-sm', '--fs-body',
    '--fs-body-lg', '--fs-subtitle', '--fs-heading', '--fs-title', '--fs-display',
];
foreach ($semanticSizeTokens as $token) {
    preg_match('/' . preg_quote($token, '/') . ':\\s*([^;]+);/', $css, $match);
    if (!isset($match[1]) || substr_count($match[1], 'var(--type-scale)') < 1) {
        fwrite(STDERR, "FAIL: token {$token} must use --type-scale\n");
        exit(1);
    }
}

preg_match('/--fs-heading:\\s*([^;]+);/', $css, $headingMatch);
if (isset($headingMatch[1]) && strpos($headingMatch[1], 'clamp(') !== false) {
    fwrite(STDERR, "FAIL: token --fs-heading must not use clamp()\n");
    exit(1);
}

foreach (['--fs-title', '--fs-display'] as $token) {
    preg_match('/' . preg_quote($token, '/') . ':\\s*([^;]+);/', $css, $match);
    if (!isset($match[1]) || strpos($match[1], 'clamp(') !== 0
        || substr_count($match[1], 'var(--type-scale)') !== 3) {
        fwrite(STDERR, "FAIL: token {$token} must scale every clamp() bound\n");
        exit(1);
    }
}

echo "PASS\n";
