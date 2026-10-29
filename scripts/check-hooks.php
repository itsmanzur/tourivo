<?php
/**
 * Tourivo ↔ Tourivo Pro hook compatibility check.
 *
 * Scans both repositories with the PHP tokenizer and lists every tourivo/... hook that Pro listens to
 * (add_action, add_filter, has_*, remove_*) but nobody fires (do_action*, apply_filters*).
 *
 * Usage:
 *   php scripts/check-hooks.php [--core=<path>] [--pro=<path>] [--verbose]
 *
 *   --core     Tourivo (free) checkout. Default: the folder above this script.
 *   --pro      Tourivo Pro checkout. Default: ../tourivo-pro next to the core folder.
 *   --verbose  Also list every matched hook with the files that fire and listen to it.
 *
 * Exit code: 0 = no mismatch, 1 = mismatches found, 2 = a path is missing.
 * Hook names built at runtime ('tourivo/seo/' . $x) are matched by their literal prefix and listed separately.
 */

declare(strict_types=1);

$options = getopt('', ['core::', 'pro::', 'verbose']);
$core    = rtrim((string) ($options['core'] ?? dirname(__DIR__)), '/\\');
$pro     = rtrim((string) ($options['pro'] ?? dirname($core) . '/tourivo-pro'), '/\\');
$verbose = isset($options['verbose']);

foreach (['core' => $core, 'pro' => $pro] as $label => $path) {
    if (!is_dir($path)) {
        fwrite(STDERR, "Missing {$label} folder: {$path}\n");
        exit(2);
    }
}

const TRV_FIRE   = ['do_action', 'do_action_ref_array', 'do_action_deprecated', 'apply_filters', 'apply_filters_ref_array', 'apply_filters_deprecated'];
const TRV_LISTEN = ['add_action', 'add_filter', 'has_action', 'has_filter', 'remove_action', 'remove_filter', 'remove_all_actions', 'remove_all_filters'];
const TRV_SKIP   = ['vendor', 'node_modules', 'tests', 'dist', 'bin', 'scripts', '.git'];

/**
 * PHP files of a checkout, without dependencies, tests and build output.
 *
 * @return string[]
 */
function trv_php_files(string $root): array
{
    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $f): bool => !($f->isDir() && in_array($f->getFilename(), TRV_SKIP, true))
        )
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

/**
 * Every tourivo/... hook name passed as the first argument of the given functions.
 *
 * @param string[] $functions
 * @return array<int, array{name: string, dynamic: bool, function: string, file: string, line: int}>
 */
function trv_hook_calls(string $file, array $functions, string $root): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $found  = [];
    $count  = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || !in_array(strtolower($t[1]), $functions, true)) {
            continue;
        }
        // Skip method calls ($this->add_action) and declarations (function add_action).
        $prev = $i - 1;
        while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
            $prev--;
        }
        if ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            continue;
        }

        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (($tokens[$j] ?? null) !== '(') {
            continue;
        }
        $j++;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT], true)) {
            $j++;
        }
        $arg = $tokens[$j] ?? null;
        if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $name = substr($arg[1], 1, -1);
        if (!str_starts_with($name, 'tourivo/')) {
            continue;
        }

        $k = $j + 1;
        while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
            $k++;
        }

        $found[] = [
            'name'     => $name,
            'dynamic'  => ($tokens[$k] ?? null) === '.',
            'function' => strtolower($t[1]),
            'file'     => ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/'),
            'line'     => (int) $t[2],
        ];
    }

    return $found;
}

/**
 * @return array{fired: array<int, array<string, mixed>>, listened: array<int, array<string, mixed>>}
 */
function trv_scan(string $root, string $label): array
{
    $fired = $listened = [];
    foreach (trv_php_files($root) as $file) {
        foreach (trv_hook_calls($file, TRV_FIRE, $root) as $call) {
            $fired[] = $call + ['repo' => $label];
        }
        foreach (trv_hook_calls($file, TRV_LISTEN, $root) as $call) {
            $listened[] = $call + ['repo' => $label];
        }
    }
    return ['fired' => $fired, 'listened' => $listened];
}

$coreScan = trv_scan($core, 'core');
$proScan  = trv_scan($pro, 'pro');

// Pro may listen to hooks fired by core or by Pro itself.
$fired = array_merge($coreScan['fired'], $proScan['fired']);
$firedExact    = [];
$firedPrefixes = [];
foreach ($fired as $f) {
    if ($f['dynamic']) {
        $firedPrefixes[$f['name']][] = $f;
    } else {
        $firedExact[$f['name']][] = $f;
    }
}

$matched = $missing = $dynamic = [];
foreach ($proScan['listened'] as $l) {
    $name  = $l['name'];
    $where = "{$l['file']}:{$l['line']} ({$l['function']})";

    if ($l['dynamic']) {
        $dynamic[] = "{$name}… {$where}";
        continue;
    }
    if (isset($firedExact[$name])) {
        $matched[$name][] = $where;
        continue;
    }
    foreach (array_keys($firedPrefixes) as $prefix) {
        if (str_starts_with($name, $prefix)) {
            $matched[$name][] = $where . ' [dynamic: ' . $prefix . '…]';
            continue 2;
        }
    }
    $missing[] = "{$name}  {$where}";
}

echo "Tourivo hook compatibility\n";
echo "  core: {$core}\n  pro:  {$pro}\n";
echo sprintf(
    "  core fires %d tourivo/ hook names, pro fires %d; pro listens to %d.\n\n",
    count(array_unique(array_column($coreScan['fired'], 'name'))),
    count(array_unique(array_column($proScan['fired'], 'name'))),
    count(array_unique(array_column($proScan['listened'], 'name')))
);

if ($verbose) {
    ksort($matched);
    echo "Matched:\n";
    foreach ($matched as $name => $wheres) {
        $by = implode(', ', array_unique(array_map(static fn ($f) => $f['repo'] . ':' . $f['file'] . ':' . $f['line'], $firedExact[$name] ?? [])));
        echo "  ✓ {$name}\n      fired by:  " . ($by ?: 'dynamic name') . "\n      listened:  " . implode(', ', $wheres) . "\n";
    }
    echo "\n";
}

if ($dynamic) {
    echo "Listeners with a runtime name (check by hand):\n";
    foreach ($dynamic as $line) {
        echo "  ? {$line}\n";
    }
    echo "\n";
}

if ($missing) {
    echo "MISMATCH, nobody fires these hooks:\n";
    foreach ($missing as $line) {
        echo "  ✗ {$line}\n";
    }
    echo "\n" . count($missing) . " mismatch(es).\n";
    exit(1);
}

echo "✓ Every tourivo/ hook Pro listens to is fired (" . count($matched) . " names).\n";
exit(0);
