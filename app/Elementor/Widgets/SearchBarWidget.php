<?php

declare(strict_types=1);

namespace Tourivo\Elementor\Widgets;

use Tourivo\Shortcodes\SearchBarShortcode;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Class SearchBarWidget
 *
 * Elementor Widget for embedding the Tourivo search filter bar.
 *
 * @package Tourivo\Elementor\Widgets
 */
class SearchBarWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'tourivo_search_bar';
    }

    public function get_title(): string
    {
        return esc_html__('Search Bar', 'tourivo');
    }

    public function get_icon(): string
    {
        return 'eicon-search-results';
    }

    public function get_categories(): array
    {
        return ['tourivo-elements'];
    }

    public function get_keywords(): array
    {
        return ['tourivo', 'search', 'bar', 'filter', 'destination'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Search Bar Settings', 'tourivo'),
            ]
        );

        $this->add_control(
            'info_notice',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw'  => esc_html__('This widget renders the responsive Destination, Date, and Travelers search bar.', 'tourivo'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SearchBarShortcode::render();
    }
}
