<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Spam controls for the public form endpoint.
 *
 * A public lead form cannot use a WordPress nonce: nonces are bound to a
 * session and to a 12 hour window, and any page cache would hand every visitor
 * the same expired token. What works instead is a signed, stateless form token
 * that proves the form was rendered by this site and measures how long the
 * visitor took, plus the honeypot and the rate budgets.
 *
 * A CAPTCHA is deliberately not implemented here. It belongs in an adapter, and
 * the hook below is where one plugs in.
 */
final class HUB_Tibox_Antispam
{
    /** Minimum seconds between rendering the form and submitting it. */
    private const MIN_SECONDS = 3;

    /** After this the token is stale and the visitor should reload. */
    private const MAX_SECONDS = 12 * HOUR_IN_SECONDS;

    /**
     * Token embedded in every rendered form.
     *
     * Signed with the site salt, so it cannot be minted elsewhere, and safe to
     * serve from a page cache for as long as MAX_SECONDS.
     */
    public static function issue_token(int $host_id): string
    {
        $issued = time();

        return $issued . '.' . self::signature($host_id, $issued);
    }

    /**
     * @return true|WP_Error
     */
    public static function validate(int $host_id, string $token, array $payload)
    {
        if (!empty($payload['website'])) {
            // Honeypot. Handled by the caller as a silent success so the bot
            // does not learn anything.
            return new WP_Error('hub_honeypot', 'Honeypot completado.');
        }

        $enforce = (bool) apply_filters('constructor_hub_enforce_form_token', true, $host_id);
        if (!$enforce) {
            return true;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return new WP_Error('hub_token_missing', 'Vuelve a cargar la página e inténtalo de nuevo.');
        }

        $issued = (int) $parts[0];
        if (!hash_equals(self::signature($host_id, $issued), $parts[1])) {
            return new WP_Error('hub_token_invalid', 'Vuelve a cargar la página e inténtalo de nuevo.');
        }

        $elapsed = time() - $issued;

        if ($elapsed > self::MAX_SECONDS) {
            return new WP_Error('hub_token_expired', 'El formulario caducó. Vuelve a cargar la página.');
        }

        $minimum = (int) apply_filters('constructor_hub_min_form_seconds', self::MIN_SECONDS, $host_id);
        if ($elapsed < $minimum) {
            return new WP_Error('hub_too_fast', 'El envío se completó demasiado rápido. Inténtalo de nuevo.');
        }

        /**
         * Extension point for reCAPTCHA, Turnstile or an external anti spam
         * service. Return a WP_Error to reject the submission.
         *
         * @param true|WP_Error       $result
         * @param int                 $host_id
         * @param array<string,mixed> $payload
         */
        return apply_filters('constructor_hub_antispam_check', true, $host_id, $payload);
    }

    private static function signature(int $host_id, int $issued): string
    {
        return hash_hmac('sha256', $host_id . '|' . $issued, wp_salt('nonce'));
    }
}
