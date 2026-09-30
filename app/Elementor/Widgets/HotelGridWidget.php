<?php

declare(strict_types=1);

namespace Tourivo\Elementor\Widgets;

use Tourivo\Shortcodes\HotelGridShortcode;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Class HotelGridWidget
 *
 * Elementor Widget for displaying Hotels in a grid.
 *
 * @package Tourivo\Elementor\Widgets
 */
class HotelGridWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'tourivo_hotel_grid';
    }

    public function get_title(): string
    {
        return esc_html__('Hotel Grid', 'tourivo');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-grid';
    }

    public function get_categories(): array
    {
        return ['tourivo-elements'];
    }

    public function get_keywords(): array
    {
        return ['tourivo', 'hotel', 'resort', 'stay', 'grid'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Hotel Grid Settings', 'tourivo'),
            ]
        );

        $this->add_control(
            'count',
            [
                'label'   => esc_html__('Hotels Count', 'tourivo'),
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
                'placeholder' => 'e.g. maldives',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $content = HotelGridShortcode::render([
            'count'       => isset($settings['count']) ? absint($settings['count']) : 6,
            'columns'     => isset($settings['columns']) ? absint($settings['columns']) : 3,
            'destination' => isset($settings['destination']) ? sanitize_text_field($settings['destination']) : '',
        ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
    }
}
