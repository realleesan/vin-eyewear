<?php

$source = file_get_contents(__DIR__ . '/../assets/css/typography.css');

function assertRejected(string $name, string $css, string $expectedFailure): void
{
    $fixture = tempnam(sys_get_temp_dir(), 'verify-typography-');
    file_put_contents($fixture, $css);

    $command = escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__DIR__ . '/verify-typography.php') . ' '
        . escapeshellarg($fixture) . ' 2>&1';
    exec($command, $output, $exitCode);
    unlink($fixture);

    $result = implode("\n", $output);
    if ($exitCode === 0 || strpos($result, $expectedFailure) === false) {
        fwrite(STDERR, "FAIL: {$name} bypass was not rejected as expected\n");
        exit(1);
    }

    echo "PASS: {$name} rejected\n";
}

$uppercaseClamp = preg_replace(
    '/--fs-heading:\\s*[^;]+;/',
    '--fs-heading: CLAMP(calc(1.25rem * var(--type-scale)), calc(1.5rem * var(--type-scale)), calc(1.75rem * var(--type-scale)));',
    $source,
    1,
    $uppercaseClampCount,
);
if ($uppercaseClampCount !== 1) {
    fwrite(STDERR, "FAIL: could not create uppercase CLAMP mutation\n");
    exit(1);
}
assertRejected(
    'uppercase CLAMP heading',
    $uppercaseClamp,
    'FAIL: token --fs-heading must not use clamp()',
);

$commentScale = str_replace(
    'calc(3rem * var(--type-scale))',
    'calc(3rem) /* var(--type-scale) */',
    $source,
    $commentScaleCount,
);
if ($commentScaleCount !== 1) {
    fwrite(STDERR, "FAIL: could not create comment scale mutation\n");
    exit(1);
}
assertRejected(
    'commented title scale',
    $commentScale,
    'FAIL: token --fs-title must scale every clamp() bound',
);

echo "PASS\n";
