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

echo "PASS\n";
