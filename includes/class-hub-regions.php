<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Header and Footer configuration, independently per region.
 *
 * The previous hybrid renderer required a HUB Header *and* a HUB Footer to be
 * published before it would activate, which made the first step of the
 * migration — replacing only the header — impossible.
 *
 * Two ways to take over a region:
 *
 *  - `inject` keeps the theme template and inserts the HUB markup through
 *    `wp_body_open` / `wp_footer`, optionally hiding the theme region with a CSS
 *    selector. This is the safe mode on an unknown theme and the one to use for
 *    a partial migration.
 *  - `replace` gives Constructor HUB the whole document. Use it when both
 *    regions are HUB, because a theme region is not printed in this mode.
 */
final class HUB_Tibox_Regions
{
    public const OPTION = 'hub_tibox_regions';

    public const MODE_THEME = 'theme';
    public const MODE_INJECT = 'inject';
    public const MODE_REPLACE = 'replace';

    public const SCOPE_ALL = 'all';
    public const SCOPE_SELECTED = 'selected';
    public const SCOPE_EXCEPT = 'except';

    /** @return string[] */
    public static function names(): array
    {
        return ['header', 'footer'];
    }

    /**
     * @return array{mode:string,design:int,scope:string,targets:int[],hide_selector:string}
     */
    public static function config(string $region): array
    {
        $all = get_option(self::OPTION, []);
        $config = is_array($all) && isset($all[$region]) && is_array($all[$region]) ? $all[$region] : [];

        $mode = (string) ($config['mode'] ?? self::MODE_THEME);
        if (!in_array($mode, [self::MODE_THEME, self::MODE_INJECT, self::MODE_REPLACE], true)) {
            $mode = self::MODE_THEME;
        }

        $scope = (string) ($config['scope'] ?? self::SCOPE_ALL);
        if (!in_array($scope, [self::SCOPE_ALL, self::SCOPE_SELECTED, self::SCOPE_EXCEPT], true)) {
            $scope = self::SCOPE_ALL;
        }

        return [
            'mode' => $mode,
            'design' => absint($config['design'] ?? 0),
            'scope' => $scope,
            'targets' => array_values(array_filter(array_map('absint', (array) ($config['targets'] ?? [])))),
            'hide_selector' => (string) ($config['hide_selector'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function save(string $region, array $config): void
    {
        if (!in_array($region, self::names(), true)) {
            return;
        }

        $all = get_option(self::OPTION, []);
        $all = is_array($all) ? $all : [];
        $all[$region] = [
            'mode' => (string) ($config['mode'] ?? self::MODE_THEME),
            'design' => absint($config['design'] ?? 0),
            'scope' => (string) ($config['scope'] ?? self::SCOPE_ALL),
            'targets' => array_values(array_filter(array_map('absint', (array) ($config['targets'] ?? [])))),
            'hide_selector' => sanitize_text_field((string) ($config['hide_selector'] ?? '')),
        ];

        update_option(self::OPTION, $all, true);
    }

    /**
     * Design id that should render this region on the current request, or 0.
     */
    public static function active_design(string $region): int
    {
        $config = self::config($region);

        if ($config['mode'] === self::MODE_THEME || $config['design'] <= 0) {
            return 0;
        }

        if (get_post_status($config['design']) !== 'publish') {
            return 0;
        }

        if (HUB_Tibox_Design::get_type($config['design']) !== $region) {
            return 0;
        }

        if (!self::scope_matches($config)) {
            return 0;
        }

        return (int) apply_filters('constructor_hub_active_region_design', $config['design'], $region);
    }

    public static function mode(string $region): string
    {
        return self::active_design($region) > 0 ? self::config($region)['mode'] : self::MODE_THEME;
    }

    /** True when Constructor HUB owns the whole document on this request. */
    public static function owns_document(): bool
    {
        foreach (self::names() as $region) {
            if (self::mode($region) === self::MODE_REPLACE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{scope:string,targets:int[]} $config
     */
    private static function scope_matches(array $config): bool
    {
        // Regions are chrome: they apply to whatever the theme would wrap,
        // which in practice means any front-end request that renders a template.
        if (is_admin() || is_feed() || is_embed() || wp_is_json_request()) {
            return false;
        }

        if ($config['scope'] === self::SCOPE_ALL) {
            return true;
        }

        $object_id = get_queried_object_id();
        $listed = $object_id > 0 && in_array($object_id, $config['targets'], true);

        return $config['scope'] === self::SCOPE_SELECTED ? $listed : !$listed;
    }
}
