<?php
require_once '../libs/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configure directories
$fontDir = '../storage/fonts/';
$tempDir = '../storage/temp/';
$outputDir = 'generated_ids/';

// Create directories if needed
if (!file_exists($fontDir)) mkdir($fontDir, 0777, true);
if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);
if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);

// Configure Dompdf
$options = new Options();
$options->set([
    'isRemoteEnabled' => true,
    'isHtml5ParserEnabled' => true,
    'isPhpEnabled' => true,
    'isJavascriptEnabled' => false, // Disable JS for more reliable PDFs
    'fontDir' => $fontDir,
    'fontCache' => $fontDir,
    'tempDir' => $tempDir,
    'chroot' => realpath('../'),
    'defaultFont' => 'Helvetica',
]);

$dompdf = new Dompdf($options);

// Get the HTML content
$html = file_get_contents('card-template.html');

// Inject CSS directly into the HTML
$css = file_get_contents('../assets/css/card.css');
$html = str_replace(
    '<link rel="stylesheet" href="../assets/css/card.css">',
    '<style>' . $css . '</style>',
    $html
);

// Pre-process the HTML for better PDF rendering
$processedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        /* Injected card.css styles */
        {$css}
        
        /* PDF-specific overrides */
        body { margin: 0; padding: 0; }
        .card-container { page-break-inside: avoid; }
        
        /* Force important styles */
        .member-photo {
            width: 100px !important;
            height: 100px !important;
            margin-bottom: 0 !important;
        }
        #barcode-output, #qrcode-output {
            visibility: visible !important;
        }
    </style>
</head>
<body>
    <div id="card-container">
        <!-- Your card content will be rendered here -->
        {$html}
    </div>
</body>
</html>
HTML;

// Load HTML content
$dompdf->loadHtml($processedHtml);

// Set paper size (match your card dimensions)
$dompdf->setPaper([0, 0, 595, 842], 'portrait'); // A4 size in points

// Render with error handling
try {
    $dompdf->render();
    
    // Generate filename
    $filename = $outputDir . 'membership_card_' . time() . '.pdf';
    
    // Save the file
    file_put_contents($filename, $dompdf->output());
    
    // Output to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="membership_card.pdf"');
    echo $dompdf->output();

} catch (Exception $e) {
    die("PDF generation failed: " . $e->getMessage());
}
?>