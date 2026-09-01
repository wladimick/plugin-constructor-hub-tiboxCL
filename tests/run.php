<?php

require __DIR__ . '/bootstrap.php';

require __DIR__ . '/stubs-wp.php';

foreach (glob(__DIR__ . '/test-*.php') ?: [] as $test) {
    require $test;
}

exit(Hub_Test::summary());
