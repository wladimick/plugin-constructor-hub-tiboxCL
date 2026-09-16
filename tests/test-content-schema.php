<?php

require_once dirname(__DIR__) . '/includes/class-hub-content.php';

$schema = HUB_Tibox_Content::validate_schema([
    'hero.title' => [
        'type' => 'text',
        'label' => 'Título',
        'default' => 'Hola',
        'required' => true,
        'group' => 'Hero',
    ],
    'hero.image' => [
        'type' => 'media',
        'label' => 'Imagen',
    ],
    'hero.variant' => [
        'type' => 'select',
        'label' => 'Variante',
        'options' => [
            'light' => 'Clara',
            'dark' => 'Oscura',
        ],
        'default' => 'dark',
    ],
]);

Hub_Test::assert_true(
    'content schema: valid schema is normalized',
    is_array($schema)
);

Hub_Test::assert_same(
    'content schema: normalized field type',
    'text',
    is_array($schema) ? $schema['hero.title']['type'] : null
);

Hub_Test::assert_same(
    'content schema: valid select default is preserved',
    'dark',
    is_array($schema) ? $schema['hero.variant']['default'] : null
);

Hub_Test::assert_same(
    'content schema: references are extracted once',
    ['hero.title', 'hero.image.url', 'hero.image.alt'],
    HUB_Tibox_Content::references_in(
        '<h1>{{CONTENT.hero.title}}</h1><img src="{{CONTENT.hero.image.url}}" alt="{{ CONTENT.hero.image.alt }}"><span>{{CONTENT.hero.title}}</span>'
    )
);

Hub_Test::assert_same(
    'content schema: media URL and alt suffixes are valid references',
    [],
    is_array($schema)
        ? HUB_Tibox_Content::unknown_references(
            '{{CONTENT.hero.title}}{{CONTENT.hero.image.url}}{{CONTENT.hero.image.alt}}{{CONTENT.hero.image.id}}',
            $schema
        )
        : ['schema-invalid']
);

Hub_Test::assert_same(
    'content schema: undeclared reference is detected',
    ['hero.subtitle'],
    is_array($schema)
        ? HUB_Tibox_Content::unknown_references('{{CONTENT.hero.subtitle}}', $schema)
        : ['schema-invalid']
);

$invalid_path = HUB_Tibox_Content::validate_schema([
    'Hero Title' => ['type' => 'text'],
]);

Hub_Test::assert_true(
    'content schema: invalid path returns WP_Error',
    $invalid_path instanceof WP_Error
);

$invalid_type = HUB_Tibox_Content::validate_schema([
    'hero.title' => ['type' => 'html'],
]);

Hub_Test::assert_true(
    'content schema: raw HTML field type is rejected',
    $invalid_type instanceof WP_Error
);

$invalid_select = HUB_Tibox_Content::validate_schema([
    'hero.variant' => ['type' => 'select'],
]);

Hub_Test::assert_true(
    'content schema: select requires options',
    $invalid_select instanceof WP_Error
);
