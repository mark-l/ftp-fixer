<?php 

/**
 * Fix for hourly file overwriting stock levels. 
 */

require_once(getcwd() . '/vendor/autoload.php'); 
require_once(getcwd() . '/ftp_handler.php'); 

use PhpOffice\PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Sync\FTP\Ftp_Handler;

// workaround for timezones such as BST. 
date_default_timezone_set("CET");

$connections = array();

$connections[] = array(
    'details' => array(
        'server' => 'ftp.stockinthechannel.co.uk',
        'username' => 'DistriAccount40805',
        'password' => 'tDmBg2017',
    ),
    'filename' => 'SITC-Sync.xlsx',
    'replacements' => array('119140' => '500')
);

$connections[] = array(
    'details' => array(
        'server' => 'ftp.stockinthechannel.co.uk',
        'username' => 'DistriAccount60741',
        'password' => 'Q1jfReO3?T9T',
    ),
    'filename' => 'SITC-SyncCC.xlsx',
    'replacements' => array('119140' => '500')
);

$delimiter = ',';
$encloser = '"';

$fields = array();

$fields = array();
$fields[] = 'part number';
$fields[] = 'description';
$fields[] = 'price';
$fields[] = 'stock';
$fields[] = 'brand';

$tempPath = sys_get_temp_dir();
if ( substr($tempPath, -1) != DIRECTORY_SEPARATOR ) {
    $tempPath .= DIRECTORY_SEPARATOR; 
}

foreach ( $connections as $connection ) {

    $filename = $connection['filename'];

    $replacements = $connection['replacements'];

    //$localFilePath = $tempPath . $filename;
    $localFilename = $tempPath . $filename . date('His') . '.xlsx';
    //$localFilename = getcwd() . '/' . $filename . date('His') . '.xlsx';
    //$localFilename = $tempPath . $filename;

    ///*
    print PHP_EOL;
    print 'input : ' . $filename . PHP_EOL;
    print 'output : ' . $localFilename . PHP_EOL;
    print PHP_EOL;
    //*/

    $ftpHandler = new Ftp_Handler();

    if ( $ftpHandler->connect($connection['details']) ) {

        if ( $ftpHandler->checkRemoteFile($filename, $localFilename) ) {

            if ( $ftpHandler->getFile($filename, $localFilename) ) {

                $reader = IOFactory::createReader('Xlsx');

                $spreadsheet = $reader->load($localFilename);
                
                //$spreadsheet = new \PhpOffice\PhpSpreadsheet\IOFactory::load($localFilename);

                $activeSheet = $spreadsheet->getActiveSheet();
                //$data = $activeSheet('', false, false); 

                $rowNumber = 1;
                $sku = '';
                do {

                    $sku = trim($activeSheet->getCell('A' . $rowNumber));

                    // lower to avoid problems in future. 
                    $sku = strtolower($sku); 

                    foreach ( $replacements as $key => $value ) {
                        if ( $sku == strtolower($key) ) {
                            $activeSheet->setCellValue('D' . $rowNumber, $value);
                        }
                    }

                    $rowNumber++; 

                    // failsafe
                    if ( $rowNumber > 5000000 ) {
                        $sku = '';
                    }

                } while ( !empty($sku) );

                // write out the new file. 
                $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
                $writer->save($localFilename);
                //$writer->close();

                //$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                //$writer->save($localFilename);
                //$writer->close();

                print "Saved to : " . $localFilename . PHP_EOL;

                // clean up. 
                unset($worksheet); 
                unset($spreadsheet); 
                unset($reader); 
                unset($writer); 

                // get it back. 
                if ( $ftpHandler->putFile($localFilename, $filename) ) {
                    print "Success" . PHP_EOL; 
                }

            }

        }

    }

}

exit; 
