<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateBookingItemsTable
 *
 * Migration for creating the booking items line table: wp_tourivo_booking_items.
 *
 * @package Tourivo\Database\Migrations
 */
class CreateBookingItemsTable implements MigrationInterface
{
    public function getName(): string
    {
        return 'create_tourivo_booking_items_table';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'booking_items';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            item_id bigint(20) unsigned NOT NULL,
            item_type varchar(30) NOT NULL DEFAULT 'tour',
            parent_item_id bigint(20) unsigned DEFAULT NULL,
            item_title varchar(255) NOT NULL DEFAULT '',
            check_in datetime NOT NULL,
            check_out datetime DEFAULT NULL,
            time_slot varchar(50) DEFAULT NULL,
            adults_count int(11) NOT NULL DEFAULT 1,
            children_count int(11) NOT NULL DEFAULT 0,
            infants_count int(11) NOT NULL DEFAULT 0,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(12,2) NOT NULL DEFAULT '0.00',
            total_price decimal(12,2) NOT NULL DEFAULT '0.00',
            pricing_breakdown longtext,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY item_id (item_id),
            KEY item_type (item_type),
            KEY check_in (check_in)
        ) {$charsetCollate};";
    }

    public function down(string $prefix): string
    {
        $table = $prefix . 'booking_items';
        return "DROP TABLE IF EXISTS {$table};";
    }
}
