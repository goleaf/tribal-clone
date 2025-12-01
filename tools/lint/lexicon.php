<?php
declare(strict_types=1);

/**
 * Lexicon lint: scans selected directories for banned legacy terms defined in docs/ip-glossary.md.
 *
 * Usage: php tools/lint/lexicon.php
 * Exit code 0 on success, 1 if any violations are found.
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$glossaryPath = $root . '/docs/ip-glossary.md';
if (!file_exists($glossaryPath)) {
    fwrite(STDERR, "Glossary not found at {$glossaryPath}\n");
    exit(1);
}

$bannedTerms = [];
$glossaryLines = file($glossaryPath, FILE_IGNORE_NEW_LINES);
foreach ($glossaryLines as $line) {
    // Skip header/separator lines
    if (!str_contains($line, '|') || str_starts_with(trim($line), '#') || str_starts_with(trim($line), '| Legacy')) {
        continue;
    }
    $parts = array_map('trim', explode('|', $line));
    if (count($parts) < 3) {
        continue;
    }
    $term = $parts[1] ?? '';
    $legacy = $parts[0] ?? '';
    if ($legacy === 'Legacy Term' || $legacy === '' || $legacy === '---') {
        continue;
    }
    $bannedTerms[] = $legacy;
}
$bannedTerms = array_values(array_filter($bannedTerms));

if (empty($bannedTerms)) {
    fwrite(STDERR, "No banned terms found in glossary.\n");
    exit(1);
}

$targets = [
    $root . '/lang',
    $root . '/messages',
    $root . '/templates'
];
$allowedExtensions = ['php', 'js', 'ts', 'json', 'twig', 'html'];
$ignoreFiles = [
    realpath($glossaryPath)
];

$violations = [];

foreach ($targets as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $allowedExtensions, true)) {
            continue;
        }
        $realPath = $file->getRealPath();
        if ($realPath === false || in_array($realPath, $ignoreFiles, true)) {
            continue;
        }
        $lines = file($realPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $ln => $content) {
            foreach ($bannedTerms as $term) {
                $pattern = '~\b' . preg_quote($term, '~') . '\b~i';
                if (preg_match($pattern, $content)) {
                    $violations[] = [
                        'file' => str_replace($root . '/', '', $realPath),
                        'line' => $ln + 1,
                        'term' => $term,
                        'snippet' => trim($content)
                    ];
                }
            }
        }
    }
}

if (!empty($violations)) {
    fwrite(STDERR, "Lexicon violations found:\n");
    foreach ($violations as $v) {
        fwrite(
            STDERR,
            sprintf(
                "- %s:%d contains '%s': %s\n",
                $v['file'],
                $v['line'],
                $v['term'],
                $v['snippet']
            )
        );
    }
    exit(1);
}

fwrite(STDOUT, "Lexicon check passed. No banned terms found.\n");
exit(0);
