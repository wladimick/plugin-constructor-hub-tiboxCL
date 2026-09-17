<?php

require_once dirname(__DIR__) . '/includes/class-hub-page-assignment.php';
require_once dirname(__DIR__) . '/includes/class-hub-redesign-workflow.php';

$before = [
    'hero.title' => [
        'type' => 'text',
        'required' => true,
    ],
    'hero.image' => [
        'type' => 'media',
        'required' => false,
    ],
    'legacy.note' => [
        'type' => 'textarea',
        'required' => false,
    ],
];

$after = [
    'hero.title' => [
        'type' => 'text',
        'required' => true,
    ],
    'hero.image' => [
        'type' => 'url',
        'required' => false,
    ],
    'hero.eyebrow' => [
        'type' => 'text',
        'required' => false,
        'default' => 'TIBOX',
    ],
    'hero.cta' => [
        'type' => 'text',
        'required' => true,
    ],
];

$diff = HUB_Tibox_Redesign_Workflow::compare_schemas($before, $after);

Hub_Test::assert_same(
    'redesign: schema diff detects added fields',
    ['hero.cta', 'hero.eyebrow'],
    $diff['added']
);

Hub_Test::assert_same(
    'redesign: schema diff detects removed fields without deleting them',
    ['legacy.note'],
    $diff['removed']
);

Hub_Test::assert_same(
    'redesign: schema diff detects field type changes',
    ['from' => 'media', 'to' => 'url'],
    $diff['type_changed']['hero.image'] ?? null
);

Hub_Test::assert_same(
    'redesign: new required field without default is flagged',
    ['hero.cta'],
    $diff['required_without_default']
);

Hub_Test::assert_true(
    'redesign: incompatible schema forces manual review',
    HUB_Tibox_Redesign_Workflow::has_breaking_schema_change($diff)
);

$compatible = HUB_Tibox_Redesign_Workflow::compare_schemas(
    ['hero.title' => ['type' => 'text', 'required' => true]],
    [
        'hero.title' => ['type' => 'text', 'required' => true],
        'hero.subtitle' => ['type' => 'text', 'required' => false, 'default' => ''],
    ]
);

Hub_Test::assert_false(
    'redesign: additive compatible schema can be published',
    HUB_Tibox_Redesign_Workflow::has_breaking_schema_change($compatible)
);

Hub_Test::assert_same(
    'redesign: live HUB renderer remains HUB while reviewing a draft',
    HUB_Tibox_Page_Assignment::MODE_HUB,
    HUB_Tibox_Redesign_Workflow::renderer_after(
        HUB_Tibox_Page_Assignment::MODE_HUB,
        false,
        false
    )
);

Hub_Test::assert_same(
    'redesign: Theme stays Theme when import is only a preview',
    HUB_Tibox_Page_Assignment::MODE_THEME,
    HUB_Tibox_Redesign_Workflow::renderer_after(
        HUB_Tibox_Page_Assignment::MODE_THEME,
        false,
        false
    )
);

Hub_Test::assert_same(
    'redesign: Theme does not switch if publication failed',
    HUB_Tibox_Page_Assignment::MODE_THEME,
    HUB_Tibox_Redesign_Workflow::renderer_after(
        HUB_Tibox_Page_Assignment::MODE_THEME,
        true,
        false
    )
);

Hub_Test::assert_same(
    'redesign: Theme switches to HUB only after explicit activation and publish',
    HUB_Tibox_Page_Assignment::MODE_HUB,
    HUB_Tibox_Redesign_Workflow::renderer_after(
        HUB_Tibox_Page_Assignment::MODE_THEME,
        true,
        true
    )
);
