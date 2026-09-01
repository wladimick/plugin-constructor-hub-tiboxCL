<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps the historical post types registered after unification.
 *
 * The migration copies `hub_component` and `hub_landing` into `hub_design` and
 * never deletes the source. Registering them without UI and without their own
 * URLs means the data stays queryable for a rollback while the design objects
 * own the front end.
 */
final class HUB_Tibox_Legacy_Types
{
    public static function register(): void
    {
        $shared = [
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'show_in_nav_menus' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
            'delete_with_user' => false,
            'supports' => ['title'],
            'capability_type' => ['hub_design', 'hub_designs'],
            'map_meta_cap' => true,
        ];

        register_post_type('hub_component', array_merge($shared, [
            'labels' => ['name' => 'Componentes HUB (histórico)', 'singular_name' => 'Componente HUB (histórico)'],
        ]));

        register_post_type('hub_landing', array_merge($shared, [
            'labels' => ['name' => 'Landings HUB (histórico)', 'singular_name' => 'Landing HUB (histórico)'],
        ]));
    }
}
