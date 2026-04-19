<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS payroll_run_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    employee_id INT NOT NULL,
    normal_hours DECIMAL(10,2) DEFAULT 0,
    leave_hours DECIMAL(10,2) DEFAULT 0,
    extra_hours DECIMAL(10,2) DEFAULT 0,
    hourly_rate DECIMAL(10,2) DEFAULT 0,
    normal_wages DECIMAL(10,2) DEFAULT 0,
    leave_pay DECIMAL(10,2) DEFAULT 0,
    total_wages DECIMAL(10,2) DEFAULT 0,
    paye DECIMAL(10,2) DEFAULT 0,
    nrbf_com DECIMAL(10,2) DEFAULT 0,
    nrbf_ind DECIMAL(10,2) DEFAULT 0,
    ot_pay DECIMAL(10,2) DEFAULT 0,
    bonus DECIMAL(10,2) DEFAULT 0,
    net_wages DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (run_id),
    INDEX (employee_id),
    FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $db->exec($sql);
    echo "Table 'payroll_run_items' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
