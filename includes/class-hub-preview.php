<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Signed preview of a version that is not published.
 *
 * A preview link has to be shareable with a client who has no WordPress account,
 * so it cannot rely on the session. It is a short lived HMAC over the version id
 * and the expiry, and previewed URLs always emit noindex.
 */
final class HUB_Tibox_Preview
{
    public const QUERY_VERSION = 'hub_preview_version';
    public const QUERY_EXPIRES = 'hub_preview_expires';
    public const QUERY_TOKEN = 'hub_preview_token';

    private static ?int $resolved_version = null;
    private static bool $resolved = false;

    public static function url(int $design_id, int $version_id, int $ttl = DAY_IN_SECONDS): string
    {
        $expires = time() + max(60, $ttl);

        return add_query_arg([
            self::QUERY_VERSION => $version_id,
            self::QUERY_EXPIRES => $expires,
            self::QUERY_TOKEN => self::token($version_id, $expires),
        ], (string) get_permalink($design_id));
    }

    public static function token(int $version_id, int $expires): string
    {
        return hash_hmac('sha256', $version_id . '|' . $expires, wp_salt('auth'));
    }

    /**
     * Version requested for preview on this request, or 0.
     *
     * Reading `$_GET` here is intentional: the values are authenticated by the
     * HMAC below, not trusted on their own.
     */
    public static function requested_version_id(): int
    {
        if (self::$resolved) {
            return (int) self::$resolved_version;
        }

        self::$resolved = true;
        self::$resolved_version = 0;

        if (empty($_GET[self::QUERY_VERSION]) || empty($_GET[self::QUERY_TOKEN]) || empty($_GET[self::QUERY_EXPIRES])) {
            return 0;
        }

        $version_id = absint($_GET[self::QUERY_VERSION]);
        $expires = absint($_GET[self::QUERY_EXPIRES]);
        $token = sanitize_text_field(wp_unslash((string) $_GET[self::QUERY_TOKEN]));

        if ($version_id <= 0 || $expires < time()) {
            return 0;
        }

        if (!hash_equals(self::token($version_id, $expires), $token)) {
            return 0;
        }

        self::$resolved_version = $version_id;
        return $version_id;
    }

    /**
     * The version to render for a design: the previewed one when the signature
     * matches and it belongs to this design, otherwise the published one.
     *
     * @return array<string,mixed>|null
     */
    public static function version_for(int $design_id): ?array
    {
        $store = HUB_Tibox_Version_Store::instance();
        $requested = self::requested_version_id();

        if ($requested > 0) {
            $version = $store->get($requested);
            if ($version !== null && (int) $version['design_id'] === $design_id) {
                return $version;
            }
        }

        return $store->get_live($design_id);
    }

    public static function is_previewing(int $design_id = 0): bool
    {
        $requested = self::requested_version_id();
        if ($requested <= 0) {
            return false;
        }

        if ($design_id <= 0) {
            return true;
        }

        $version = HUB_Tibox_Version_Store::instance()->get($requested);
        return $version !== null && (int) $version['design_id'] === $design_id;
    }

    /** Banner shown to whoever opens a preview link. */
    public static function render_notice(): void
    {
        if (!self::is_previewing()) {
            return;
        }

        $version = HUB_Tibox_Version_Store::instance()->get(self::requested_version_id());
        if ($version === null) {
            return;
        }

        printf(
            '<div style="position:fixed;z-index:99999;left:0;right:0;bottom:0;padding:10px 16px;background:#111827;color:#fff;font:600 13px/1.4 system-ui,sans-serif;text-align:center;">%s</div>',
            esc_html(sprintf(
                'Vista previa de la versión %d (%s). Esta URL no está publicada y no se indexa.',
                (int) $version['version'],
                (string) $version['status']
            ))
        );
    }
}
