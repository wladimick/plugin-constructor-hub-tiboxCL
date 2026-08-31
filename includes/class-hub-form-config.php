<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form settings resolved for whatever object hosts the form.
 *
 * The form pipeline used to read its configuration straight from the Landings
 * manager, which tied a generic backend to one post type. A form can live on a
 * HUB design, on a legacy landing or on an ordinary page, and the endpoint must
 * not care which.
 */
final class HUB_Tibox_Form_Config
{
    private const LEGACY_POST_TYPE = 'hub_landing';

    /** @return string[] */
    public static function required_fields(int $host_id): array
    {
        if (self::is_design($host_id)) {
            return HUB_Tibox_Design::get_required_fields($host_id);
        }

        if (self::is_legacy_landing($host_id)) {
            $value = get_post_meta($host_id, '_hub_landing_required_fields', true);
            return is_array($value) ? array_values(array_map('sanitize_key', $value)) : [];
        }

        return (array) apply_filters('constructor_hub_form_required_fields', [], $host_id);
    }

    public static function success_message(int $host_id): string
    {
        $default = 'Gracias. Recibimos tus datos y te contactaremos pronto.';

        if (self::is_design($host_id)) {
            return HUB_Tibox_Design::get_success_message($host_id);
        }

        if (self::is_legacy_landing($host_id)) {
            $message = trim((string) get_post_meta($host_id, '_hub_landing_success_message', true));
            return $message !== '' ? $message : $default;
        }

        return (string) apply_filters('constructor_hub_form_success_message', $default, $host_id);
    }

    public static function recipients(int $host_id): string
    {
        if (self::is_design($host_id)) {
            return HUB_Tibox_Design::get_recipient_emails($host_id);
        }

        if (self::is_legacy_landing($host_id)) {
            return (string) get_post_meta($host_id, '_hub_landing_recipient_emails', true);
        }

        return (string) apply_filters('constructor_hub_form_recipients', '', $host_id);
    }

    public static function confirmation_override(int $host_id): ?bool
    {
        $value = '';

        if (self::is_design($host_id)) {
            return HUB_Tibox_Design::get_confirmation_override($host_id);
        }

        if (self::is_legacy_landing($host_id)) {
            $value = (string) get_post_meta($host_id, '_hub_landing_confirmation', true);
        }

        if ($value === 'yes') {
            return true;
        }
        if ($value === 'no') {
            return false;
        }

        return null;
    }

    public static function is_design(int $host_id): bool
    {
        return $host_id > 0
            && class_exists('HUB_Tibox_Design')
            && get_post_type($host_id) === HUB_Tibox_Design::POST_TYPE;
    }

    public static function is_legacy_landing(int $host_id): bool
    {
        return $host_id > 0 && get_post_type($host_id) === self::LEGACY_POST_TYPE;
    }
}
