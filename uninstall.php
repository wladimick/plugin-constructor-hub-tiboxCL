<?php
/**
 * Uninstall routine.
 *
 * Removing a plugin should not silently destroy commercial data. Designs,
 * versions and leads survive an uninstall unless an administrator explicitly
 * asked for a full wipe by setting the `hub_tibox_delete_data_on_uninstall`
 * option, so reinstalling recovers the site.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-hub-capabilities.php';
require_once __DIR__ . '/includes/class-hub-filesystem.php';

HUB_Tibox_Capabilities::revoke();

$hub_transient_prefixes = ['hub_lp_', 'hub_landing_zip_error_'];

global $wpdb;
foreach ($hub_transient_prefixes as $hub_prefix) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $hub_prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $hub_prefix) . '%'
        )
    );
}

if (get_option('hub_tibox_delete_data_on_uninstall', '0') !== '1') {
    return;
}

// Full wipe, explicitly requested.
$hub_design_ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('hub_design', 'hub_landing', 'hub_component')"
);

foreach ((array) $hub_design_ids as $hub_post_id) {
    wp_delete_post((int) $hub_post_id, true);
}

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hub_design_versions");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hub_landing_leads");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hub_mail_log");

$hub_options = [
    'hub_tibox_plugin_version',
    'hub_tibox_designs_unified',
    'hub_tibox_designs_unification_result',
    'hub_tibox_legacy_redirects',
    'hub_tibox_regions',
    'hub_tibox_design_rewrite_version',
    'hub_tibox_landing_rewrite_version',
    'hub_tibox_versions_db_version',
    'hub_tibox_landing_leads_db_version',
    'hub_tibox_client_ip_header',
    'hub_tibox_elementor_design_support',
    'hub_tibox_elementor_landing_support',
    'hub_tibox_active_header',
    'hub_tibox_active_footer',
    'hub_tibox_hybrid_enabled',
    'hub_tibox_hybrid_scope',
    'hub_tibox_hybrid_pages',
    'hub_tibox_mail_recipients',
    'hub_tibox_mail_confirmation',
    'hub_tibox_legacy_last_migration',
    'hub_tibox_legacy_leads_last_migration',
    'hub_tibox_delete_data_on_uninstall',
];

foreach ($hub_options as $hub_option) {
    delete_option($hub_option);
}

$hub_uploads = wp_upload_dir();
foreach (['constructor-hub', 'constructor-hub-landings'] as $hub_dir) {
    HUB_Tibox_Filesystem::delete_directory(trailingslashit((string) $hub_uploads['basedir']) . $hub_dir);
}
