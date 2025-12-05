<?php
declare(strict_types=1);

/**
 * Ensure every .log file in the repository is emptied before tests run.
 * Skips heavyweight vendor directories but will touch any existing .log file elsewhere.
 */
function clearProjectLogs(?string $rootDir = null): array
{
    $resolvedRoot = $rootDir !== null ? realpath($rootDir) : realpath(__DIR__ . '/..');
    if ($resolvedRoot === false) {
        throw new RuntimeException('Unable to resolve project root for log clearing.');
    }

    $skipDirectories = ['.git', 'node_modules', 'vendor'];
    $cleared = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($skipDirectories): bool {
                if ($file->isDir()) {
                    return !in_array($file->getFilename(), $skipDirectories, true);
                }
                return true;
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'log') {
            continue;
        }

        $path = $file->getPathname();
        if (file_put_contents($path, '') === false) {
            throw new RuntimeException("Failed to clear log file: {$path}");
        }
        $cleared[] = $path;
    }

    return $cleared;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        $cleared = clearProjectLogs();
        $count = count($cleared);
        echo "Cleared {$count} log file" . ($count === 1 ? '' : 's') . ".\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Error clearing log files: {$e->getMessage()}\n");
        exit(1);
    }
}
