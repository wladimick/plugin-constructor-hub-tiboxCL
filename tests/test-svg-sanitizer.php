<?php

require_once dirname(__DIR__) . '/includes/class-hub-landing-zip-importer.php';

$clean = static function (string $svg): string {
    $result = HUB_Tibox_Landing_Zip_Importer::sanitize_svg($svg);
    return $result === null ? '__UNPARSEABLE__' : $result;
};

$base = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">';

$script = $clean($base . '<script>alert(1)</script><circle cx="5" cy="5" r="4"/></svg>');
Hub_Test::assert_false('svg: script elements are removed', str_contains($script, '<script'));
Hub_Test::assert_true('svg: legitimate shapes survive', str_contains($script, '<circle'));

$handler = $clean($base . '<rect width="10" height="10" onload="alert(1)" onclick="x()"/></svg>');
Hub_Test::assert_false('svg: onload handler is removed', str_contains($handler, 'onload'));
Hub_Test::assert_false('svg: onclick handler is removed', str_contains($handler, 'onclick'));
Hub_Test::assert_true('svg: the element itself survives', str_contains($handler, '<rect'));

$href = $clean($base . '<a href="javascript:alert(1)"><text>x</text></a></svg>');
Hub_Test::assert_false('svg: javascript: href is removed', str_contains(strtolower($href), 'javascript:'));

$spaced = $clean($base . '<a href="java&#9;script:alert(1)"><text>x</text></a></svg>');
Hub_Test::assert_false('svg: obfuscated javascript: href is removed', str_contains(strtolower($spaced), 'javascript:'));

$foreign = $clean($base . '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject></svg>');
Hub_Test::assert_false('svg: foreignObject is removed', str_contains($foreign, 'foreignObject'));

$style = $clean($base . '<rect style="fill:url(javascript:alert(1))"/></svg>');
Hub_Test::assert_false('svg: javascript in style is removed', str_contains(strtolower($style), 'javascript:'));

$plain = $clean($base . '<path d="M0 0 L10 10" fill="#123456"/></svg>');
Hub_Test::assert_true('svg: a plain path is preserved', str_contains($plain, 'M0 0 L10 10'));
Hub_Test::assert_true('svg: fill attributes are preserved', str_contains($plain, '#123456'));

Hub_Test::assert_same('svg: broken XML is rejected', '__UNPARSEABLE__', $clean('<svg><unclosed>'));
Hub_Test::assert_same('svg: an empty file is a no-op', '', $clean(''));
