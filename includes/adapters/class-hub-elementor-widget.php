<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Elementor widget that drops a HUB design inside an Elementor page.
 *
 * This is the migration path in practice: replace one section at a time inside
 * a page that keeps working, instead of rebuilding the whole page in HUB mode.
 */
final class HUB_Tibox_Elementor_Widget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'hub_design';
    }

    public function get_title(): string
    {
        return 'Diseño HUB';
    }

    public function get_icon(): string
    {
        return 'eicon-code-highlight';
    }

    /** @return string[] */
    public function get_categories(): array
    {
        return ['general'];
    }

    /** @return string[] */
    public function get_keywords(): array
    {
        return ['hub', 'constructor', 'header', 'footer', 'hero', 'landing'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('hub_content', [
            'label' => 'Diseño HUB',
        ]);

        $options = ['' => '— Seleccionar —'];
        foreach (HUB_Tibox_Insertion::instance()->design_choices() as $choice) {
            $options[$choice['value']] = $choice['label'];
        }

        $this->add_control('slug', [
            'label' => 'Componente',
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $options,
            'default' => '',
        ]);

        $this->add_control('hub_note', [
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw' => 'El contenido lo administra Constructor HUB. Publicar una versión nueva del componente actualiza todas las páginas donde esté insertado.',
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $slug = (string) ($settings['slug'] ?? '');

        if ($slug === '') {
            return;
        }

        $design_id = HUB_Tibox_Design::resolve($slug);
        if ($design_id <= 0) {
            return;
        }

        HUB_Tibox_Render::instance()->output($design_id);
    }
}
