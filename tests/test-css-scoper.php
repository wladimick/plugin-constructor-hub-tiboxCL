<?php

require_once dirname(__DIR__) . '/includes/class-hub-css-scoper.php';

$normalize = static fn(string $value): string => (string) preg_replace('/\s+/', ' ', trim($value));

$cases = [
    'custom properties move to the scope root' => [':root{--a:1px}', '.s{--a:1px}'],
    'body becomes the scope' => ['body{margin:0}', '.s{margin:0}'],
    'plain selector is prefixed' => ['nav a{color:red}', '.s nav a{color:red}'],
    'selector list is prefixed per selector' => ['h1,h2 > .x{color:red}', '.s h1,.s h2 > .x{color:red}'],
    'media queries are recursed' => ['@media (max-width:600px){nav{display:none}}', '@media (max-width:600px){.s nav{display:none}}'],
    'keyframes stay untouched' => ['@keyframes spin{from{opacity:0}to{opacity:1}}', '@keyframes spin{from{opacity:0}to{opacity:1}}'],
    'font-face stays untouched' => ['@font-face{font-family:"X";src:url(a.woff2)}', '@font-face{font-family:"X";src:url(a.woff2)}'],
    'import statement survives' => ['@import url("x.css");a{color:red}', '@import url("x.css");.s a{color:red}'],
    'already scoped selector is left alone' => ['.s .already{color:red}', '.s .already{color:red}'],
    'comma inside an attribute selector is not a separator' => ['a[href*=","]{color:red}', '.s a[href*=","]{color:red}'],
    'comma inside :is() is not a separator' => [':is(h1,h2){color:red}', '.s :is(h1,h2){color:red}'],
    'comments are preserved' => ['/* c */ p{color:red}', '/* c */.s p{color:red}'],
    'supports is recursed' => ['@supports (display:grid){body{display:grid}}', '@supports (display:grid){.s{display:grid}}'],
    'html body prefix collapses to the scope' => ['html body .z{color:red}', '.s .z{color:red}'],
    'nested media inside supports' => [
        '@supports (display:grid){@media screen{a{color:red}}}',
        '@supports (display:grid){@media screen{.s a{color:red}}}',
    ],
    'empty stylesheet is a no-op' => ['', ''],
];

foreach ($cases as $name => [$input, $expected]) {
    Hub_Test::assert_same(
        'css-scoper: ' . $name,
        $normalize($expected),
        $normalize(HUB_Tibox_Css_Scoper::scope($input, '.s'))
    );
}

Hub_Test::assert_same(
    'css-scoper: an empty scope returns the stylesheet unchanged',
    'a{color:red}',
    HUB_Tibox_Css_Scoper::scope('a{color:red}', '')
);
