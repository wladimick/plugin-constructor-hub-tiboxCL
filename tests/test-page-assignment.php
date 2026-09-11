<?php

require_once dirname(__DIR__) . '/includes/class-hub-page-assignment.php';

Hub_Test::assert_false(
    'page assignment: no design never takes over',
    HUB_Tibox_Page_Assignment::should_take_over(
        HUB_Tibox_Page_Assignment::MODE_HUB,
        false,
        true,
        false
    )
);

Hub_Test::assert_false(
    'page assignment: theme mode keeps legacy renderer',
    HUB_Tibox_Page_Assignment::should_take_over(
        HUB_Tibox_Page_Assignment::MODE_THEME,
        true,
        true,
        false
    )
);

Hub_Test::assert_false(
    'page assignment: HUB mode fails safe without a live version',
    HUB_Tibox_Page_Assignment::should_take_over(
        HUB_Tibox_Page_Assignment::MODE_HUB,
        true,
        false,
        false
    )
);

Hub_Test::assert_true(
    'page assignment: HUB mode takes over with a live version',
    HUB_Tibox_Page_Assignment::should_take_over(
        HUB_Tibox_Page_Assignment::MODE_HUB,
        true,
        true,
        false
    )
);

Hub_Test::assert_true(
    'page assignment: signed preview can take over before cutover',
    HUB_Tibox_Page_Assignment::should_take_over(
        HUB_Tibox_Page_Assignment::MODE_THEME,
        true,
        false,
        true
    )
);
