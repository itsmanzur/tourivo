<?php

declare(strict_types=1);

namespace Tourivo\Elementor\Widgets;

use Tourivo\Shortcodes\TourGridShortcode;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Class TourGridWidget
 *
 * Elementor Widget for displaying Tours in a modern grid.
 *
 * @package Tourivo\Elementor\Widgets
 */
class TourGridWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'tourivo_tour_grid';
    }

    public function get_title(): string
    {
        return esc_html__('Tour Grid', 'tourivo');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array
    {
        return ['tourivo-elements'];
    }

    public function get_keywords(): array
    {
        return ['tourivo', 'tour', 'travel', 'grid', 'package'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Grid Settings', 'tourivo'),
            ]
        );

        $this->add_control(
            'count',
            [
                'label'   => esc_html__('Tours Count', 'tourivo'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 50,
                'default' => 6,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => esc_html__('Columns', 'tourivo'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1 Column',
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
            ]
        );

        $this->add_control(
            'destination',
            [
                'label'       => esc_html__('Filter by Destination Slug', 'tourivo'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'e.g. bali',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $content = TourGridShortcode::render([
            'count'       => isset($settings['count']) ? absint($settings['count']) : 6,
            'columns'     => isset($settings['columns']) ? absint($settings['columns']) : 3,
            'destination' => isset($settings['destination']) ? sanitize_text_field($settings['destination']) : '',
        ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
    }
}
