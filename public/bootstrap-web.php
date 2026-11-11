<?php

declare(strict_types=1);

set_time_limit(120);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$endpointsDir = dirname(__DIR__);
$webDir = dirname($endpointsDir) . '/web';

if (!is_dir($webDir)) {
    echo "Web directory not found at: {$webDir}\n";
    echo "Trying to detect...\n";
    $parentDir = dirname($endpointsDir);
    $dirs = scandir($parentDir);
    echo "Contents of {$parentDir}:\n";
    foreach ($dirs as $d) {
        if ($d === '.' || $d === '..') continue;
        echo "  {$d}" . (is_dir("{$parentDir}/{$d}") ? '/' : '') . "\n";
    }
    exit(1);
}

echo "Web directory found: {$webDir}\n\n";

echo "=== Git pull in web directory ===\n";
$output = [];
$exitCode = 0;
exec("cd " . escapeshellarg($webDir) . " && git pull --ff-only 2>&1", $output, $exitCode);
echo implode("\n", $output) . "\n";
echo "Exit code: {$exitCode}\n\n";

if ($exitCode !== 0) {
    echo "Git pull failed.\n";
    exit($exitCode);
}

$htaccessSource = $webDir . '/deploy.htaccess';
$htaccessDest = $webDir . '/.htaccess';

if (!is_file($htaccessSource)) {
    echo "deploy.htaccess not found at: {$htaccessSource}\n";
    exit(1);
}

$copied = copy($htaccessSource, $htaccessDest);
echo "=== Installing .htaccess ===\n";
echo $copied
    ? "Copied deploy.htaccess -> .htaccess successfully.\n"
    : "FAILED to copy .htaccess!\n";

echo "\nDone. The web/redeploy.php should now be accessible.\n";
echo "You can delete this bootstrap script.\n";
