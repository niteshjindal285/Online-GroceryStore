<?php
require_once __DIR__ . '/../includes/database.php';
$columns = db_fetch_all("SHOW COLUMNS FROM suppliers");
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
