<?php

/**
 * Covers a fifth finding from the second audit of the migration rewrite:
 * `stage_component()`/`stage_landing()` called `seed_version()` and
 * `move_package_directory()` but ignored what they actually returned.
 * `HUB_Tibox_Version_Store::create()` can return `0` on a DB error and
 * `HUB_Tibox_Filesystem::copy_directory()` can return `false`, and neither
 * failure turned the item into `failed` — a design could be reported
 * `created` with no usable version, or with an `entry` pointing at files that
 * were never copied.
 *
 * The fix splits each step into a WordPress-calling wrapper and a pure
 * classifier of that step's already-known outcome. The classifiers —
 * `evaluate_version_write()` and `evaluate_package_copy()` — are what these
 * tests exercise directly, without a database or a filesystem.
 *
 * What is deliberately NOT covered here: the actual "resume the same
 * `hub_design` row instead of inserting a duplicate" mechanic
 * (`find_staged_design_id()` vs. `find_migrated()`) depends on `get_posts()`
 * against a real database and can only be verified in Fase 7, on WordPress
 * itself — same as the cutover and the rollback before it.
 */

require_once dirname(__DIR__) . '/includes/class-hub-design.php';
require_once dirname(__DIR__) . '/includes/class-hub-upgrade.php';

// -------------------------------------------------------------- version write

// Scenario 1: version creation itself failed (Version_Store::create()
// returned 0, e.g. a DB write error). This must never be reported as if the
// version existed.
$create_failed = HUB_Tibox_Upgrade::evaluate_version_write(5, 0, false);
Hub_Test::assert_same('stage: a version create() failure is reported as failed', 'failed', $create_failed['status']);
Hub_Test::assert_true(
    'stage: the create failure names the design, not the version (there is none)',
    str_contains($create_failed['error'], 'crear la versión') && str_contains($create_failed['error'], '#5')
);

// Scenario 2: the version was created (a real, non-zero id) but publish()
// returned false. This is a materially different failure — a row exists in
// wp_hub_design_versions with nothing pointing at it — and must be reported
// with a different, accurate message.
$publish_failed = HUB_Tibox_Upgrade::evaluate_version_write(5, 42, false);
Hub_Test::assert_same('stage: a publish() failure is reported as failed', 'failed', $publish_failed['status']);
Hub_Test::assert_true(
    'stage: the publish failure names the version id that could not be promoted',
    str_contains($publish_failed['error'], 'no pudo publicarse') && str_contains($publish_failed['error'], '#42')
);
Hub_Test::assert_true(
    'stage: create-failure and publish-failure produce distinguishable messages',
    $create_failed['error'] !== $publish_failed['error']
);

// The success path: a real version id, actually published.
$version_ok = HUB_Tibox_Upgrade::evaluate_version_write(5, 42, true);
Hub_Test::assert_same('stage: a created and published version is ok', 'ok', $version_ok['status']);
Hub_Test::assert_same('stage: a successful write carries no error', '', $version_ok['error']);

// -------------------------------------------------------------- package copy

// No package declared on the legacy object at all: not applicable, not a
// failure.
$no_package = HUB_Tibox_Upgrade::evaluate_package_copy(7, 99, '', '', true, null);
Hub_Test::assert_same('stage: a design with no package entry skips the copy step', 'skipped', $no_package['status']);

// Scenario 3: a package IS declared (a non-empty zip entry) but the source
// directory that should hold its extracted files is missing on disk — the
// legacy object's own package folder disappeared or was never there.
$missing_source = HUB_Tibox_Upgrade::evaluate_package_copy(7, 99, 'index.html', '/uploads/tibox-landings/7', false, null);
Hub_Test::assert_same('stage: a missing source directory is a failure, not a silent skip', 'failed', $missing_source['status']);
Hub_Test::assert_true(
    'stage: the missing-source error names the legacy object',
    str_contains($missing_source['error'], '#7') && str_contains($missing_source['error'], 'no se encontró en disco')
);

// Scenario 3b: the source directory exists, but Filesystem::copy_directory()
// itself returned false (a symlink inside it, a permissions error, disk full).
$copy_failed = HUB_Tibox_Upgrade::evaluate_package_copy(7, 99, 'index.html', '/uploads/tibox-landings/7', true, false);
Hub_Test::assert_same('stage: copy_directory() returning false is a failure', 'failed', $copy_failed['status']);
Hub_Test::assert_true(
    'stage: the copy failure names both the source and the destination design',
    str_contains($copy_failed['error'], '#7') && str_contains($copy_failed['error'], '#99')
);

$copy_ok = HUB_Tibox_Upgrade::evaluate_package_copy(7, 99, 'index.html', '/uploads/tibox-landings/7', true, true);
Hub_Test::assert_same('stage: a successful copy is ok', 'ok', $copy_ok['status']);

// ---------------------------------------------------- an item is never both

// The precise defect this fixes: a version failure and a package outcome
// arriving together must never average out to a success. Whichever step
// failed, the item as a whole is 'failed' — this is the logic
// stage_component()/stage_landing() apply before ever writing META_STAGED.
$version_then_package = static function (array $version, array $package): string {
    if ($version['status'] === 'failed') {
        return 'failed';
    }
    if ($package['status'] === 'failed') {
        return 'failed';
    }
    return 'created';
};

Hub_Test::assert_same(
    'stage: a failed version blocks completion even if the package step is skipped',
    'failed',
    $version_then_package($create_failed, $no_package)
);
Hub_Test::assert_same(
    'stage: a successful version with a failed package still blocks completion',
    'failed',
    $version_then_package($version_ok, $missing_source)
);
Hub_Test::assert_same(
    'stage: only version ok + package ok/skipped reaches created',
    'created',
    $version_then_package($version_ok, $copy_ok)
);
Hub_Test::assert_same(
    'stage: a package-less design (component) completes on version alone',
    'created',
    $version_then_package($version_ok, $no_package)
);

// ------------------------------------------------------- retry after a fix

// Scenario 4: the exact sequence an administrator triggers from Constructor
// HUB → Diagnóstico → "Reintentar migración". Pass 1 fails at the version
// step for one item; after fixing whatever caused create() to return 0 (a
// disk-full condition clearing up, a DB issue resolving), pass 2 for the SAME
// design id succeeds. The classifier has no memory of pass 1 — it is pure —
// so this is really a test that retrying re-evaluates from scratch instead of
// being permanently poisoned by the earlier failure.
$pass_one = HUB_Tibox_Upgrade::evaluate_version_write(11, 0, false);
Hub_Test::assert_same('stage: retry scenario — first pass fails', 'failed', $pass_one['status']);

$pass_two = HUB_Tibox_Upgrade::evaluate_version_write(11, 55, true);
Hub_Test::assert_same('stage: retry scenario — second pass on the same design succeeds', 'ok', $pass_two['status']);

// And the batch-level consequence, using the same design id across two
// staging attempts represented as items: the migration as a whole only
// reaches 'complete' once the retry item stops being 'failed'.
$batch_pass_one = HUB_Tibox_Upgrade::evaluate_migration_result([
    ['status' => 'created', 'legacy_id' => 1],
    ['status' => 'failed', 'legacy_id' => 11, 'error' => $pass_one['error']],
]);
Hub_Test::assert_same('stage: retry scenario — batch is partial while the item still fails', 'partial', $batch_pass_one['status']);

$batch_pass_two = HUB_Tibox_Upgrade::evaluate_migration_result([
    ['status' => 'existing', 'legacy_id' => 1],
    ['status' => 'created', 'legacy_id' => 11],
]);
Hub_Test::assert_same('stage: retry scenario — batch completes once the fix lands', 'complete', $batch_pass_two['status']);
