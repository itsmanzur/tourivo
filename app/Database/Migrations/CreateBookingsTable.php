<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CreateBookingsTable
 *
 * Migration for creating the central bookings table: wp_tourivo_bookings.
 *
 * @package Tourivo\Database\Migrations
 */
class CreateBookingsTable implements MigrationInterface
{
    public function getName(): string
    {
        return 'create_tourivo_bookings_table';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'bookings';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_code varchar(32) NOT NULL,
            customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            customer_name varchar(191) NOT NULL DEFAULT '',
            customer_email varchar(191) NOT NULL DEFAULT '',
            customer_phone varchar(64) NOT NULL DEFAULT '',
            billing_address text,
            total_amount decimal(12,2) NOT NULL DEFAULT '0.00',
            tax_amount decimal(12,2) NOT NULL DEFAULT '0.00',
            discount_amount decimal(12,2) NOT NULL DEFAULT '0.00',
            paid_amount decimal(12,2) NOT NULL DEFAULT '0.00',
            due_amount decimal(12,2) NOT NULL DEFAULT '0.00',
            currency varchar(10) NOT NULL DEFAULT 'USD',
            payment_method varchar(50) NOT NULL DEFAULT 'offline',
            payment_status varchar(30) NOT NULL DEFAULT 'pending',
            booking_status varchar(30) NOT NULL DEFAULT 'pending',
            check_in_status varchar(30) NOT NULL DEFAULT 'pending',
            transaction_id varchar(100) DEFAULT NULL,
            customer_notes text,
            admin_notes text,
            cancel_requested_at datetime DEFAULT NULL,
            email_verified_at datetime DEFAULT NULL,
            access_key varchar(64) DEFAULT NULL,
            consent_at datetime DEFAULT NULL,
            consent_version varchar(64) DEFAULT NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_code (booking_code),
            KEY customer_id (customer_id),
            KEY customer_email (customer_email),
            KEY booking_status (booking_status),
            KEY payment_status (payment_status),
            KEY transaction_id (transaction_id),
            KEY check_in_status (check_in_status),
            KEY created_at (created_at),
            KEY expiry_scan (booking_status, created_at)
        ) {$charsetCollate};";
    }

    public function down(string $prefix): string
    {
        $table = $prefix . 'bookings';
        return "DROP TABLE IF EXISTS {$table};";
    }
}
