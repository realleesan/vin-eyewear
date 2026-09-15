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

echo "PASS\n";
