<?php

/**
 * Covers the ordering bug a second audit found: `HUB_Tibox_Plugin`'s
 * constructor decided Legacy vs. Unified synchronously, which for a plugin's
 * main file happens before WordPress fires `plugins_loaded` — before the
 * upgrade routine (hooked at `plugins_loaded` priority 5) has run. The very
 * request that completed a migration still rendered through the pre-migration
 * boot path.
 *
 * `needs_deferred_boot()` is the pure decision extracted from that timing
 * question, so it can be checked without depending on WordPress's action
 * firing order at all.
 */

require_once dirname(__DIR__) . '/includes/class-hub-plugin.php';

Hub_Test::assert_true(
    'plugin boot: before plugins_loaded has fired, booting must be deferred',
    HUB_Tibox_Plugin::needs_deferred_boot(false)
);

Hub_Test::assert_false(
    'plugin boot: once plugins_loaded has fired, the upgrade routine already ran and booting can happen immediately',
    HUB_Tibox_Plugin::needs_deferred_boot(true)
);
