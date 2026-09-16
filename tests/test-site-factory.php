<?php

require_once dirname(__DIR__) . '/includes/class-hub-site-factory.php';

Hub_Test::assert_false(
    'site factory: never activates without an explicit request',
    HUB_Tibox_Site_Factory::should_activate(false, true, true, true)
);

Hub_Test::assert_false(
    'site factory: never activates without publish permission',
    HUB_Tibox_Site_Factory::should_activate(true, false, true, true)
);

Hub_Test::assert_false(
    'site factory: publish guard failure keeps Theme/Elementor active',
    HUB_Tibox_Site_Factory::should_activate(true, true, false, true)
);

Hub_Test::assert_false(
    'site factory: unpublished design cannot own the Page',
    HUB_Tibox_Site_Factory::should_activate(true, true, true, false)
);

Hub_Test::assert_true(
    'site factory: activates only after every prerequisite succeeds',
    HUB_Tibox_Site_Factory::should_activate(true, true, true, true)
);

Hub_Test::assert_false(
    'site factory: cannot publish a Page without WordPress permission',
    HUB_Tibox_Site_Factory::should_publish_page(true, false)
);

Hub_Test::assert_true(
    'site factory: explicit Page publish request works with permission',
    HUB_Tibox_Site_Factory::should_publish_page(true, true)
);
