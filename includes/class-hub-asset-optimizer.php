<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Selective removal of assets a page no longer needs.
 *
 * The historical MVP dequeued anything whose handle contained `elementor`,
 * `swiper`, `font-awesome` or `jquery`, on any page it took over. That is
 * guessing: a single remaining widget, a theme feature or another plugin
 * depending on the same handle breaks silently and the site owner finds out
 * later.
 *
 * The rules here are deliberately narrow:
 *
 *  - only on URLs whose document Constructor HUB owns;
 *  - only when the inventory says the content does not need Elementor;
 *  - never a handle that something still enqueued depends on;
 *  - off by default, and reversible by unticking one box.
 */
final class HUB_Tibox_Asset_Optimizer
{
    public const OPTION = 'hub_tibox_asset_optimizer';

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
        add_action('wp_enqueue_scripts', [$this, 'maybe_strip'], 999);
    }

    public static function is_enabled(): bool
    {
        return get_option(self::OPTION, '0') === '1';
    }

    public function maybe_strip(): void
    {
        if (!self::is_enabled() || is_admin()) {
            return;
        }

        if (!HUB_Tibox_Render::instance()->is_hub_document()) {
            return;
        }

        $post_id = get_queried_object_id();
        if ($post_id > 0 && apply_filters('constructor_hub_elementor_needed', false, $post_id)) {
            // The content still renders through Elementor. Removing its assets
            // would break the page it is trying to speed up.
            return;
        }

        $handles = (array) apply_filters('constructor_hub_strippable_handles', [
            'elementor-frontend',
            'elementor-post',
            'elementor-global',
            'elementor-icons',
            'elementor-animations',
            'elementor-common',
            'elementor-frontend-modules',
            'elementor-waypoints',
            'swiper',
            'eael-general',
            'font-awesome',
        ], $post_id);

        $this->dequeue_styles($handles);
        $this->dequeue_scripts($handles);
    }

    /** @param string[] $handles */
    private function dequeue_styles(array $handles): void
    {
        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles)) {
            return;
        }

        foreach ($handles as $handle) {
            if (!isset($wp_styles->registered[$handle]) || $this->has_dependents($wp_styles, $handle, $handles)) {
                continue;
            }

            wp_dequeue_style($handle);
        }
    }

    /** @param string[] $handles */
    private function dequeue_scripts(array $handles): void
    {
        global $wp_scripts;

        if (!($wp_scripts instanceof WP_Scripts)) {
            return;
        }

        foreach ($handles as $handle) {
            if (!isset($wp_scripts->registered[$handle]) || $this->has_dependents($wp_scripts, $handle, $handles)) {
                continue;
            }

            wp_dequeue_script($handle);
        }
    }

    /**
     * True when something still queued depends on this handle.
     *
     * Dropping a dependency out from under another plugin is the failure mode
     * that makes asset stripping untrustworthy.
     *
     * @param WP_Styles|WP_Scripts $collection
     * @param string[]             $removing
     */
    private function has_dependents($collection, string $handle, array $removing): bool
    {
        foreach ((array) $collection->queue as $queued) {
            if ($queued === $handle || in_array($queued, $removing, true)) {
                continue;
            }

            $registered = $collection->registered[$queued] ?? null;
            if ($registered !== null && in_array($handle, (array) $registered->deps, true)) {
                return true;
            }
        }

        return false;
    }
}
