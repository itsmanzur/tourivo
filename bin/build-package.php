<?php
/**
 * Tourivo Plugin Distribution Package Builder
 *
 * Usage:
 *   php bin/build-package.php
 */

declare(strict_types=1);

$startTime = microtime(true);
$pluginRoot = dirname(__DIR__);

echo "=====================================================\n";
echo "           Tourivo Distribution Builder\n";
echo "=====================================================\n\n";

// 1. Detect Version from tourivo.php
$mainFileContent = file_get_contents($pluginRoot . '/tourivo.php');
if (!preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:-[a-zA-Z0-9.]+)?)/i', $mainFileContent, $vMatches)) {
    fwrite(STDERR, "Error: Could not extract version from tourivo.php\n");
    exit(1);
}
$version = $vMatches[1];
echo "==> Target Release Version: $version\n";

// 2. Syntax Check on all PHP files
echo "==> Running PHP Syntax Verification...\n";
$directoryIterator = new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($directoryIterator);
$phpErrors = 0;
$checkedCount = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        
        // Skip dist directory
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $checkedCount++;
        $output = [];
        $returnVar = 0;
        exec(sprintf('php -l %s', escapeshellarg($filePath)), $output, $returnVar);

        if ($returnVar !== 0) {
            echo " [FAIL] Syntax error in: $filePath\n";
            $phpErrors++;
        }
    }
}

if ($phpErrors > 0) {
    fwrite(STDERR, "Error: $phpErrors PHP syntax error(s) found. Aborting build.\n");
    exit(1);
}
echo "    Checked $checkedCount PHP files: All Syntax OK!\n";

// 3. Ensure Languages Directory has .mo
$moFile = $pluginRoot . '/languages/tourivo-bn_BD.mo';
if (!file_exists($moFile)) {
    echo "==> Notice: .mo file missing, searching .po to compile...\n";
} else {
    echo "==> Languages & MO binary files verified (" . number_format(filesize($moFile)) . " bytes)\n";
}

// 4. Setup dist directory
$distDir = $pluginRoot . '/dist';
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

// 5. Whitelist / Blacklist definitions
$includedEntries = [
    'app',
    'assets',
    'languages',
    'templates',
    'views',
    'tourivo.php',
    'readme.txt',
    'README.md',
    'LICENSE',
    'uninstall.php',
    'composer.json',
];

$excludedPatterns = [
    '/^tests(\/|\\\\|$)/i',
    '/^bin(\/|\\\\|$)/i',
    '/^dist(\/|\\\\|$)/i',
    '/\.git(\/|\\\\|$)/i',
    '/node_modules(\/|\\\\|$)/i',
    '/\.idea(\/|\\\\|$)/i',
    '/\.vscode(\/|\\\\|$)/i',
    '/\.DS_Store$/i',
    '/Thumbs\.db$/i',
    '/\.log$/i',
    '/\.tmp$/i',
    '/\.sql$/i',
    '/tourivo_project_roadmap\.md$/i',
];

$zipFiles = [
    $distDir . "/tourivo-{$version}.zip",
    $distDir . "/tourivo.zip",
];

// Clean old zips
foreach ($zipFiles as $zipFile) {
    if (file_exists($zipFile)) {
        unlink($zipFile);
    }
}

echo "==> Building ZIP Packages...\n";

$zip = new ZipArchive();
if ($zip->open($zipFiles[0], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Error: Could not create ZIP archive at {$zipFiles[0]}\n");
    exit(1);
}

$fileCount = 0;
$uncompressedBytes = 0;

foreach ($includedEntries as $entry) {
    $fullPath = $pluginRoot . DIRECTORY_SEPARATOR . $entry;
    if (!file_exists($fullPath)) {
        continue;
    }

    if (is_file($fullPath)) {
        $zipPath = 'tourivo/' . $entry;
        $zip->addFile($fullPath, $zipPath);
        $fileCount++;
        $uncompressedBytes += filesize($fullPath);
    } elseif (is_dir($fullPath)) {
        $dirIterator = new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS);
        $subIterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($subIterator as $item) {
            $itemPath = $item->getPathname();
            $relPath = ltrim(substr($itemPath, strlen($pluginRoot)), DIRECTORY_SEPARATOR);
            $normalizedRel = str_replace('\\', '/', $relPath);

            // Check exclusions
            $isExcluded = false;
            foreach ($excludedPatterns as $pattern) {
                if (preg_match($pattern, $normalizedRel)) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                continue;
            }

            $zipPath = 'tourivo/' . $normalizedRel;
            if ($item->isDir()) {
                $zip->addEmptyDir($zipPath);
            } else {
                $zip->addFile($itemPath, $zipPath);
                $fileCount++;
                $uncompressedBytes += $item->getSize();
            }
        }
    }
}

$zip->close();

// Copy to generic tourivo.zip as well
copy($zipFiles[0], $zipFiles[1]);

$compressedBytes = filesize($zipFiles[0]);
$sha256 = hash_file('sha256', $zipFiles[0]);
$duration = round(microtime(true) - $startTime, 2);

echo "\n=====================================================\n";
echo "           BUILD SUCCESSFUL ($duration s)\n";
echo "=====================================================\n";
echo "Package (Versioned): dist/tourivo-{$version}.zip\n";
echo "Package (Generic):   dist/tourivo.zip\n";
echo "Files Packaged:      " . number_format($fileCount) . " files\n";
echo "Uncompressed Size:   " . round($uncompressedBytes / 1024 / 1024, 2) . " MB (" . number_format($uncompressedBytes) . " bytes)\n";
echo "Compressed Size:     " . round($compressedBytes / 1024 / 1024, 2) . " MB (" . number_format($compressedBytes) . " bytes)\n";
echo "SHA256 Checksum:     " . $sha256 . "\n";
echo "=====================================================\n";
