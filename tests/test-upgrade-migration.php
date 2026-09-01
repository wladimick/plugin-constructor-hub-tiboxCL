<?php

/**
 * Covers the exact decision a second audit flagged as unsafe: whether a
 * migration result should flip `hub_tibox_designs_unified` to `1`.
 *
 * `HUB_Tibox_Upgrade::evaluate_migration_result()` is a pure function with no
 * WordPress calls — the staging/cutover split moved every WordPress-dependent
 * side effect (wp_insert_post, wp_update_post, post meta) out of the decision
 * itself, specifically so this could be unit tested without a database.
 */

require_once dirname(__DIR__) . '/includes/class-hub-upgrade.php';

Hub_Test::assert_same(
    'migration: no legacy objects at all is a complete, empty result',
    'complete',
    HUB_Tibox_Upgrade::evaluate_migration_result([])['status']
);

Hub_Test::assert_same(
    'migration: every item created is complete',
    'complete',
    HUB_Tibox_Upgrade::evaluate_migration_result([
        ['status' => 'created'],
        ['status' => 'created'],
    ])['status']
);

Hub_Test::assert_same(
    'migration: a mix of created and already-migrated items is still complete',
    'complete',
    HUB_Tibox_Upgrade::evaluate_migration_result([
        ['status' => 'created'],
        ['status' => 'existing'],
        ['status' => 'missing'],
    ])['status']
);

$one_failure = HUB_Tibox_Upgrade::evaluate_migration_result([
    ['status' => 'created'],
    ['status' => 'failed', 'legacy_id' => 42, 'error' => 'wp_insert_post falló'],
    ['status' => 'existing'],
]);

Hub_Test::assert_same(
    'migration: a single failure anywhere in the batch is never complete',
    'partial',
    $one_failure['status']
);
Hub_Test::assert_same('migration: the failure count is exact', 1, $one_failure['failed']);
Hub_Test::assert_same('migration: successes are still counted alongside the failure', 1, $one_failure['created']);
Hub_Test::assert_same('migration: the failing item itself is returned for the admin screen', 42, $one_failure['failures'][0]['legacy_id']);

Hub_Test::assert_same(
    'migration: every item failing is fully partial, not silently complete',
    'partial',
    HUB_Tibox_Upgrade::evaluate_migration_result([
        ['status' => 'failed'],
        ['status' => 'failed'],
    ])['status']
);

// An item classification the summariser has never seen must never be
// interpreted as a quiet success — the whole point of this function is that
// activating Unified is the exception, not the default.
Hub_Test::assert_same(
    'migration: an unrecognised item status counts as a failure, not a success',
    'partial',
    HUB_Tibox_Upgrade::evaluate_migration_result([['status' => 'something-new']])['status']
);

// Simulates the real sequence: first pass has one failure, the administrator
// fixes it, retrying re-stages only the failure (the successful item is now
// 'existing') and the batch completes.
$first_pass = [
    ['status' => 'created', 'legacy_id' => 1],
    ['status' => 'failed', 'legacy_id' => 2, 'error' => 'DB error'],
];
Hub_Test::assert_same('migration: first pass with one failure is partial', 'partial', HUB_Tibox_Upgrade::evaluate_migration_result($first_pass)['status']);

$retry_pass = [
    ['status' => 'existing', 'legacy_id' => 1],
    ['status' => 'created', 'legacy_id' => 2],
];
Hub_Test::assert_same('migration: retry pass with the fix applied is complete', 'complete', HUB_Tibox_Upgrade::evaluate_migration_result($retry_pass)['status']);
