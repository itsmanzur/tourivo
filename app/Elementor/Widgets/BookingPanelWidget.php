<?php

declare(strict_types=1);

namespace Tourivo\Elementor\Widgets;

use Tourivo\Shortcodes\BookingPanelShortcode;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

/**
 * Class BookingPanelWidget
 *
 * Elementor Widget for embedding the interactive booking panel.
 *
 * @package Tourivo\Elementor\Widgets
 */
class BookingPanelWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'tourivo_booking_panel';
    }

    public function get_title(): string
    {
        return esc_html__('Booking Panel', 'tourivo');
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array
    {
        return ['tourivo-elements'];
    }

    public function get_keywords(): array
    {
        return ['tourivo', 'booking', 'panel', 'checkout', 'reservation'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Booking Settings', 'tourivo'),
            ]
        );

        $this->add_control(
            'item_id',
            [
                'label'       => esc_html__('Custom Item ID (Leave 0 for Current Page)', 'tourivo'),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'default'     => 0,
            ]
        );

        $this->add_control(
            'item_type',
            [
                'label'   => esc_html__('Item Type', 'tourivo'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'tour',
                'options' => [
                    'tour'       => 'Tour Package',
                    'hotel_room' => 'Hotel Room',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $itemId = !empty($settings['item_id']) ? (int) $settings['item_id'] : get_the_ID();

        echo BookingPanelShortcode::render([
            'item_id'   => $itemId,
            'item_type' => $settings['item_type'] ?? 'tour',
        ]);
    }
}
