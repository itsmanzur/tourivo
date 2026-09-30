<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateLogsTable
 *
 * Migration for creating audit and activity log table: wp_tourivo_logs.
 *
 * @package Tourivo\Database\Migrations
 */
class CreateLogsTable implements MigrationInterface
{
    public function getName(): string
    {
        return 'create_tourivo_logs_table';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'logs';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned DEFAULT 0,
            action varchar(100) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            details longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charsetCollate};";
    }

    public function down(string $prefix): string
    {
        $table = $prefix . 'logs';
        return "DROP TABLE IF EXISTS {$table};";
    }
}
