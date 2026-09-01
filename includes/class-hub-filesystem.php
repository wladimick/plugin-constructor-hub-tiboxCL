<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filesystem helpers constrained to the uploads directory.
 *
 * Every recursive delete in this plugin goes through here so a path bug cannot
 * remove anything outside `wp-content/uploads`.
 */
final class HUB_Tibox_Filesystem
{
    public static function uploads_basedir(): string
    {
        $upload = wp_upload_dir();
        return wp_normalize_path(trailingslashit((string) $upload['basedir']));
    }

    public static function is_inside_uploads(string $path): bool
    {
        $path = wp_normalize_path($path);
        $base = self::uploads_basedir();

        return $path !== '' && $base !== '' && str_starts_with(trailingslashit($path), $base);
    }

    public static function delete_directory(string $dir): bool
    {
        if (!self::is_inside_uploads($dir) || !is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::delete_directory($path);
                continue;
            }

            @unlink($path);
        }

        return @rmdir($dir);
    }

    /** Copies a directory tree, refusing to follow symlinks. */
    public static function copy_directory(string $source, string $target): bool
    {
        if (!is_dir($source) || !wp_mkdir_p($target)) {
            return false;
        }

        $items = scandir($source);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . '/' . $item;
            $dst = $target . '/' . $item;

            if (is_link($src)) {
                return false;
            }

            if (is_dir($src)) {
                if (!self::copy_directory($src, $dst)) {
                    return false;
                }
                continue;
            }

            if (!copy($src, $dst)) {
                return false;
            }
        }

        return true;
    }
}
