<?php

require_once dirname(__DIR__) . '/includes/class-hub-package-upload-guard.php';

Hub_Test::assert_false(
    'upload guard: empty ZIP is rejected',
    HUB_Tibox_Package_Upload_Guard::size_allowed(0, 25 * 1024 * 1024)
);

Hub_Test::assert_true(
    'upload guard: ZIP below the compressed limit is allowed',
    HUB_Tibox_Package_Upload_Guard::size_allowed(10 * 1024 * 1024, 25 * 1024 * 1024)
);

Hub_Test::assert_true(
    'upload guard: ZIP exactly at the compressed limit is allowed',
    HUB_Tibox_Package_Upload_Guard::size_allowed(25 * 1024 * 1024, 25 * 1024 * 1024)
);

Hub_Test::assert_false(
    'upload guard: ZIP above the compressed limit is rejected',
    HUB_Tibox_Package_Upload_Guard::size_allowed((25 * 1024 * 1024) + 1, 25 * 1024 * 1024)
);

Hub_Test::assert_false(
    'upload guard: invalid max policy is fail-closed',
    HUB_Tibox_Package_Upload_Guard::size_allowed(1024, 0)
);
