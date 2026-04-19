<?php
/**
 * Export Functions - NO CSV
 * 
 * Functions for exporting to Excel, PDF, Word, and Email only
 * Updated to match your database structure
 */

require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Export inventory report to Excel
 * 
 * @param array $data Inventory data
 * @param array $options Export options
 * @return string Path to generated file
 */
function export_inventory_to_excel($data, $options = []) {
    try {
        $defaults = [
            'title' => 'Inventory Report',
            'filename' => 'inventory_report_' . date('Y-m-d_His') . '.xlsx',
            'company_name' => 'Company Name',
            'include_summary' => true
        ];
        $options = array_merge($defaults, $options);
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator($options['company_name'])
            ->setTitle($options['title'])
            ->setSubject('Inventory Report')
            ->setDescription('Inventory report generated on ' . date('Y-m-d H:i:s'));
        
        // Title
        $sheet->setCellValue('A1', $options['title']);
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Generation date
        $sheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', $options['company_name']);
        $sheet->mergeCells('A3:L3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Headers
        $headers = [
            'A5' => 'Item Code',
            'B5' => 'Item Name',
            'C5' => 'Description',
            'D5' => 'Category',
            'E5' => 'Quantity',
            'F5' => 'Unit',
            'G5' => 'Cost Price',
            'H5' => 'Selling Price',
            'I5' => 'Total Value',
            'J5' => 'Reorder Level',
            'K5' => 'Barcode',
            'L5' => 'Status'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];
        $sheet->getStyle('A5:L5')->applyFromArray($headerStyle);
        $sheet->getRowDimension('5')->setRowHeight(25);
        
        // Add data
        $row = 6;
        $totalValue = 0;
        $totalQuantity = 0;
        $lowStockCount = 0;
        $outOfStockCount = 0;
        
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['item_code'] ?? '');
            $sheet->setCellValue('B' . $row, $item['item_name'] ?? '');
            $sheet->setCellValue('C' . $row, $item['description'] ?? '');
            $sheet->setCellValue('D' . $row, $item['category'] ?? 'Uncategorized');
            $sheet->setCellValue('E' . $row, $item['quantity'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['unit'] ?? 'PCS');
            $sheet->setCellValue('G' . $row, $item['cost_price'] ?? 0);
            $sheet->setCellValue('H' . $row, $item['unit_price'] ?? 0);
            
            $itemTotal = $item['total_value'] ?? 0;
            $sheet->setCellValue('I' . $row, $itemTotal);
            $totalValue += $itemTotal;
            $totalQuantity += ($item['quantity'] ?? 0);
            
            $sheet->setCellValue('J' . $row, $item['reorder_level'] ?? 0);
            $sheet->setCellValue('K' . $row, $item['barcode'] ?? '');
            
            // Status determination
            $qty = $item['quantity'] ?? 0;
            $reorder = $item['reorder_level'] ?? 0;
            $status = 'In Stock';
            $bgColor = null;
            
            if ($qty == 0) {
                $status = 'Out of Stock';
                $bgColor = 'FF6B6B'; // Red
                $outOfStockCount++;
            } elseif ($qty <= $reorder) {
                $status = 'Low Stock';
                $bgColor = 'FFD966'; // Yellow
                $lowStockCount++;
            }
            
            $sheet->setCellValue('L' . $row, $status);
            
            // Highlight status
            if ($bgColor) {
                $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($bgColor);
            }
            
            $row++;
        }
        
        $lastDataRow = $row - 1;
        
        // Add summary
        if ($options['include_summary']) {
            $row += 2;
            
            // Summary header
            $sheet->setCellValue('A' . $row, 'REPORT SUMMARY');
            $sheet->mergeCells('A' . $row . ':L' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E7E6E6');
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Total Items:');
            $sheet->setCellValue('B' . $row, count($data));
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Total Quantity:');
            $sheet->setCellValue('B' . $row, number_format($totalQuantity));
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Total Inventory Value:');
            $sheet->setCellValue('B' . $row, $totalValue);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Low Stock Items:');
            $sheet->setCellValue('B' . $row, $lowStockCount);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Out of Stock Items:');
            $sheet->setCellValue('B' . $row, $outOfStockCount);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        }
        
        // Format currency columns
        $sheet->getStyle('G6:I' . $lastDataRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        
        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add borders to data
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ];
        $sheet->getStyle('A5:L' . $lastDataRow)->applyFromArray($dataStyle);
        
        // Save file
        $exportDir = __DIR__ . '/../public/exports/';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filepath = $exportDir . $options['filename'];
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
        
    } catch (Exception $e) {
        log_error("Excel export failed: " . $e->getMessage());
        throw new Exception("Failed to generate Excel report: " . $e->getMessage());
    }
}

/**
 * Export inventory report to PDF using Dompdf
 * 
 * @param array $data Inventory data
 * @param array $options Export options
 * @return string Path to generated file
 */
function export_inventory_to_pdf($data, $options = []) {
    try {
        if (!class_exists('\Dompdf\Dompdf')) {
            throw new Exception('Dompdf is not available. Run: composer require dompdf/dompdf');
        }
        
        $defaults = [
            'title' => 'Inventory Report',
            'filename' => 'inventory_report_' . date('Y-m-d_His') . '.pdf',
            'company_name' => 'Company Name',
            'orientation' => 'landscape'
        ];
        $options = array_merge($defaults, $options);
        
        $dompdfOptions = new \Dompdf\Options();
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('isRemoteEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($dompdfOptions);
        
        // Generate HTML
        $html = generate_pdf_html($data, $options);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $options['orientation']);
        $dompdf->render();
        
        // Save file
        $exportDir = __DIR__ . '/../public/exports/';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filepath = $exportDir . $options['filename'];
        file_put_contents($filepath, $dompdf->output());
        
        return $filepath;
        
    } catch (Exception $e) {
        log_error("PDF export failed: " . $e->getMessage());
        throw new Exception("Failed to generate PDF report: " . $e->getMessage());
    }
}

/**
 * Generate HTML for PDF
 */
function generate_pdf_html($data, $options) {
    $totalValue = 0;
    $totalQuantity = 0;
    $lowStockCount = 0;
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { color: #333; margin: 5px 0; font-size: 18pt; }
        .header p { color: #666; margin: 3px 0; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #4472C4; color: white; padding: 8px; text-align: left; font-weight: bold; border: 1px solid #333; font-size: 9pt; }
        td { padding: 6px; border: 1px solid #ddd; font-size: 9pt; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .low-stock { background-color: #FFD966 !important; }
        .out-of-stock { background-color: #FF6B6B !important; color: white; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin-top: 20px; padding: 15px; background-color: #f0f0f0; border: 1px solid #ccc; }
        .summary-row { margin: 5px 0; }
        .summary-label { font-weight: bold; display: inline-block; width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($options['title']) . '</h1>
        <p><strong>' . htmlspecialchars($options['company_name']) . '</strong></p>
        <p>Generated on: ' . date('F j, Y g:i A') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Code</th>
                <th style="width: 15%;">Item Name</th>
                <th style="width: 20%;">Description</th>
                <th style="width: 10%;">Category</th>
                <th style="width: 7%;" class="text-center">Qty</th>
                <th style="width: 5%;">Unit</th>
                <th style="width: 9%;" class="text-right">Cost</th>
                <th style="width: 9%;" class="text-right">Price</th>
                <th style="width: 10%;" class="text-right">Value</th>
                <th style="width: 7%;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($data as $item) {
        $itemTotal = $item['total_value'] ?? 0;
        $totalValue += $itemTotal;
        $totalQuantity += ($item['quantity'] ?? 0);
        
        $qty = $item['quantity'] ?? 0;
        $reorder = $item['reorder_level'] ?? 0;
        $status = 'In Stock';
        $rowClass = '';
        
        if ($qty == 0) {
            $status = 'Out';
            $rowClass = ' class="out-of-stock"';
        } elseif ($qty <= $reorder) {
            $status = 'Low';
            $rowClass = ' class="low-stock"';
            $lowStockCount++;
        }
        
        $html .= '<tr' . $rowClass . '>
            <td>' . htmlspecialchars($item['item_code'] ?? '') . '</td>
            <td>' . htmlspecialchars($item['item_name'] ?? '') . '</td>
            <td>' . htmlspecialchars(substr($item['description'] ?? '', 0, 50)) . '</td>
            <td>' . htmlspecialchars($item['category'] ?? 'N/A') . '</td>
            <td class="text-center">' . number_format($qty) . '</td>
            <td>' . htmlspecialchars($item['unit'] ?? '') . '</td>
            <td class="text-right">$' . number_format($item['cost_price'] ?? 0, 2) . '</td>
            <td class="text-right">$' . number_format($item['unit_price'] ?? 0, 2) . '</td>
            <td class="text-right">$' . number_format($itemTotal, 2) . '</td>
            <td class="text-center">' . $status . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Total Items:</span>
            <span>' . count($data) . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Quantity:</span>
            <span>' . number_format($totalQuantity) . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Inventory Value:</span>
            <span><strong>$' . number_format($totalValue, 2) . '</strong></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Low Stock Items:</span>
            <span>' . $lowStockCount . '</span>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Export inventory report to Word
 * 
 * @param array $data Inventory data
 * @param array $options Export options
 * @return string Path to generated file
 */
function export_inventory_to_word($data, $options = []) {
    try {
        $defaults = [
            'title' => 'Inventory Report',
            'filename' => 'inventory_report_' . date('Y-m-d_His') . '.docx',
            'company_name' => 'Company Name'
        ];
        $options = array_merge($defaults, $options);
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Add section with landscape orientation
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 600,
            'marginRight' => 600,
            'marginTop' => 600,
            'marginBottom' => 600
        ]);
        
        // Title
        $section->addText(
            $options['title'],
            ['size' => 18, 'bold' => true],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        $section->addText(
            $options['company_name'],
            ['size' => 12],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        $section->addText(
            'Generated on: ' . date('F j, Y g:i A'),
            ['size' => 10],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        
        $section->addTextBreak(1);
        
        // Create table
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 50,
            'width' => 100 * 50
        ];
        $phpWord->addTableStyle('InventoryTable', $tableStyle);
        
        $table = $section->addTable('InventoryTable');
        
        // Header row
        $table->addRow(400);
        $headerStyle = ['bold' => true, 'size' => 10, 'color' => 'FFFFFF'];
        $headerCellStyle = ['bgColor' => '4472C4', 'valign' => 'center'];
        
        $table->addCell(1200, $headerCellStyle)->addText('Item Code', $headerStyle);
        $table->addCell(2000, $headerCellStyle)->addText('Item Name', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Category', $headerStyle);
        $table->addCell(1000, $headerCellStyle)->addText('Quantity', $headerStyle);
        $table->addCell(800, $headerCellStyle)->addText('Unit', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Cost Price', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Selling Price', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Total Value', $headerStyle);
        $table->addCell(1000, $headerCellStyle)->addText('Status', $headerStyle);
        
        // Data rows
        $totalValue = 0;
        $totalQuantity = 0;
        
        foreach ($data as $item) {
            $table->addRow();
            
            $itemTotal = $item['total_value'] ?? 0;
            $totalValue += $itemTotal;
            $totalQuantity += ($item['quantity'] ?? 0);
            
            $cellStyle = [];
            $qty = $item['quantity'] ?? 0;
            $reorder = $item['reorder_level'] ?? 0;
            $status = 'In Stock';
            
            if ($qty == 0) {
                $cellStyle = ['bgColor' => 'FF6B6B'];
                $status = 'Out of Stock';
            } elseif ($qty <= $reorder) {
                $cellStyle = ['bgColor' => 'FFD966'];
                $status = 'Low Stock';
            }
            
            $table->addCell(1200, $cellStyle)->addText($item['item_code'] ?? '');
            $table->addCell(2000, $cellStyle)->addText($item['item_name'] ?? '');
            $table->addCell(1500, $cellStyle)->addText($item['category'] ?? '');
            $table->addCell(1000, $cellStyle)->addText((string)($item['quantity'] ?? 0));
            $table->addCell(800, $cellStyle)->addText($item['unit'] ?? '');
            $table->addCell(1200, $cellStyle)->addText('$' . number_format($item['cost_price'] ?? 0, 2));
            $table->addCell(1200, $cellStyle)->addText('$' . number_format($item['unit_price'] ?? 0, 2));
            $table->addCell(1500, $cellStyle)->addText('$' . number_format($itemTotal, 2));
            $table->addCell(1000, $cellStyle)->addText($status);
        }
        
        // Summary row
        $table->addRow();
        $summaryStyle = ['bold' => true];
        $summaryCellStyle = ['bgColor' => 'E7E6E6'];
        
        $table->addCell(8500, $summaryCellStyle)->addText('Total:', $summaryStyle);
        $table->addCell(1500, $summaryCellStyle)->addText('$' . number_format($totalValue, 2), $summaryStyle);
        $table->addCell(1000, $summaryCellStyle)->addText('');
        
        // Save file
        $exportDir = __DIR__ . '/../public/exports/';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filepath = $exportDir . $options['filename'];
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filepath);
        
        return $filepath;
        
    } catch (Exception $e) {
        log_error("Word export failed: " . $e->getMessage());
        throw new Exception("Failed to generate Word report: " . $e->getMessage());
    }
}

/**
 * Email inventory report
 * 
 * @param array $data Inventory data
 * @param string $recipient Email address
 * @param array $options Export options
 * @return bool Success status
 */
function email_inventory_report($data, $recipient, $options = []) {
    try {
        require_once(__DIR__ . '/PHPMailer-master/src/PHPMailer.php');
        require_once(__DIR__ . '/PHPMailer-master/src/SMTP.php');
        require_once(__DIR__ . '/PHPMailer-master/src/Exception.php');
        require_once(__DIR__ . '/email_config.php');
        
        $defaults = [
            'title' => 'Inventory Report',
            'format' => 'excel',
            'subject' => 'Inventory Report - ' . date('F j, Y'),
            'message' => 'Please find attached the inventory report.'
        ];
        $options = array_merge($defaults, $options);
        
        // Generate report file based on format
        $filepath = null;
        switch ($options['format']) {
            case 'pdf':
                $filepath = export_inventory_to_pdf($data, $options);
                break;
            case 'word':
                $filepath = export_inventory_to_word($data, $options);
                break;
            case 'excel':
            default:
                $filepath = export_inventory_to_excel($data, $options);
                break;
        }
        
        // Send email using PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipient);
        
        // Attach report
        $mail->addAttachment($filepath);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $options['subject'];
        $mail->Body = nl2br($options['message']);
        
        $mail->send();
        
        // Delete temporary file
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        return true;
        
    } catch (Exception $e) {
        log_error("Email export failed: " . $e->getMessage());
        throw new Exception("Failed to email report: " . $e->getMessage());
    }
}

/**
 * Download file and clean up
 * 
 * @param string $filepath Path to file
 * @param string $filename Download filename
 */
function download_file($filepath, $filename) {
    if (!file_exists($filepath)) {
        throw new Exception("File not found");
    }
    
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    $contentTypes = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
    
    // Clear all output buffers to avoid corrupt downloads/headers issues
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($filepath));
    header('Expires: 0');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output file
    readfile($filepath);
    flush();
    
    // Delete file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    exit();
}
?>
