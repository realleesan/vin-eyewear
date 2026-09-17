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
        $value = self::$tokens[$token] ?? '16px';
        // Recursively resolve var(--...) aliases so email clients get concrete values
        while (preg_match('/^var\(\s*(--[a-z0-9_-]+)\s*\)$/i', $value, $aliasMatch)) {
            $aliasToken = $aliasMatch[1];
            if (!isset(self::$tokens[$aliasToken]) || self::$tokens[$aliasToken] === $value) {
                break;
            }
            $value = self::$tokens[$aliasToken];
        }

        // Resolve simple calc(var(...) [+-] Xpx) expressions
        if (preg_match('/^calc\(\s*var\(\s*(--[a-z0-9_-]+)\s*\)\s*([+-])\s*(\d+(?:\.\d+)?px)\s*\)$/i', $value, $calcMatch)) {
            $baseVal = self::size($calcMatch[1]);
            if (preg_match('/^(\d+(?:\.\d+)?)px$/i', $baseVal, $numMatch)) {
                $baseNum = (float) $numMatch[1];
                $diff = (float) $calcMatch[3];
                $res = $calcMatch[2] === '-' ? ($baseNum - $diff) : ($baseNum + $diff);
                return $res . 'px';
            }
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

        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        preg_match_all('/(--fs-[a-z0-9_-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            self::$tokens[$match[1]] = trim($match[2]);
        }

        if (preg_match('/--type-scale\s*:\s*([0-9.]+)\s*;/', $css, $scaleMatch)) {
            self::$scale = (float) $scaleMatch[1];
        }
    }
}
