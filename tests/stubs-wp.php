<?php
/**
 * The few WordPress primitives the pure-PHP tests touch.
 *
 * Deliberately minimal: these tests cover algorithms, not WordPress. Anything
 * that needs a real database belongs in an integration suite, not here.
 */

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', ' ', wp_strip_all_tags($value)));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(wp_strip_all_tags($value));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower(trim($title));
        $title = (string) preg_replace('/[^a-z0-9\s\-]/', '', $title);

        return trim((string) preg_replace('/[\s\-]+/', '-', $title), '-');
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $class);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action(): bool
    {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(): bool
    {
        return true;
    }
}
