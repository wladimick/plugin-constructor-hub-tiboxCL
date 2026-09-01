<?php

/**
 * Covers the same class of bug the second audit found in
 * `HUB_Tibox_Upgrade` (see test-upgrade-stage-transaction.php), now found in
 * the other migration path: `HUB_Tibox_Legacy_Migrator::migrate_landing()`
 * (WPCode's `tibox_landing` → `hub_design`) returned the new design id
 * regardless of whether `Version_Store::create()`, `publish()`, or the
 * package copy actually succeeded.
 *
 * `evaluate_stage_result()` reuses `HUB_Tibox_Upgrade::evaluate_version_write()`
 * and `HUB_Tibox_Upgrade::evaluate_package_copy()` for the underlying
 * create/publish/copy classification — those are exercised directly in
 * test-upgrade-stage-transaction.php — and adds only the combination rule
 * this migrator needs: a version failure or a package failure both block
 * completion.
 *
 * `evaluate_cutover_readiness()` covers the independent guard added to
 * `run_cutover()`: publishing a URL must never happen on top of a design
 * whose live version is not actually renderable, whether or not it was ever
 * marked complete by the migration itself.
 *
 * Not covered here, because it needs a real database: that
 * `find_staged_design_id()` actually resumes the same `hub_design` row on
 * retry instead of `wp_insert_post()`-ing a duplicate. That is Fase 7, on
 * WordPress itself — same line the rest of this migration draws.
 */

require_once dirname(__DIR__) . '/includes/class-hub-design.php';
require_once dirname(__DIR__) . '/includes/class-hub-version-store.php';
require_once dirname(__DIR__) . '/includes/class-hub-upgrade.php';
require_once dirname(__DIR__) . '/includes/class-hub-legacy-migrator.php';

$ok = static fn(): array => ['status' => 'ok', 'error' => ''];
$skipped = static fn(): array => ['status' => 'skipped', 'error' => ''];

// ------------------------------------------------- version create() failed

$create_failed = HUB_Tibox_Upgrade::evaluate_version_write(5, 0, false);
$stage_after_create_failure = HUB_Tibox_Legacy_Migrator::evaluate_stage_result($create_failed, $skipped());

Hub_Test::assert_same(
    'wpcode migrator: a version create() failure blocks the landing from being reported migrated',
    'failed',
    $stage_after_create_failure['status']
);
Hub_Test::assert_true(
    'wpcode migrator: the create failure carries an actionable message',
    str_contains($stage_after_create_failure['error'], 'crear la versión')
);

// ------------------------------------------------------ version publish() failed

$publish_failed = HUB_Tibox_Upgrade::evaluate_version_write(5, 42, false);
$stage_after_publish_failure = HUB_Tibox_Legacy_Migrator::evaluate_stage_result($publish_failed, $skipped());

Hub_Test::assert_same(
    'wpcode migrator: a version publish() failure blocks the landing from being reported migrated',
    'failed',
    $stage_after_publish_failure['status']
);
Hub_Test::assert_true(
    'wpcode migrator: the publish failure is distinguishable from the create failure',
    $stage_after_publish_failure['error'] !== $stage_after_create_failure['error']
        && str_contains($stage_after_publish_failure['error'], 'no pudo publicarse')
);

// --------------------------------------------------------- package copy failed

$version_ok = HUB_Tibox_Upgrade::evaluate_version_write(5, 42, true);
$package_copy_failed = HUB_Tibox_Upgrade::evaluate_package_copy(9, 5, 'index.html', '/uploads/tibox-landings/9', true, false);
$stage_after_package_failure = HUB_Tibox_Legacy_Migrator::evaluate_stage_result($version_ok, $package_copy_failed);

Hub_Test::assert_same(
    'wpcode migrator: a package copy failure blocks the landing even with a good version',
    'failed',
    $stage_after_package_failure['status']
);
Hub_Test::assert_true(
    'wpcode migrator: the package failure names both the legacy landing and the design',
    str_contains($stage_after_package_failure['error'], '#9') && str_contains($stage_after_package_failure['error'], '#5')
);

// A landing with no package declared at all must still complete on a good
// version alone — most WPCode landings never had one.
$stage_no_package = HUB_Tibox_Legacy_Migrator::evaluate_stage_result($version_ok, $skipped());
Hub_Test::assert_same('wpcode migrator: no declared package is not a failure', 'ok', $stage_no_package['status']);

$stage_success = HUB_Tibox_Legacy_Migrator::evaluate_stage_result($version_ok, $ok());
Hub_Test::assert_same('wpcode migrator: version ok + package ok completes', 'ok', $stage_success['status']);

// ------------------------------------------------------------- retry succeeds

// Pass 1: create() failed for this legacy landing.
$retry_pass_one = HUB_Tibox_Legacy_Migrator::evaluate_stage_result(
    HUB_Tibox_Upgrade::evaluate_version_write(11, 0, false),
    $skipped()
);
Hub_Test::assert_same('wpcode migrator: retry scenario — first attempt fails', 'failed', $retry_pass_one['status']);

// Pass 2: same design id (11), the underlying cause got fixed — create() now
// returns a real id and publish() succeeds. The classifier has no memory of
// pass 1 — it is pure — which is exactly what makes the retry safe to repeat
// without special-casing "this failed before".
$retry_pass_two = HUB_Tibox_Legacy_Migrator::evaluate_stage_result(
    HUB_Tibox_Upgrade::evaluate_version_write(11, 77, true),
    $skipped()
);
Hub_Test::assert_same('wpcode migrator: retry scenario — second attempt on the same design succeeds', 'ok', $retry_pass_two['status']);

// ---------------------------------------------------- run_cutover() readiness

// No live version at all: the migration never completed, or something wiped
// the pointer since. Cutover must refuse regardless of META_STAGED.
Hub_Test::assert_true(
    'cutover: a design with no live version at all is never ready',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness(null, HUB_Tibox_Design::MODE_HUB, null) !== ''
);

// HUB / STANDALONE modes need actual HTML in the live version.
$empty_html_live = ['id' => 3, 'html' => '', 'entry' => ''];
Hub_Test::assert_true(
    'cutover: a HUB-mode design with an empty live version is not ready',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($empty_html_live, HUB_Tibox_Design::MODE_HUB, null) !== ''
);

$real_html_live = ['id' => 3, 'html' => '<section>hola</section>', 'entry' => ''];
Hub_Test::assert_same(
    'cutover: a HUB-mode design with real HTML is ready',
    '',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($real_html_live, HUB_Tibox_Design::MODE_HUB, null)
);

// LEGACY mode is rendered by the theme/Elementor via the_content(): an empty
// version is expected, not a failure.
Hub_Test::assert_same(
    'cutover: MODE_LEGACY needs no content of its own',
    '',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($empty_html_live, HUB_Tibox_Design::MODE_LEGACY, null)
);

// PACKAGE mode needs a declared entry AND the file actually present on disk —
// two independent ways this can still be broken even with META_STAGED set.
$package_no_entry = ['id' => 4, 'html' => '', 'entry' => ''];
Hub_Test::assert_true(
    'cutover: a package design with no entry declared is not ready',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($package_no_entry, HUB_Tibox_Design::MODE_PACKAGE, null) !== ''
);

$package_with_entry = ['id' => 4, 'html' => '', 'entry' => 'index.html'];
Hub_Test::assert_true(
    'cutover: a package design whose entry file is missing on disk is not ready',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($package_with_entry, HUB_Tibox_Design::MODE_PACKAGE, false) !== ''
);
Hub_Test::assert_same(
    'cutover: a package design whose entry file exists on disk is ready',
    '',
    HUB_Tibox_Legacy_Migrator::evaluate_cutover_readiness($package_with_entry, HUB_Tibox_Design::MODE_PACKAGE, true)
);
