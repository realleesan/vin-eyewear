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

function assertLiteralSizeLocations(string $name, string $css, array $expectedLines): void
{
    $cssDirectory = __DIR__ . '/../assets/css';
    $temporaryPath = tempnam($cssDirectory, 'verify-typography-');
    $fixturePath = $temporaryPath . '.css';
    rename($temporaryPath, $fixturePath);
    file_put_contents($fixturePath, $css);

    $command = escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__DIR__ . '/verify-typography.php') . ' 2>&1';
    exec($command, $output, $exitCode);
    unlink($fixturePath);

    $relativePath = 'assets/css/' . basename($fixturePath);
    preg_match_all(
        '/^' . preg_quote($relativePath, '/') . ':(\d+)$/m',
        implode("\n", $output),
        $matches,
    );
    $actualLines = array_map('intval', $matches[1]);
    $expectsFailure = $expectedLines !== [];
    if (($expectsFailure && $exitCode === 0)
        || (!$expectsFailure && $exitCode !== 0)
        || $actualLines !== $expectedLines) {
        fwrite(STDERR, "FAIL: {$name} locations were not reported as expected\n");
        exit(1);
    }

    echo "PASS: {$name} locations reported\n";
}

$missingClamp = preg_replace(
    '/--fs-2xl:\\s*[^;]+;/',
    '--fs-2xl: 32px;',
    $source,
    1,
    $missingClampCount,
);
if ($missingClampCount !== 1) {
    fwrite(STDERR, "FAIL: could not create missing clamp mutation\n");
    exit(1);
}
assertRejected(
    'missing 2xl clamp',
    $missingClamp,
    'FAIL: token --fs-2xl must use clamp()',
);

$missingDisplayClamp = preg_replace(
    '/--fs-3xl:\\s*[^;]+;/',
    '--fs-3xl: 64px;',
    $source,
    1,
    $missingDisplayClampCount,
);
if ($missingDisplayClampCount !== 1) {
    fwrite(STDERR, "FAIL: could not create missing 3xl clamp mutation\n");
    exit(1);
}
assertRejected(
    'missing 3xl clamp',
    $missingDisplayClamp,
    'FAIL: token --fs-3xl must use clamp()',
);

assertLiteralSizeLocations(
    'literal size syntax and physical line mapping',
    ".one { font-size: .92em; }\xC2\x85.two { font-size: calc(14px * var(--scale)); }\r\n"
        . ".three { font-size: calc(6px * var(--scale)); }\r"
        . ".four { font-size: calc(13px * var(--scale)); }\n"
        . ".five { font-size: clamp(12px, 2vw, 18px); }\n"
        . ".six { font-size: 100%; }\n"
        . ".seven { font-size: 0em; }\n"
        . ".eight { font-size: 1e1px; }\n",
    [1, 1, 2, 3, 4, 5, 6, 7],
);

assertLiteralSizeLocations(
    'exact zero-size exceptions',
    ".zero { font-size: 0; }\n.zero-px { font-size: 0px; }\n",
    [],
);

echo "PASS\n";
