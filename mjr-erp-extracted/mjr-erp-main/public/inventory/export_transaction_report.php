<?php
/**
 * Export Inventory Transaction Report
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';
require_once __DIR__ . '/../../includes/export_functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_login();
ensure_inventory_transaction_reporting_schema();

$format = trim((string)get_param('format', 'csv'));
$allowedFormats = ['csv', 'excel', 'pdf', 'word'];
if (!in_array($format, $allowedFormats, true)) {
    set_flash('Invalid export format requested.', 'error');
    redirect('transactions.php');
}

$filters = [
    'date_from' => trim((string)get_param('date_from', '')),
    'date_to' => trim((string)get_param('date_to', '')),
    'item_id' => trim((string)get_param('item_id', '')),
    'location_id' => trim((string)get_param('location_id', '')),
    'transaction_type' => trim((string)get_param('transaction_type', '')),
    'customer_id' => trim((string)get_param('customer_id', '')),
    'supplier_id' => trim((string)get_param('supplier_id', '')),
    'search' => trim((string)get_param('search', ''))
];

try {
    $rows = inventory_fetch_transaction_report_rows($filters);
    if (empty($rows)) {
        set_flash('No transaction data available for export.', 'warning');
        redirect('transactions.php');
    }

    $summary = inventory_transaction_report_summary($rows);
    $timestamp = date('Y-m-d_His');
    $baseName = 'inventory_transaction_report_' . $timestamp;
    $exportDir = __DIR__ . '/../exports/';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    if ($format === 'csv') {
        $filename = $baseName . '.csv';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Date',
            'Item Code',
            'Item Name',
            'Transaction Type',
            'Quantity',
            'Unit',
            'Unit Cost',
            'Selling Price',
            'Customer',
            'Supplier',
            'Reference',
            'Location',
            'User',
            'Notes'
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['created_at'],
                $row['item_code'],
                $row['item_name'],
                $row['transaction_label'],
                $row['quantity_signed'],
                $row['unit_of_measure'],
                number_format((float)$row['unit_cost'], 4, '.', ''),
                number_format((float)$row['selling_price'], 4, '.', ''),
                $row['customer_name'] ?? '',
                $row['supplier_name'] ?? '',
                $row['reference_display'] ?? '',
                ($row['location_code'] ?? '') . ' - ' . ($row['location_name'] ?? ''),
                $row['created_by_name'] ?? '',
                $row['notes'] ?? ''
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Rows', $summary['total_rows']]);
        fputcsv($output, ['Total Inbound Qty', $summary['total_in_qty']]);
        fputcsv($output, ['Total Outbound Qty', $summary['total_out_qty']]);
        fputcsv($output, ['Inbound Cost Value', number_format((float)$summary['total_in_cost_value'], 2, '.', '')]);
        fputcsv($output, ['Outbound Sales Value', number_format((float)$summary['total_out_sales_value'], 2, '.', '')]);

        fclose($output);
        exit;
    }

    if ($format === 'excel') {
        $filename = $baseName . '.xlsx';
        $path = $exportDir . $filename;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        $sheet->setCellValue('A1', 'Inventory Transaction Report');
        $sheet->mergeCells('A1:N1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
        $sheet->mergeCells('A2:N2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'A4' => 'Date',
            'B4' => 'Item Code',
            'C4' => 'Item Name',
            'D4' => 'Type',
            'E4' => 'Qty',
            'F4' => 'Unit',
            'G4' => 'Unit Cost',
            'H4' => 'Selling Price',
            'I4' => 'Customer',
            'J4' => 'Supplier',
            'K4' => 'Reference',
            'L4' => 'Location',
            'M4' => 'User',
            'N4' => 'Notes'
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A4:N4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        $r = 5;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $r, $row['created_at']);
            $sheet->setCellValue('B' . $r, $row['item_code']);
            $sheet->setCellValue('C' . $r, $row['item_name']);
            $sheet->setCellValue('D' . $r, $row['transaction_label']);
            $sheet->setCellValue('E' . $r, intval($row['quantity_signed']));
            $sheet->setCellValue('F' . $r, $row['unit_of_measure']);
            $sheet->setCellValue('G' . $r, (float)$row['unit_cost']);
            $sheet->setCellValue('H' . $r, (float)$row['selling_price']);
            $sheet->setCellValue('I' . $r, $row['customer_name'] ?? '');
            $sheet->setCellValue('J' . $r, $row['supplier_name'] ?? '');
            $sheet->setCellValue('K' . $r, $row['reference_display'] ?? '');
            $sheet->setCellValue('L' . $r, trim(($row['location_code'] ?? '') . ' ' . ($row['location_name'] ?? '')));
            $sheet->setCellValue('M' . $r, $row['created_by_name'] ?? '');
            $sheet->setCellValue('N' . $r, $row['notes'] ?? '');
            $r++;
        }

        if ($r > 5) {
            $lastDataRow = $r - 1;
            $sheet->getStyle('G5:H' . $lastDataRow)->getNumberFormat()->setFormatCode('$#,##0.00');
            $sheet->getStyle('A5:N' . $lastDataRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'DDDDDD']
                    ]
                ]
            ]);
        }

        $summaryStart = $r + 1;
        $sheet->setCellValue('A' . $summaryStart, 'Summary');
        $sheet->getStyle('A' . $summaryStart)->getFont()->setBold(true);
        $sheet->setCellValue('A' . ($summaryStart + 1), 'Total Rows');
        $sheet->setCellValue('B' . ($summaryStart + 1), $summary['total_rows']);
        $sheet->setCellValue('A' . ($summaryStart + 2), 'Total Inbound Qty');
        $sheet->setCellValue('B' . ($summaryStart + 2), $summary['total_in_qty']);
        $sheet->setCellValue('A' . ($summaryStart + 3), 'Total Outbound Qty');
        $sheet->setCellValue('B' . ($summaryStart + 3), $summary['total_out_qty']);
        $sheet->setCellValue('A' . ($summaryStart + 4), 'Inbound Cost Value');
        $sheet->setCellValue('B' . ($summaryStart + 4), $summary['total_in_cost_value']);
        $sheet->setCellValue('A' . ($summaryStart + 5), 'Outbound Sales Value');
        $sheet->setCellValue('B' . ($summaryStart + 5), $summary['total_out_sales_value']);
        $sheet->getStyle('B' . ($summaryStart + 4) . ':B' . ($summaryStart + 5))->getNumberFormat()->setFormatCode('$#,##0.00');

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        download_file($path, $filename);
    }

    if ($format === 'pdf') {
        if (!class_exists('\Dompdf\Dompdf')) {
            throw new Exception('Dompdf is not available. Run: composer require dompdf/dompdf');
        }

        $filename = $baseName . '.pdf';
        $path = $exportDir . $filename;

        $dompdfOptions = new \Dompdf\Options();
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($dompdfOptions);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; font-size: 9pt; }
            h1 { text-align:center; margin: 0 0 10px; }
            .meta { text-align:center; color:#666; margin-bottom:10px; }
            table { width:100%; border-collapse: collapse; font-size:8pt; }
            th { background:#1F4E78; color:#fff; padding:6px; border:1px solid #333; }
            td { padding:5px; border:1px solid #CCC; vertical-align: top; }
            .right { text-align:right; }
            .summary { margin-top:12px; padding:8px; background:#F3F3F3; border:1px solid #CCC; }
            .summary div { margin:3px 0; }
        </style></head><body>';

        $html .= '<h1>Inventory Transaction Report</h1>';
        $html .= '<div class="meta">Generated on ' . date('F j, Y g:i A') . '</div>';
        $html .= '<table><thead><tr>
            <th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>Unit</th>
            <th>Cost</th><th>Selling</th><th>Customer</th><th>Supplier</th><th>Ref</th>
        </tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>
                <td>' . escape_html((string)$row['created_at']) . '</td>
                <td>' . escape_html((string)$row['item_code']) . ' - ' . escape_html((string)$row['item_name']) . '</td>
                <td>' . escape_html((string)$row['transaction_label']) . '</td>
                <td class="right">' . escape_html((string)$row['quantity_signed']) . '</td>
                <td>' . escape_html((string)$row['unit_of_measure']) . '</td>
                <td class="right">$' . number_format((float)$row['unit_cost'], 2) . '</td>
                <td class="right">$' . number_format((float)$row['selling_price'], 2) . '</td>
                <td>' . escape_html((string)($row['customer_name'] ?? '-')) . '</td>
                <td>' . escape_html((string)($row['supplier_name'] ?? '-')) . '</td>
                <td>' . escape_html((string)($row['reference_display'] ?? '-')) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<div class="summary">
            <div><strong>Total Rows:</strong> ' . intval($summary['total_rows']) . '</div>
            <div><strong>Total Inbound Qty:</strong> ' . intval($summary['total_in_qty']) . '</div>
            <div><strong>Total Outbound Qty:</strong> ' . intval($summary['total_out_qty']) . '</div>
            <div><strong>Inbound Cost Value:</strong> $' . number_format((float)$summary['total_in_cost_value'], 2) . '</div>
            <div><strong>Outbound Sales Value:</strong> $' . number_format((float)$summary['total_out_sales_value'], 2) . '</div>
        </div>';
        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());

        download_file($path, $filename);
    }

    if ($format === 'word') {
        if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
            throw new Exception('PhpWord is not available. Run: composer require phpoffice/phpword');
        }

        $filename = $baseName . '.docx';
        $path = $exportDir . $filename;

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginTop' => 600,
            'marginBottom' => 600,
            'marginLeft' => 600,
            'marginRight' => 600
        ]);

        $section->addText(
            'Inventory Transaction Report',
            ['bold' => true, 'size' => 16],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            'Generated on: ' . date('F j, Y g:i A'),
            ['size' => 10],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addTextBreak(1);

        $phpWord->addTableStyle('TxReportTable', [
            'borderSize' => 4,
            'borderColor' => '999999',
            'cellMargin' => 40
        ]);
        $table = $section->addTable('TxReportTable');

        $headerFont = ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'];
        $headerCell = ['bgColor' => '1F4E78'];
        $table->addRow();
        $table->addCell(1300, $headerCell)->addText('Date', $headerFont);
        $table->addCell(1300, $headerCell)->addText('Item', $headerFont);
        $table->addCell(900, $headerCell)->addText('Type', $headerFont);
        $table->addCell(700, $headerCell)->addText('Qty', $headerFont);
        $table->addCell(700, $headerCell)->addText('Unit', $headerFont);
        $table->addCell(900, $headerCell)->addText('Cost', $headerFont);
        $table->addCell(900, $headerCell)->addText('Selling', $headerFont);
        $table->addCell(1400, $headerCell)->addText('Customer', $headerFont);
        $table->addCell(1400, $headerCell)->addText('Supplier', $headerFont);
        $table->addCell(1200, $headerCell)->addText('Reference', $headerFont);

        foreach ($rows as $row) {
            $table->addRow();
            $table->addCell(1300)->addText((string)$row['created_at'], ['size' => 8]);
            $table->addCell(1300)->addText((string)$row['item_code'], ['size' => 8]);
            $table->addCell(900)->addText((string)$row['transaction_label'], ['size' => 8]);
            $table->addCell(700)->addText((string)$row['quantity_signed'], ['size' => 8]);
            $table->addCell(700)->addText((string)$row['unit_of_measure'], ['size' => 8]);
            $table->addCell(900)->addText('$' . number_format((float)$row['unit_cost'], 2), ['size' => 8]);
            $table->addCell(900)->addText('$' . number_format((float)$row['selling_price'], 2), ['size' => 8]);
            $table->addCell(1400)->addText((string)($row['customer_name'] ?? '-'), ['size' => 8]);
            $table->addCell(1400)->addText((string)($row['supplier_name'] ?? '-'), ['size' => 8]);
            $table->addCell(1200)->addText((string)($row['reference_display'] ?? '-'), ['size' => 8]);
        }

        $section->addTextBreak(1);
        $section->addText('Summary', ['bold' => true, 'size' => 11]);
        $section->addText('Total Rows: ' . intval($summary['total_rows']), ['size' => 10]);
        $section->addText('Total Inbound Qty: ' . intval($summary['total_in_qty']), ['size' => 10]);
        $section->addText('Total Outbound Qty: ' . intval($summary['total_out_qty']), ['size' => 10]);
        $section->addText('Inbound Cost Value: $' . number_format((float)$summary['total_in_cost_value'], 2), ['size' => 10]);
        $section->addText('Outbound Sales Value: $' . number_format((float)$summary['total_out_sales_value'], 2), ['size' => 10]);

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);
        download_file($path, $filename);
    }
} catch (Exception $e) {
    log_error('Transaction report export failed: ' . $e->getMessage());
    set_flash('Export failed: ' . $e->getMessage(), 'error');
    redirect('transactions.php');
}
