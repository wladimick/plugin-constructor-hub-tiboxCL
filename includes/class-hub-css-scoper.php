<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prefixes every selector of a stylesheet with a scope class.
 *
 * HUB designs are dropped on top of live sites that still run a theme and
 * Elementor. An AI generated `nav a { color: #fff }` is legitimate inside its
 * own component and catastrophic when it leaks into the rest of the page.
 * Scoping is opt-in per design because existing designs were authored without
 * it and some deliberately style global elements.
 */
final class HUB_Tibox_Css_Scoper
{
    /** At-rules whose body contains nested rule sets and must be recursed into. */
    private const NESTED_AT_RULES = ['media', 'supports', 'container', 'layer', 'scope', 'document'];

    /** At-rules whose body is not a selector list and must be copied verbatim. */
    private const OPAQUE_AT_RULES = ['keyframes', 'font-face', 'font-feature-values', 'counter-style', 'page', 'property', 'viewport'];

    public static function scope(string $css, string $scope_selector): string
    {
        $scope_selector = trim($scope_selector);
        if ($css === '' || $scope_selector === '') {
            return $css;
        }

        return self::process_block($css, $scope_selector);
    }

    private static function process_block(string $css, string $scope): string
    {
        $output = '';
        $length = strlen($css);
        $prelude = '';
        $index = 0;

        while ($index < $length) {
            $char = $css[$index];

            // Comments and strings are copied without being interpreted.
            if ($char === '/' && ($css[$index + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $index + 2);
                $end = $end === false ? $length : $end + 2;
                $prelude .= substr($css, $index, $end - $index);
                $index = $end;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $end = self::string_end($css, $index);
                $prelude .= substr($css, $index, $end - $index);
                $index = $end;
                continue;
            }

            if ($char === ';') {
                // A statement without a block: @import, @charset, stray text.
                $output .= $prelude . ';';
                $prelude = '';
                $index++;
                continue;
            }

            if ($char === '}') {
                // Unbalanced closing brace: keep it and stop guessing.
                $output .= $prelude . '}';
                $prelude = '';
                $index++;
                continue;
            }

            if ($char !== '{') {
                $prelude .= $char;
                $index++;
                continue;
            }

            $body_start = $index + 1;
            $body_end = self::matching_brace($css, $index);
            $body = substr($css, $body_start, $body_end - $body_start);
            $index = $body_end + 1;

            $trimmed = trim($prelude);
            $prelude = '';

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '@')) {
                $at_rule = strtolower((string) strtok(substr($trimmed, 1), " \t\n\r({"));
                $at_rule = (string) preg_replace('/^-(webkit|moz|ms|o)-/', '', $at_rule);

                if (in_array($at_rule, self::OPAQUE_AT_RULES, true)) {
                    $output .= $trimmed . '{' . $body . '}';
                    continue;
                }

                if (in_array($at_rule, self::NESTED_AT_RULES, true)) {
                    $output .= $trimmed . '{' . self::process_block($body, $scope) . '}';
                    continue;
                }

                $output .= $trimmed . '{' . $body . '}';
                continue;
            }

            // Leading comments belong to the stylesheet, not to the selector.
            $leading = '';
            while (preg_match('#^\s*/\*.*?\*/#s', $trimmed, $match)) {
                $leading .= $match[0];
                $trimmed = substr($trimmed, strlen($match[0]));
            }
            $trimmed = trim($trimmed);

            if ($trimmed === '') {
                $output .= $leading . '{' . $body . '}';
                continue;
            }

            $output .= $leading . self::scope_selector_list($trimmed, $scope) . '{' . $body . '}';
        }

        return $output . $prelude;
    }

    private static function scope_selector_list(string $selectors, string $scope): string
    {
        $parts = self::split_selectors($selectors);
        $scoped = [];

        foreach ($parts as $selector) {
            $selector = trim($selector);
            if ($selector === '') {
                continue;
            }
            $scoped[] = self::scope_single($selector, $scope);
        }

        return implode(',', $scoped);
    }

    private static function scope_single(string $selector, string $scope): string
    {
        // Already scoped by the author.
        if (str_starts_with($selector, $scope)) {
            return $selector;
        }

        // Root level selectors become the scope itself so custom properties and
        // base styles land on the component wrapper instead of the document.
        $normalized = strtolower($selector);
        if (in_array($normalized, [':root', 'html', 'body', 'html body', ':host'], true)) {
            return $scope;
        }

        // `html body .z` and `:root body .z` both mean "inside the document":
        // strip every leading root token, not just the first one.
        $stripped = $selector;
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ([':root', 'html', 'body'] as $root) {
                if (stripos($stripped, $root . ' ') === 0) {
                    $stripped = ltrim(substr($stripped, strlen($root)));
                    $changed = true;
                }
            }
        }

        if ($stripped !== $selector) {
            return $stripped === '' ? $scope : $scope . ' ' . $stripped;
        }

        // Keyframe percentage selectors and `from`/`to` never reach here because
        // @keyframes is opaque, but a stray one must not be prefixed.
        if (preg_match('/^(\d+%|from|to)$/i', $selector)) {
            return $selector;
        }

        return $scope . ' ' . $selector;
    }

    /** @return string[] */
    private static function split_selectors(string $selectors): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($selectors);

        for ($i = 0; $i < $length; $i++) {
            $char = $selectors[$i];

            if ($char === '"' || $char === "'") {
                $end = self::string_end($selectors, $i);
                $current .= substr($selectors, $i, $end - $i);
                $i = $end - 1;
                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;
        return $parts;
    }

    private static function matching_brace(string $css, int $open): int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $i = self::string_end($css, $i) - 1;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length;
    }

    /** Index just after the closing quote. */
    private static function string_end(string $subject, int $start): int
    {
        $quote = $subject[$start];
        $length = strlen($subject);

        for ($i = $start + 1; $i < $length; $i++) {
            if ($subject[$i] === '\\') {
                $i++;
                continue;
            }
            if ($subject[$i] === $quote) {
                return $i + 1;
            }
        }

        return $length;
    }
}
