<?php

declare(strict_types=1);

namespace Tourivo\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface MigrationInterface
 *
 * Contract for database migration definitions.
 *
 * @package Tourivo\Database\Migrations
 */
interface MigrationInterface
{
    /**
     * Get the unique migration identifier.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the schema version for this migration.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get the SQL statement to execute with dbDelta() or wpdb.
     *
     * @param string $prefix Table prefix (e.g. wp_tourivo_)
     * @param string $charsetCollate WordPress charset and collate string
     * @return string
     */
    public function up(string $prefix, string $charsetCollate): string;

    /**
     * Get the SQL statement to drop or reverse the migration.
     *
     * @param string $prefix Table prefix
     * @return string
     */
    public function down(string $prefix): string;
}
