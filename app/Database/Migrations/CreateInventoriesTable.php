<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateInventoriesTable
 *
 * Migration for creating capacity and inventory tracking table: wp_tourivo_inventories.
 *
 * @package Tourivo\Database\Migrations
 */
class CreateInventoriesTable implements MigrationInterface
{
    public function getName(): string
    {
        return 'create_tourivo_inventories_table';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'inventories';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            item_type varchar(30) NOT NULL DEFAULT 'tour',
            event_date date NOT NULL,
            time_slot varchar(50) NOT NULL DEFAULT 'all_day',
            total_capacity int(11) NOT NULL DEFAULT 0,
            booked_count int(11) NOT NULL DEFAULT 0,
            reserved_count int(11) NOT NULL DEFAULT 0,
            price_override decimal(12,2) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY item_slot_date (item_id, item_type, event_date, time_slot),
            KEY event_date (event_date),
            KEY status (status)
        ) {$charsetCollate};";
    }

    public function down(string $prefix): string
    {
        $table = $prefix . 'inventories';
        return "DROP TABLE IF EXISTS {$table};";
    }
}
