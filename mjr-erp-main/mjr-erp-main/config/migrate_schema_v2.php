<?php
require_once __DIR__ . '/../includes/database.php';

echo "Starting migration: Adding is_active to customer_discounts...\n";

try {
    // Check if column already exists to avoid errors
    $columns = db_fetch_all("DESCRIBE customer_discounts");
    $exists = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'is_active') {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        db_execute("ALTER TABLE customer_discounts ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER discount_percent");
        echo "SUCCESS: Column 'is_active' added to 'customer_discounts'.\n";
    } else {
        echo "INFO: Column 'is_active' already exists in 'customer_discounts'.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration complete.\n";
?>
