<?php

require_once dirname(__DIR__) . '/includes/class-hub-variables.php';
require_once dirname(__DIR__) . '/includes/class-hub-content.php';
require_once dirname(__DIR__) . '/includes/class-hub-publish-guard.php';

$schema = HUB_Tibox_Content::validate_schema([
    'hero.title' => [
        'type' => 'text',
        'label' => 'Título principal',
        'required' => true,
    ],
    'hero.image' => [
        'type' => 'media',
        'label' => 'Imagen',
    ],
]);

$schema = is_array($schema) ? $schema : [];

$clean = HUB_Tibox_Publish_Guard::inspect_payload(
    'page',
    '<section><h1>{{CONTENT.hero.title}}</h1><img src="{{CONTENT.hero.image.url}}" alt="{{CONTENT.hero.image.alt}}"></section>',
    '',
    $schema,
    ['hero.title' => 'Página de prueba', 'hero.image' => 15]
);

Hub_Test::assert_same(
    'publish guard: clean page has no blockers',
    [],
    $clean['errors']
);

Hub_Test::assert_same(
    'publish guard: clean page has no warnings',
    [],
    $clean['warnings']
);

$unknown_content = HUB_Tibox_Publish_Guard::inspect_payload(
    'page',
    '<h1>{{CONTENT.hero.subtitle}}</h1>',
    '',
    $schema,
    ['hero.title' => 'Título']
);

Hub_Test::assert_true(
    'publish guard: undeclared CONTENT field blocks publication',
    in_array(
        'Campo de contenido no declarado: {{CONTENT.hero.subtitle}}.',
        $unknown_content['errors'],
        true
    )
);

$unknown_variable = HUB_Tibox_Publish_Guard::inspect_payload(
    'page',
    '<h1>{{PAGE_TITLE}}</h1><p>{{INVENTED_VARIABLE}}</p>',
    '',
    [],
    []
);

Hub_Test::assert_true(
    'publish guard: unknown fixed variable blocks publication',
    in_array('Variable no registrada: {{INVENTED_VARIABLE}}.', $unknown_variable['errors'], true)
);

$seo_warning = HUB_Tibox_Publish_Guard::inspect_payload(
    'page',
    '<section><h2>Sin H1</h2><img src="photo.webp"></section>',
    '',
    [],
    []
);

Hub_Test::assert_true(
    'publish guard: missing H1 is warning, not blocker',
    $seo_warning['errors'] === [] && in_array(
        'La página no contiene un H1 en el contenido HUB.',
        $seo_warning['warnings'],
        true
    )
);

Hub_Test::assert_same(
    'publish guard: image without alt is counted',
    1,
    $seo_warning['metrics']['images_missing_alt']
);

$risky = HUB_Tibox_Publish_Guard::inspect_payload(
    'section',
    '<div onclick="openThing()"><script src="https://cdn.example.com/app.js"></script></div>',
    'eval(code); fetch("https://api.example.com/data"); document.cookie;',
    [],
    []
);

Hub_Test::assert_true(
    'publish guard: risky JavaScript is surfaced for manual review',
    count($risky['warnings']) >= 4
);

$empty = HUB_Tibox_Publish_Guard::inspect_payload('page', '', '', [], []);

Hub_Test::assert_true(
    'publish guard: empty page blocks publication',
    in_array('El diseño no contiene HTML utilizable.', $empty['errors'], true)
);

$required = HUB_Tibox_Publish_Guard::inspect_payload(
    'hero',
    '<div>{{CONTENT.hero.title}}</div>',
    '',
    $schema,
    ['hero.title' => '', 'hero.image' => 0]
);

Hub_Test::assert_true(
    'publish guard: empty required editorial field is warning',
    in_array(
        'El campo obligatorio "Título principal" no tiene un valor base.',
        $required['warnings'],
        true
    )
);
