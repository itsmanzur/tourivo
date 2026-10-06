<?php
/**
 * Minimal, real-MySQL stand-in for WordPress' `wpdb`.
 *
 * Implements just the surface InventoryRepository needs (prepare/query/get_row/get_results/get_var/get_col),
 * with the same semantics that matter for concurrency: DML errors (deadlock, duplicate key) make query()
 * return false instead of throwing, exactly like wpdb.
 *
 * Declared in the global namespace as `wpdb` because the repository type-hints `?wpdb`.
 */

declare(strict_types=1);

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $options = 'wp_options';
        public int $insert_id = 0;
        public int $rows_affected = 0;
        public string $last_error = '';
        /** @var array<int, string> Every DB error seen, e.g. "1213: Deadlock found...". */
        public array $errors = [];

        private \mysqli $dbh;

        public function __construct(string $host, int $port, string $user, string $pass, string $dbName, string $prefix = 'wp_')
        {
            mysqli_report(MYSQLI_REPORT_OFF);
            $this->dbh = new \mysqli($host, $user, $pass, $dbName, $port);
            if ($this->dbh->connect_errno) {
                throw new \RuntimeException('MySQL connect failed: ' . $this->dbh->connect_error);
            }
            $this->dbh->set_charset('utf8mb4');
            $this->prefix = $prefix;
        }

        public function prepare(string $query, mixed ...$args): string
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }

            $i = 0;
            return (string) preg_replace_callback('/%[dsfF]/', function (array $m) use (&$i, $args): string {
                $arg = $args[$i++] ?? null;
                return match ($m[0]) {
                    '%d'       => (string) (int) $arg,
                    '%f', '%F' => (string) (float) $arg,
                    default    => "'" . $this->dbh->real_escape_string((string) $arg) . "'",
                };
            }, $query);
        }

        public function query(string $query): int|bool
        {
            $result = $this->dbh->query($query);

            if ($result === false) {
                $this->last_error = $this->dbh->errno . ': ' . $this->dbh->error;
                $this->errors[] = $this->last_error;
                $this->rows_affected = 0;
                return false;
            }

            $this->insert_id     = (int) $this->dbh->insert_id;
            $this->rows_affected = (int) $this->dbh->affected_rows;

            if ($result instanceof \mysqli_result) {
                $result->free();
                return 0;
            }

            return $this->rows_affected;
        }

        public function get_row(?string $query = null): ?object
        {
            $result = $query === null ? false : $this->dbh->query($query);
            if (!$result instanceof \mysqli_result) {
                $this->recordError();
                return null;
            }
            $row = $result->fetch_object();
            $result->free();

            return $row ?: null;
        }

        /** @return array<int, object> */
        public function get_results(?string $query = null): array
        {
            $result = $query === null ? false : $this->dbh->query($query);
            if (!$result instanceof \mysqli_result) {
                $this->recordError();
                return [];
            }
            $rows = [];
            while ($row = $result->fetch_object()) {
                $rows[] = $row;
            }
            $result->free();

            return $rows;
        }

        public function get_var(?string $query = null): mixed
        {
            $result = $query === null ? false : $this->dbh->query($query);
            if (!$result instanceof \mysqli_result) {
                $this->recordError();
                return null;
            }
            $row = $result->fetch_row();
            $result->free();

            return $row[0] ?? null;
        }

        /** @return array<int, mixed> */
        public function get_col(?string $query = null): array
        {
            return array_map(static fn (object $r) => array_values((array) $r)[0], $this->get_results($query));
        }

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }

        private function recordError(): void
        {
            if ($this->dbh->errno) {
                $this->last_error = $this->dbh->errno . ': ' . $this->dbh->error;
                $this->errors[] = $this->last_error;
            }
        }
    }
}
