<?php

/**
 * Reads the shared typography tokens for contexts that cannot load the main
 * stylesheet directly, such as transactional email HTML.
 */
final class Typography
{
    /** @var array<string, string>|null */
    private static ?array $tokens = null;
    private static float $scale = 1.0;

    public static function size(string $token): string
    {
        self::load();
        $value = self::$tokens[$token] ?? 'var(--fs-body)';

        // Transactional email clients do not consistently support CSS custom
        // properties. Resolve the shared rem token while keeping typography.css
        // as the only numeric source of truth.
        if (preg_match('/^calc\(\s*([0-9.]+)rem\s*\*\s*var\(--type-scale\)\s*\)$/', $value, $match)) {
            $rem = (float) $match[1] * self::$scale;
            return rtrim(rtrim(number_format($rem, 4, '.', ''), '0'), '.') . 'rem';
        }

        return $value;
    }

    private static function load(): void
    {
        if (self::$tokens !== null) {
            return;
        }

        $path = defined('ROOT_PATH')
            ? ROOT_PATH . '/assets/css/typography.css'
            : dirname(__DIR__) . '/assets/css/typography.css';
        $css = file_get_contents($path);
        self::$tokens = [];

        if ($css === false) {
            return;
        }

        preg_match_all('/(--fs-[a-z-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            self::$tokens[$match[1]] = trim($match[2]);
        }

        if (preg_match('/--type-scale\s*:\s*([0-9.]+)\s*;/', $css, $scaleMatch)) {
            self::$scale = (float) $scaleMatch[1];
        }
    }
}
