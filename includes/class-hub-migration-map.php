<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inventory of what renders each URL.
 *
 * Elementor is not removed by decision, it is removed by inventory. Before this
 * screen there was no way to answer "which pages still need Elementor?" other
 * than opening them one by one, and the historical MVP guessed by dequeuing any
 * handle whose name contained `jquery` or `swiper` — which is how a page loses
 * its slider and nobody notices for a week.
 */
final class HUB_Tibox_Migration_Map
{
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
        add_action('constructor_hub_admin_menu', [$this, 'register_page']);
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_settings()
            ? HUB_Tibox_Capabilities::MANAGE_SETTINGS
            : 'manage_options';

        add_submenu_page($parent, 'Mapa de migración', 'Mapa de migración', $capability, 'constructor-hub-map', [$this, 'render']);
    }

    /**
     * What renders one piece of content, and what it still depends on.
     *
     * @return array{renderer:string,elementor:bool,designs:string[],shortcodes:int,mode:string}
     */
    public function inspect(int $post_id): array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return ['renderer' => 'desconocido', 'elementor' => false, 'designs' => [], 'shortcodes' => 0, 'mode' => ''];
        }

        $needs_elementor = (bool) apply_filters('constructor_hub_elementor_needed', false, $post_id);
        $designs = $this->designs_in($post);

        if ($post->post_type === HUB_Tibox_Design::POST_TYPE) {
            $mode = HUB_Tibox_Design::get_render_mode($post_id);
            $renderers = [
                HUB_Tibox_Design::MODE_HUB => 'Constructor HUB',
                HUB_Tibox_Design::MODE_STANDALONE => 'HUB — documento completo',
                HUB_Tibox_Design::MODE_PACKAGE => 'HUB — package',
                HUB_Tibox_Design::MODE_LEGACY => 'Theme / Elementor',
            ];

            return [
                'renderer' => $renderers[$mode] ?? 'Constructor HUB',
                'elementor' => $needs_elementor,
                'designs' => $designs,
                'shortcodes' => count($designs),
                'mode' => $mode,
            ];
        }

        $renderer = 'Theme';
        if ($needs_elementor) {
            $renderer = 'Elementor';
        }
        if ($designs !== []) {
            $renderer .= ' + HUB';
        }

        return [
            'renderer' => $renderer,
            'elementor' => $needs_elementor,
            'designs' => $designs,
            'shortcodes' => count($designs),
            'mode' => '',
        ];
    }

    /**
     * HUB designs referenced from a piece of content, through the shortcode or
     * the block.
     *
     * @return string[]
     */
    private function designs_in(WP_Post $post): array
    {
        $found = [];
        $content = (string) $post->post_content;

        if (preg_match_all('/\[hub_design[^\]]*\]/', $content, $matches)) {
            foreach ($matches[0] as $shortcode) {
                $attributes = shortcode_parse_atts(trim($shortcode, '[]'));
                $reference = (string) ($attributes['slug'] ?? $attributes['id'] ?? '');
                if ($reference !== '') {
                    $found[] = $reference;
                }
            }
        }

        if (preg_match_all('/"slug"\s*:\s*"([a-z0-9\-]+)"/i', $content, $block_matches)) {
            foreach ($block_matches[1] as $slug) {
                if (str_contains($content, 'constructor-hub/design')) {
                    $found[] = $slug;
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function render(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $post_type = isset($_GET['hub_post_type']) ? sanitize_key(wp_unslash($_GET['hub_post_type'])) : 'page';
        $available = ['page' => 'Páginas', 'post' => 'Entradas', HUB_Tibox_Design::POST_TYPE => 'Diseños HUB'];
        if (!isset($available[$post_type])) {
            $post_type = 'page';
        }

        $items = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 300,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $totals = ['elementor' => 0, 'hub' => 0, 'theme' => 0];
        $rows = [];

        foreach ($items as $item) {
            $report = $this->inspect($item->ID);
            $rows[] = ['post' => $item, 'report' => $report];

            if ($report['elementor']) {
                $totals['elementor']++;
            } elseif ($report['designs'] !== [] || $item->post_type === HUB_Tibox_Design::POST_TYPE) {
                $totals['hub']++;
            } else {
                $totals['theme']++;
            }
        }
        ?>
        <div class="wrap">
            <h1>Mapa de migración</h1>
            <p>
                Qué renderiza cada URL hoy y de qué depende todavía. Es el inventario del que debe partir cualquier
                decisión de desactivar assets de Elementor: adivinar por nombre de handle es cómo una página pierde
                una funcionalidad sin que nadie se entere.
            </p>

            <div style="display:flex;gap:24px;flex-wrap:wrap;margin:20px 0;">
                <div style="border:1px solid #c3c4c7;background:#fff;padding:14px 22px;min-width:170px;">
                    <div style="font-size:26px;font-weight:700;"><?php echo esc_html((string) $totals['elementor']); ?></div>
                    <div>Todavía necesitan Elementor</div>
                </div>
                <div style="border:1px solid #c3c4c7;background:#fff;padding:14px 22px;min-width:170px;">
                    <div style="font-size:26px;font-weight:700;"><?php echo esc_html((string) $totals['hub']); ?></div>
                    <div>Con Constructor HUB</div>
                </div>
                <div style="border:1px solid #c3c4c7;background:#fff;padding:14px 22px;min-width:170px;">
                    <div style="font-size:26px;font-weight:700;"><?php echo esc_html((string) $totals['theme']); ?></div>
                    <div>Solo el theme</div>
                </div>
            </div>

            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="constructor-hub-map">
                <select name="hub_post_type">
                    <?php foreach ($available as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($post_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('Ver', 'secondary', '', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Contenido</th>
                        <th style="width:170px;">Renderiza</th>
                        <th style="width:150px;">Elementor</th>
                        <th>Componentes HUB</th>
                        <th style="width:110px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $post = $row['post'];
                    $report = $row['report'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($post->post_title ?: ('#' . $post->ID)); ?></strong><br>
                            <code><?php echo esc_html((string) wp_parse_url((string) get_permalink($post), PHP_URL_PATH)); ?></code>
                        </td>
                        <td><?php echo esc_html($report['renderer']); ?></td>
                        <td>
                            <?php if ($report['elementor']) : ?>
                                <strong style="color:#b32d2e;">Necesario</strong>
                            <?php else : ?>
                                <span style="color:#00713c;">No necesario</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($report['designs'] === []) : ?>
                                —
                            <?php else : ?>
                                <?php foreach ($report['designs'] as $slug) : ?>
                                    <code><?php echo esc_html($slug); ?></code>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url((string) get_edit_post_link($post->ID)); ?>">Editar</a>
                            ·
                            <a href="<?php echo esc_url((string) get_permalink($post)); ?>" target="_blank" rel="noopener">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                "Elementor necesario" se determina por el modo de edición guardado en el contenido y por el markup real,
                no por el nombre de los assets encolados. Una página marcada como no necesaria es candidata a que
                Constructor HUB descargue los assets de Elementor en esa URL.
            </p>
        </div>
        <?php
    }
}
