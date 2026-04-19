<?php
/**
 * Simulated Web-Cron Trigger
 * This file is silently pinged by users' web browsers in the background.
 * It checks if the daily 6:00 AM tasks have been run yet today.
 */

// Allow the script to keep running in the background even if the browser drops the connection
ignore_user_abort(true);
set_time_limit(0);

// Load the default timezones and configs
require_once __DIR__ . '/../config/config.php';

$targetTime = "6.00"; // Set your desired time here (e.g. "06:00" or "14:30")

$lockFile = __DIR__ . '/../config/last_reorder_run.txt';
// We lock based on both the Date AND the Target Time. 
// If you test this by changing $targetTime to the current time, it will send immediately.
$lockKey = date('Y-m-d') . '_time_' . $targetTime;
$currentTime = date('H:i');

// If the current time has reached or passed our target time
if ($currentTime >= $targetTime) {
    $lastRun = file_exists($lockFile) ? file_get_contents($lockFile) : '';

    // If it hasn't run for this specific target hour today yet
    if ($lastRun !== $lockKey) {
        // 1. Immediately lock it
        file_put_contents($lockFile, $lockKey);
        
        // 2. Safely execute the main auto reorder logic in the background
        require_once __DIR__ . '/../cron_auto_reorder.php';
    }
}
?>
