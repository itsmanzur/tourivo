<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateInquiriesTable
 *
 * Migration for creating the inquiries table: wp_tourivo_inquiries.
 *
 * @package Tourivo\Database\Migrations
 */
class CreateInquiriesTable implements MigrationInterface
{
    public function getName(): string
    {
        return 'create_tourivo_inquiries_table';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'inquiries';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            item_type varchar(30) NOT NULL DEFAULT 'tour',
            customer_name varchar(191) NOT NULL DEFAULT '',
            customer_email varchar(191) NOT NULL DEFAULT '',
            customer_phone varchar(64) NOT NULL DEFAULT '',
            travel_date date DEFAULT NULL,
            guests int(11) NOT NULL DEFAULT 1,
            message text,
            status varchar(30) NOT NULL DEFAULT 'new',
            consent_at datetime DEFAULT NULL,
            consent_version varchar(64) DEFAULT NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY customer_email (customer_email),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charsetCollate};";
    }

    public function down(string $prefix): string
    {
        $table = $prefix . 'inquiries';
        return "DROP TABLE IF EXISTS {$table};";
    }
}
