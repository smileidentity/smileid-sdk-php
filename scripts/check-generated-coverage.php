<?php

/**
 * Fail unless every executable line under a directory is covered.
 *
 * Usage: php scripts/check-generated-coverage.php <cobertura.xml> [dir] [min-percent]
 *
 * dir defaults to src/Generated and min-percent to 100. Lines are read from
 * the Cobertura report PHPUnit writes with --coverage-cobertura. Prints each
 * uncovered line and exits 1 if coverage is below the minimum, or if the
 * report has no lines for the directory at all.
 */

declare(strict_types=1);

if ($argc < 2 || $argc > 4) {
    fwrite(STDERR, "usage: php scripts/check-generated-coverage.php <cobertura.xml> [dir] [min-percent]\n");
    exit(2);
}

$report = $argv[1];
$root = getcwd();
$dirArg = $argv[2] ?? 'src/Generated';
$dir = realpath($dirArg);
$min = (float) ($argv[3] ?? '100');

if ($dir === false || !is_dir($dir)) {
    fwrite(STDERR, "check-generated-coverage: directory '{$dirArg}' not found\n");
    exit(2);
}

$doc = new DOMDocument();
if (!is_file($report) || !@$doc->load($report)) {
    fwrite(STDERR, "check-generated-coverage: cannot read Cobertura report '{$report}'\n");
    exit(2);
}

$xpath = new DOMXPath($doc);
$sources = [];
foreach ($xpath->query('/coverage/sources/source') as $source) {
    $sources[] = rtrim(trim($source->textContent), '/');
}
if ($sources === []) {
    $sources[] = $root;
}

/** @var array<string, array<int, bool>> $lines file => line number => covered */
$lines = [];
foreach ($xpath->query('//class') as $class) {
    $filename = $class->getAttribute('filename');
    $path = null;
    foreach ($sources as $source) {
        $candidate = str_starts_with($filename, '/') ? $filename : $source . '/' . $filename;
        if (is_file($candidate)) {
            $path = realpath($candidate);
            break;
        }
    }
    if ($path === null || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
        continue;
    }
    foreach ($xpath->query('lines/line', $class) as $line) {
        $number = (int) $line->getAttribute('number');
        $covered = (int) $line->getAttribute('hits') > 0;
        $lines[$path][$number] = ($lines[$path][$number] ?? false) || $covered;
    }
}

$total = 0;
$hit = 0;
$missing = [];
ksort($lines);
foreach ($lines as $path => $numbers) {
    ksort($numbers);
    foreach ($numbers as $number => $covered) {
        $total++;
        if ($covered) {
            $hit++;
        } else {
            $missing[] = substr($path, strlen($root) + 1) . ':' . $number;
        }
    }
}

$relDir = substr($dir, strlen($root) + 1);
if ($total === 0) {
    fwrite(STDERR, "check-generated-coverage: the report has no executable lines under {$relDir}\n");
    exit(1);
}

$percent = 100 * $hit / $total;
printf("%s line coverage: %d/%d (%.2f%%), minimum %.2f%%\n", $relDir, $hit, $total, $percent, $min);

if ($percent < $min) {
    echo "Uncovered lines:\n";
    foreach ($missing as $entry) {
        echo "  {$entry}\n";
    }
    exit(1);
}
