<?php

require_once dirname(__DIR__) . '/includes/class-hub-variables.php';

Hub_Test::assert_same(
    'variables: content without braces yields nothing to resolve',
    [],
    HUB_Tibox_Variables::used_in('<p>Hola</p>')
);

Hub_Test::assert_same(
    'variables: used_in finds each name once',
    ['SITE_NAME', 'HUB_FORM'],
    HUB_Tibox_Variables::used_in('<h1>{{SITE_NAME}}</h1>{{HUB_FORM}}<p>{{SITE_NAME}}</p>')
);

Hub_Test::assert_same(
    'variables: whitespace inside the braces is tolerated',
    ['SITE_NAME'],
    HUB_Tibox_Variables::used_in('{{ SITE_NAME }}')
);

Hub_Test::assert_same(
    'variables: lowercase placeholders are not variables',
    [],
    HUB_Tibox_Variables::used_in('{{site_name}}')
);

Hub_Test::assert_same(
    'variables: unknown_in reports only what the registry lacks',
    ['PRECIO_MENSUAL'],
    HUB_Tibox_Variables::unknown_in('{{SITE_NAME}} {{PRECIO_MENSUAL}} {{PAGE_TITLE}}')
);

Hub_Test::assert_same(
    'variables: a fully known document reports nothing unknown',
    [],
    HUB_Tibox_Variables::unknown_in('{{SITE_NAME}} {{HUB_FORM}} {{PRIVACY_URL}}')
);

// The historical Landings aliases must stay registered: designs authored before
// the unification still use them.
foreach (['LANDING_URL', 'LANDING_TITLE', 'MENU_PRIMARY', 'HUB_FORM'] as $name) {
    Hub_Test::assert_true(
        'variables: ' . $name . ' is still part of the contract',
        in_array($name, HUB_Tibox_Variables::names(), true)
    );
}

Hub_Test::assert_same(
    'variables: replace leaves content without variables untouched',
    '<p>Hola</p>',
    HUB_Tibox_Variables::replace('<p>Hola</p>')
);
