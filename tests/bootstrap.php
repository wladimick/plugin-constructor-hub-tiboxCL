<?php
/**
 * Minimal harness for pure-PHP unit tests.
 *
 * Constructor HUB has no WordPress test suite yet. These tests cover the pieces
 * that are plain algorithms — CSS scoping, path validation, SVG sanitising —
 * which is exactly where regressions are silent and expensive.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

final class Hub_Test
{
    public static int $passed = 0;
    /** @var string[] */
    public static array $failures = [];

    public static function assert_same(string $name, $expected, $actual): void
    {
        if ($expected === $actual) {
            self::$passed++;
            return;
        }

        self::$failures[] = sprintf(
            "%s\n    expected: %s\n    actual:   %s",
            $name,
            var_export($expected, true),
            var_export($actual, true)
        );
    }

    public static function assert_true(string $name, $value): void
    {
        self::assert_same($name, true, (bool) $value);
    }

    public static function assert_false(string $name, $value): void
    {
        self::assert_same($name, false, (bool) $value);
    }

    public static function summary(): int
    {
        if (self::$failures === []) {
            printf("OK — %d assertions passed\n", self::$passed);
            return 0;
        }

        printf("FAILED — %d passed, %d failed\n\n", self::$passed, count(self::$failures));
        foreach (self::$failures as $failure) {
            echo '  ' . $failure . "\n\n";
        }

        return 1;
    }
}
