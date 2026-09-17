<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Native WordPress updates for Constructor HUB releases published on GitHub.
 *
 * WordPress 5.8+ supports third-party update providers through the Update URI
 * plugin header and the dynamic update_plugins_{hostname} filter. This class
 * deliberately consumes only stable GitHub Releases and only accepts the exact
 * installable ZIP asset produced by this repository's release workflow.
 */
final class HUB_Tibox_Release_Updater
{
    public const UPDATE_URI = 'https://github.com/wladimick/plugin-constructor-hub-tiboxCL';

    private const API_LATEST = 'https://api.github.com/repos/wladimick/plugin-constructor-hub-tiboxCL/releases/latest';
    private const RELEASES_URL = 'https://github.com/wladimick/plugin-constructor-hub-tiboxCL/releases';
    private const CACHE_KEY = 'hub_tibox_latest_release';
    private const CACHE_SECONDS = 21600; // 6 hours.
    private const FAILURE_CACHE_SECONDS = 3600; // 1 hour.

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('update_plugins_github.com', [$this, 'filter_update'], 10, 4);
        add_filter('plugin_row_meta', [$this, 'row_meta'], 10, 2);
    }

    /** Normalize release tags such as v0.6.0 to WordPress version strings. */
    public static function normalize_version(string $tag): string
    {
        $tag = trim($tag);
        if ($tag !== '' && ($tag[0] === 'v' || $tag[0] === 'V')) {
            $tag = substr($tag, 1);
        }

        return preg_replace('/[^0-9A-Za-z.+_-]/', '', $tag) ?? '';
    }

    public static function expected_asset_name(string $version): string
    {
        return 'constructor-hub-tibox-' . $version . '.zip';
    }

    /**
     * Choose only the exact release asset produced by our packaging workflow.
     * GitHub source-code archives are intentionally never accepted as updates.
     *
     * @param array<int,mixed> $assets
     * @return array<string,mixed>|null
     */
    public static function select_asset(array $assets, string $version): ?array
    {
        $expected = self::expected_asset_name($version);

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            if ((string) ($asset['name'] ?? '') !== $expected) {
                continue;
            }

            $url = (string) ($asset['browser_download_url'] ?? '');
            if (!self::is_trusted_package_url($url)) {
                continue;
            }

            return $asset;
        }

        return null;
    }

    public static function is_newer_stable_release(
        string $current_version,
        string $release_version,
        bool $draft,
        bool $prerelease
    ): bool {
        if ($draft || $prerelease || $release_version === '') {
            return false;
        }

        return version_compare($release_version, $current_version, '>');
    }

    public static function is_trusted_package_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https' || $host !== 'github.com') {
            return false;
        }

        return str_starts_with(
            $path,
            '/wladimick/plugin-constructor-hub-tiboxCL/releases/download/'
        );
    }

    /**
     * WordPress dynamic Update URI provider.
     *
     * @param array<string,mixed>|false $update
     * @param array<string,mixed>       $plugin_data
     * @param string                    $plugin_file
     * @param string[]                  $locales
     * @return array<string,mixed>|false
     */
    public function filter_update($update, array $plugin_data, string $plugin_file, array $locales)
    {
        unset($locales);

        if (!defined('TIBOX_AI_FRONTEND_FILE')) {
            return $update;
        }

        if ($plugin_file !== plugin_basename(TIBOX_AI_FRONTEND_FILE)) {
            return $update;
        }

        if ((string) ($plugin_data['UpdateURI'] ?? '') !== self::UPDATE_URI) {
            return $update;
        }

        $release = $this->latest_release();
        if ($release === null) {
            return false;
        }

        $release_version = self::normalize_version((string) ($release['tag_name'] ?? ''));
        $current_version = (string) ($plugin_data['Version'] ?? TIBOX_AI_FRONTEND_VERSION);

        if (!self::is_newer_stable_release(
            $current_version,
            $release_version,
            !empty($release['draft']),
            !empty($release['prerelease'])
        )) {
            return false;
        }

        $asset = self::select_asset((array) ($release['assets'] ?? []), $release_version);
        if ($asset === null) {
            return false;
        }

        return [
            'id' => self::UPDATE_URI,
            'version' => $release_version,
            'url' => esc_url_raw((string) ($release['html_url'] ?? self::RELEASES_URL)),
            'package' => esc_url_raw((string) ($asset['browser_download_url'] ?? '')),
            'requires_php' => '8.0',
            'autoupdate' => false,
        ];
    }

    /**
     * Add a stable navigation link without hijacking WordPress' update UI.
     *
     * @param string[] $links
     * @return string[]
     */
    public function row_meta(array $links, string $file): array
    {
        if (!defined('TIBOX_AI_FRONTEND_FILE') || $file !== plugin_basename(TIBOX_AI_FRONTEND_FILE)) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::RELEASES_URL),
            esc_html__('Versiones en GitHub', 'constructor-hub-tibox')
        );

        return $links;
    }

    /** @return array<string,mixed>|null */
    private function latest_release(): ?array
    {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            if (($cached['status'] ?? '') === 'none') {
                return null;
            }

            if (($cached['status'] ?? '') === 'ok' && is_array($cached['release'] ?? null)) {
                return $cached['release'];
            }
        }

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'Constructor-HUB-Tibox/' . TIBOX_AI_FRONTEND_VERSION,
        ];

        /**
         * Optional token hook for installations that need a larger GitHub API
         * rate limit. No credential is stored by Constructor HUB.
         */
        $token = (string) apply_filters('constructor_hub_github_update_token', '');
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($token);
        }

        $response = wp_remote_get(self::API_LATEST, [
            'timeout' => 8,
            'redirection' => 3,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            $this->cache_no_release();
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->cache_no_release();
            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            $this->cache_no_release();
            return null;
        }

        set_site_transient(
            self::CACHE_KEY,
            ['status' => 'ok', 'release' => $decoded],
            self::CACHE_SECONDS
        );

        return $decoded;
    }

    private function cache_no_release(): void
    {
        set_site_transient(
            self::CACHE_KEY,
            ['status' => 'none'],
            self::FAILURE_CACHE_SECONDS
        );
    }
}
