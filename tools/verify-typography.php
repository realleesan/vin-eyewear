<?php

function declarationValue(string $css, string $token): ?string
{
    preg_match('/' . preg_quote($token, '/') . ':\\s*([^;]+);/', $css, $match);

    return isset($match[1])
        ? preg_replace('/\\/\\*.*?\\*\\//s', '', $match[1])
        : null;
}

function clampBounds(string $value): ?array
{
    $value = trim($value);
    if (!preg_match('/^clamp\\s*\\(/i', $value)) {
        return null;
    }

    $openParenthesis = strpos($value, '(');
    if ($openParenthesis === false || substr($value, -1) !== ')') {
        return null;
    }

    $inside = substr($value, $openParenthesis + 1, -1);
    $bounds = [''];
    $depth = 0;

    foreach (str_split($inside) as $character) {
        if ($character === '(') {
            $depth++;
        } elseif ($character === ')') {
            $depth--;
        }

        if ($character === ',' && $depth === 0) {
            $bounds[] = '';
            continue;
        }

        $bounds[array_key_last($bounds)] .= $character;
    }

    if ($depth !== 0 || count($bounds) !== 3) {
        return null;
    }

    return array_map('trim', $bounds);
}

function usesTypeScale(string $value): bool
{
    return preg_match('/\\bvar\\s*\\(\\s*--type-scale\\s*\\)/i', $value) === 1;
}

function typographyError(string $css): ?string
{
    $css = preg_replace('/\\/\\*.*?\\*\\//s', '', $css);
    $required = [
        '--type-scale', '--fs-micro', '--fs-label', '--fs-caption',
        '--fs-body-sm', '--fs-body', '--fs-body-lg', '--fs-subtitle',
        '--fs-heading', '--fs-title', '--fs-display', '--lh-body', '--lh-tight',
    ];
    foreach ($required as $token) {
        if (substr_count($css, $token . ':') !== 1) {
            return "FAIL: token {$token} must have one definition";
        }
    }

    $semanticSizeTokens = [
        '--fs-micro', '--fs-label', '--fs-caption', '--fs-body-sm', '--fs-body',
        '--fs-body-lg', '--fs-subtitle', '--fs-heading', '--fs-title', '--fs-display',
    ];
    foreach ($semanticSizeTokens as $token) {
        $value = declarationValue($css, $token);
        if ($value === null || !usesTypeScale($value)) {
            return "FAIL: token {$token} must use --type-scale";
        }
    }

    $heading = declarationValue($css, '--fs-heading');
    if ($heading !== null && preg_match('/\\bclamp\\s*\\(/i', $heading)) {
        return 'FAIL: token --fs-heading must not use clamp()';
    }

    foreach (['--fs-title', '--fs-display'] as $token) {
        $bounds = clampBounds(declarationValue($css, $token) ?? '');
        if ($bounds === null || count(array_filter($bounds, 'usesTypeScale')) !== 3) {
            return "FAIL: token {$token} must scale every clamp() bound";
        }
    }

    return null;
}

function literalFontSizeErrors(string $css, string $path): array
{
    $allowed = [
        // Zero removes inline whitespace without rendering text.
        '/^font-size\s*:\s*0\s*;$/i',
        // Zero-pixel declarations serve the same non-text layout purpose.
        '/^font-size\s*:\s*0px\s*;$/i',
    ];
    $css = preg_replace_callback(
        '/\/\*.*?\*\//s',
        static fn(array $match): string => preg_replace('/[^\r\n]/', ' ', $match[0]),
        $css,
    );
    $errors = [];
    $declarationPattern = '/font-size\s*:\s*([^;]+);/i';
    preg_match_all($declarationPattern, $css, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $index => $declarationMatch) {
        $declaration = $declarationMatch[0];
        foreach ($allowed as $allowedPattern) {
            if (preg_match($allowedPattern, $declaration)) {
                continue 2;
            }
        }

        $value = $matches[1][$index][0];
        if (containsLiteralTextSize($value)) {
            $errors[] = $path . ':' . physicalLineNumber($css, $declarationMatch[1]);
        }
    }

    return $errors;
}

function containsLiteralTextSize(string $value): bool
{
    $number = '[+-]?(?:(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eE][+-]?\\d+)?)';
    $units = '(?:%|r?em|ex|ch|cap|ic|lh|rlh|px|q|in|cm|mm|pt|pc|'
        . 'vw|vh|vi|vb|vmin|vmax|svw|svh|svi|svb|svmin|svmax|'
        . 'lvw|lvh|lvi|lvb|lvmin|lvmax|dvw|dvh|dvi|dvb|dvmin|dvmax|'
        . 'cqw|cqh|cqi|cqb|cqmin|cqmax)';
    if (preg_match('/(?<![\\w-])' . $number . $units . '(?![a-z])/i', $value)) {
        return true;
    }

    if (preg_match('/^\\s*' . $number . '\\s*$/', $value)) {
        return true;
    }

    return preg_match('/\\b(?:calc|min|max|clamp)\\s*\\(/i', $value) === 1
        && preg_match('/(?<![\\w.-])' . $number . '(?![\\w.-])/', $value) === 1;
}

function physicalLineNumber(string $css, int $offset): int
{
    return preg_match_all('/\r\n|\n|\r/', substr($css, 0, $offset)) + 1;
}

function cssPaths(string $directory): array
{
    $paths = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($files as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

$sample = '.sample { font-size: 13px; }';
if (!preg_match('/font-size\s*:\s*\d+(?:\.\d+)?px\s*;/', $sample)) {
    fwrite(STDERR, "FAIL: checker did not detect literal text size\n");
    exit(1);
}

if (literalFontSizeErrors($sample, 'sample.css') !== ['sample.css:1']) {
    fwrite(STDERR, "FAIL: checker did not report literal text size location\n");
    exit(1);
}

$cssPath = $argv[1] ?? __DIR__ . '/../assets/css/typography.css';
$css = file_get_contents($cssPath);
if ($css === false) {
    fwrite(STDERR, "FAIL: unable to read {$cssPath}\n");
    exit(1);
}

$error = typographyError($css);
if ($error !== null) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

$projectRoot = realpath(__DIR__ . '/..');
$literalSizeErrors = [];
foreach (cssPaths($projectRoot . '/assets/css') as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }

    $relativePath = substr($path, strlen($projectRoot) + 1);
    $literalSizeErrors = array_merge(
        $literalSizeErrors,
        literalFontSizeErrors($contents, $relativePath),
    );
}

if ($literalSizeErrors !== []) {
    fwrite(STDERR, implode("\n", $literalSizeErrors) . "\n");
    exit(1);
}

echo "PASS\n";
