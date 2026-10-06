<?php

declare(strict_types=1);

namespace Tourivo\Database;

use Tourivo\Database\Migrations\CreateBookingsTable;
use Tourivo\Database\Migrations\CreateBookingItemsTable;
use Tourivo\Database\Migrations\CreateInquiriesTable;
use Tourivo\Database\Migrations\CreateInventoriesTable;
use Tourivo\Database\Migrations\CreateLogsTable;
use Tourivo\Database\Migrations\MigrationInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schema
 *
 * Runs database migrations and manages Tourivo custom tables.
 *
 * @package Tourivo\Database
 */
class Schema
{
    /**
     * Database version option key.
     */
    public const DB_VERSION_OPTION = 'tourivo_db_version';

    /**
     * Current schema version.
     */
    public const CURRENT_DB_VERSION = '1.5.0';

    /**
     * Get the registered migrations list.
     *
     * @return array<class-string<MigrationInterface>>
     */
    public static function getMigrations(): array
    {
        return [
            CreateBookingsTable::class,
            CreateBookingItemsTable::class,
            CreateInquiriesTable::class,
            CreateInventoriesTable::class,
            CreateLogsTable::class,
        ];
    }

    /**
     * Migration lock transient key.
     */
    public const MIGRATION_LOCK_TRANSIENT = 'tourivo_migrating_lock';

    /**
     * Run all migrations.
     *
     * @return void
     */
    public static function migrate(): void
    {
        if (get_transient(self::MIGRATION_LOCK_TRANSIENT)) {
            return;
        }

        set_transient(self::MIGRATION_LOCK_TRANSIENT, 1, 60);

        try {
            global $wpdb;

            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            $prefix = $wpdb->prefix . 'tourivo_';
            $charsetCollate = $wpdb->get_charset_collate();
            $installedVersion = (string) get_option(self::DB_VERSION_OPTION, '0.0.0');

            foreach (self::getMigrations() as $migrationClass) {
                /** @var MigrationInterface $migration */
                $migration = new $migrationClass();
                $sql = $migration->up($prefix, $charsetCollate);

                if (!empty($sql)) {
                    dbDelta($sql);
                }
            }

            // 1.4.0 introduced email verification: NULL now means "awaiting verification", so every
            // pre-existing booking must be backfilled as already verified or it would be auto-cancelled.
            if ($installedVersion !== '0.0.0' && version_compare($installedVersion, '1.4.0', '<')) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("UPDATE {$prefix}bookings SET email_verified_at = created_at WHERE email_verified_at IS NULL");
            }

            update_option(self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION);
        } finally {
            delete_transient(self::MIGRATION_LOCK_TRANSIENT);
        }
    }

    /**
     * Drop all custom tables on full uninstallation.
     *
     * @return void
     */
    public static function dropTables(): void
    {
        global $wpdb;

        $prefix = $wpdb->prefix . 'tourivo_';

        foreach (array_reverse(self::getMigrations()) as $migrationClass) {
            /** @var MigrationInterface $migration */
            $migration = new $migrationClass();
            $sql = $migration->down($prefix);

            if (!empty($sql)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($sql);
            }
        }

        delete_option(self::DB_VERSION_OPTION);
    }
}
