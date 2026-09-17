<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Early upload guard for HUB ZIP operations.
 *
 * The secure ZIP importer already limits extracted files and uncompressed size,
 * but wp_handle_upload() runs before extraction. This guard rejects an oversized
 * archive before WordPress stores it in the uploads directory, protecting disk,
 * request time and staging environments from unnecessarily large AI exports.
 */
final class HUB_Tibox_Package_Upload_Guard
{
    /** @var string[] */
    private const ACTIONS = [
        'hub_tibox_site_factory_create',
        'hub_tibox_redesign_page',
        'hub_tibox_import_package',
    ];

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('wp_handle_upload_prefilter', [$this, 'filter_upload']);
    }

    public static function size_allowed(int $size, int $max): bool
    {
        return $size > 0 && $max > 0 && $size <= $max;
    }

    /**
     * @param array<string,mixed> $file Upload payload from WordPress.
     * @return array<string,mixed>
     */
    public function filter_upload(array $file): array
    {
        // This filter only scopes validation to a previously registered admin
        // action; the corresponding handlers perform their own nonce checks.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $action = isset($_POST['action'])
            ? sanitize_key(wp_unslash((string) $_POST['action']))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!in_array($action, self::ACTIONS, true)) {
            return $file;
        }

        $name = sanitize_file_name((string) ($file['name'] ?? ''));
        if ($name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            $file['error'] = 'Constructor HUB solo acepta archivos .zip en este flujo.';
            return $file;
        }

        $size = absint($file['size'] ?? 0);
        $max = (int) apply_filters(
            'constructor_hub_zip_max_compressed_bytes',
            25 * MB_IN_BYTES,
            0
        );

        if (!self::size_allowed($size, $max)) {
            $file['error'] = sprintf(
                'El ZIP está vacío o supera el máximo permitido de %s.',
                size_format($max)
            );
        }

        return $file;
    }
}
