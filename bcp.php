<?php
$sourceDir = __DIR__;
$parentDir = dirname($sourceDir);
$sourceBaseName = basename($sourceDir); // Usually 'www'
$prefix = $sourceBaseName . '_bcp_';
$date = date('Ymd');
$destination = $parentDir . '/' . $prefix . $date;
$sevenDaysAgo = time() - 7 * 86400;

if (!file_exists($destination)) {
    exec(sprintf("cp -r %s %s", escapeshellarg($sourceDir), escapeshellarg($destination)));
}

$backups = glob($parentDir . '/' . $prefix . '????????');
$validBackups = array_filter($backups ?: [], 'is_dir');

if (count($validBackups) > 1) {
    $backupTimes = [];
    foreach ($validBackups as $b) {
        $backupTimes[$b] = filemtime($b);
    }
    arsort($backupTimes);
    $newestPath = key($backupTimes);

    foreach ($backupTimes as $path => $time) {
        if ($path !== $newestPath && $time < $sevenDaysAgo) {
            exec(sprintf("rm -rf %s", escapeshellarg($path)));
        }
    }
}
?>