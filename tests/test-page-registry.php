<?php

require_once dirname(__DIR__) . '/includes/class-hub-page-registry.php';

Hub_Test::assert_false(
    'page registry: cannot activate without an assigned HUB design',
    HUB_Tibox_Page_Registry::can_activate_hub(false, true, true)
);

Hub_Test::assert_false(
    'page registry: cannot activate an unpublished design',
    HUB_Tibox_Page_Registry::can_activate_hub(true, false, true)
);

Hub_Test::assert_false(
    'page registry: cannot activate without a live version',
    HUB_Tibox_Page_Registry::can_activate_hub(true, true, false)
);

Hub_Test::assert_true(
    'page registry: activation is ready only with assignment, published design and live version',
    HUB_Tibox_Page_Registry::can_activate_hub(true, true, true)
);
