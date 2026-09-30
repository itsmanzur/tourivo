<?php

declare(strict_types=1);

namespace Tourivo\Elementor;

use Tourivo\Elementor\Widgets\BookingPanelWidget;
use Tourivo\Elementor\Widgets\HotelGridWidget;
use Tourivo\Elementor\Widgets\SearchBarWidget;
use Tourivo\Elementor\Widgets\TourGridWidget;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ElementorManager
 *
 * Integrates Tourivo with Elementor Page Builder.
 *
 * @package Tourivo\Elementor
 */
class ElementorManager
{
    /**
     * Check if Elementor is installed and loaded.
     *
     * @return bool
     */
    public static function isElementorActive(): bool
    {
        return did_action('elementor/loaded') || class_exists('\Elementor\Plugin');
    }

    /**
     * Register Tourivo custom category in Elementor widget panel.
     *
     * @param \Elementor\Elements_Manager $elementsManager
     * @return void
     */
    public static function registerCategories($elementsManager): void
    {
        $elementsManager->add_category(
            'tourivo-elements',
            [
                'title' => esc_html__('Tourivo Elements', 'tourivo'),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    /**
     * Register Elementor Widgets.
     *
     * @param \Elementor\Widgets_Manager $widgetsManager
     * @return void
     */
    public static function registerWidgets($widgetsManager): void
    {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        $widgets = [
            TourGridWidget::class,
            HotelGridWidget::class,
            BookingPanelWidget::class,
            SearchBarWidget::class,
        ];

        foreach ($widgets as $widgetClass) {
            if (class_exists($widgetClass)) {
                $widgetsManager->register(new $widgetClass());
            }
        }
    }
}
