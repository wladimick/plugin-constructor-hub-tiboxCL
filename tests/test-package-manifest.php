<?php

require_once dirname(__DIR__) . '/includes/class-hub-design.php';
require_once dirname(__DIR__) . '/includes/class-hub-variables.php';
require_once dirname(__DIR__) . '/includes/class-hub-package.php';

$valid = [
    'hub_package' => 1,
    'type' => 'hero',
    'name' => 'Hero servicios',
    'variables' => ['SITE_NAME'],
];

$result = HUB_Tibox_Package::validate_manifest($valid);
Hub_Test::assert_false('manifest: a valid package is accepted', $result instanceof WP_Error);
Hub_Test::assert_same('manifest: the slug is derived from the name', 'hero-servicios', is_array($result) ? $result['slug'] : '');
Hub_Test::assert_same('manifest: entry defaults to index.html', 'index.html', is_array($result) ? $result['entry'] : '');

$missing = HUB_Tibox_Package::validate_manifest(['type' => 'hero', 'name' => 'x']);
Hub_Test::assert_true('manifest: hub_package is required', $missing instanceof WP_Error);
Hub_Test::assert_same(
    'manifest: the contract error names the field',
    'hub_manifest_contract',
    $missing instanceof WP_Error ? $missing->get_error_code() : ''
);

$future = HUB_Tibox_Package::validate_manifest(['hub_package' => 99, 'type' => 'hero', 'name' => 'x']);
Hub_Test::assert_same(
    'manifest: a future contract is rejected instead of half understood',
    'hub_manifest_future',
    $future instanceof WP_Error ? $future->get_error_code() : ''
);

$bad_type = HUB_Tibox_Package::validate_manifest(['hub_package' => 1, 'type' => 'carousel', 'name' => 'x']);
Hub_Test::assert_same(
    'manifest: an unknown type is rejected',
    'hub_manifest_type',
    $bad_type instanceof WP_Error ? $bad_type->get_error_code() : ''
);
Hub_Test::assert_true(
    'manifest: the type error lists the valid types',
    $bad_type instanceof WP_Error && str_contains($bad_type->get_error_message(), 'hero')
);

$no_name = HUB_Tibox_Package::validate_manifest(['hub_package' => 1, 'type' => 'hero']);
Hub_Test::assert_same(
    'manifest: a name is required',
    'hub_manifest_name',
    $no_name instanceof WP_Error ? $no_name->get_error_code() : ''
);

$bad_variable = HUB_Tibox_Package::validate_manifest([
    'hub_package' => 1,
    'type' => 'hero',
    'name' => 'x',
    'variables' => ['SITE_NAME', 'PRECIO_MENSUAL'],
]);
Hub_Test::assert_same(
    'manifest: an undeclarable variable is rejected',
    'hub_manifest_variables',
    $bad_variable instanceof WP_Error ? $bad_variable->get_error_code() : ''
);
Hub_Test::assert_true(
    'manifest: the variable error names the offending variable',
    $bad_variable instanceof WP_Error && str_contains($bad_variable->get_error_message(), 'PRECIO_MENSUAL')
);

// The shipped example package must always validate: it is what a new install
// and any AI reads as the reference.
$example = json_decode((string) file_get_contents(dirname(__DIR__) . '/examples/hub-package-hero/manifest.json'), true);
$example_result = HUB_Tibox_Package::validate_manifest(is_array($example) ? $example : []);
Hub_Test::assert_false(
    'manifest: the shipped example package validates',
    $example_result instanceof WP_Error
);
