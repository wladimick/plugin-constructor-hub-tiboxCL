<?php

require_once dirname(__DIR__) . '/includes/class-hub-release-updater.php';

Hub_Test::assert_same(
    'updater: v prefix is removed from stable tags',
    '0.6.0',
    HUB_Tibox_Release_Updater::normalize_version('v0.6.0')
);

Hub_Test::assert_same(
    'updater: prerelease suffix is preserved',
    '0.6.0-rc1',
    HUB_Tibox_Release_Updater::normalize_version('v0.6.0-rc1')
);

Hub_Test::assert_true(
    'updater: newer stable version is accepted',
    HUB_Tibox_Release_Updater::is_newer_stable_release('0.6.0-dev', '0.6.0', false, false)
);

Hub_Test::assert_false(
    'updater: same version is ignored',
    HUB_Tibox_Release_Updater::is_newer_stable_release('0.6.0', '0.6.0', false, false)
);

Hub_Test::assert_false(
    'updater: prerelease is ignored by stable channel',
    HUB_Tibox_Release_Updater::is_newer_stable_release('0.6.0-dev', '0.6.0-rc1', false, true)
);

Hub_Test::assert_false(
    'updater: draft release is ignored',
    HUB_Tibox_Release_Updater::is_newer_stable_release('0.5.0', '0.6.0', true, false)
);

$trusted = 'https://github.com/wladimick/plugin-constructor-hub-tiboxCL/releases/download/v0.6.0/constructor-hub-tibox-0.6.0.zip';
$foreign = 'https://example.com/constructor-hub-tibox-0.6.0.zip';
$wrong_repo = 'https://github.com/other/project/releases/download/v0.6.0/constructor-hub-tibox-0.6.0.zip';

Hub_Test::assert_true(
    'updater: exact GitHub release path is trusted',
    HUB_Tibox_Release_Updater::is_trusted_package_url($trusted)
);

Hub_Test::assert_false(
    'updater: external package host is rejected',
    HUB_Tibox_Release_Updater::is_trusted_package_url($foreign)
);

Hub_Test::assert_false(
    'updater: another GitHub repository is rejected',
    HUB_Tibox_Release_Updater::is_trusted_package_url($wrong_repo)
);

$assets = [
    [
        'name' => 'Source code.zip',
        'browser_download_url' => 'https://github.com/wladimick/plugin-constructor-hub-tiboxCL/archive/refs/tags/v0.6.0.zip',
    ],
    [
        'name' => 'constructor-hub-tibox-0.6.0.zip',
        'browser_download_url' => $trusted,
    ],
];

$asset = HUB_Tibox_Release_Updater::select_asset($assets, '0.6.0');
Hub_Test::assert_same(
    'updater: only the installable workflow asset is selected',
    $trusted,
    is_array($asset) ? ($asset['browser_download_url'] ?? null) : null
);

Hub_Test::assert_same(
    'updater: expected asset name follows plugin version',
    'constructor-hub-tibox-0.6.0.zip',
    HUB_Tibox_Release_Updater::expected_asset_name('0.6.0')
);
