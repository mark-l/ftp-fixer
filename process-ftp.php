<?php 

// workaround for timezones such as BST. 
date_default_timezone_set("CET");

$delimiter = ',';
$encloser = '"';

// ftp details.
// plain text yes I know.. I am sorry. Please don't hate me.  
$ftpServer = 'ftp.stockinthechannel.co.uk';
$ftpUsername = 'DistriAccount55255';
$ftpPassword = '7Co$bpHc0ezt';
$ftpMode = FTP_ASCII;
//$ftpMode = FTP_BINARY;

// files in use. 
$filename = 'Resync.csv';
//$outputFilename = date('Ymd_Hi_') . $filename;
$outputFilename = 'CCS Apple Refurb.csv';

$tempPath = sys_get_temp_dir();
if ( substr($tempPath, -1) != DIRECTORY_SEPARATOR ) {
    $tempPath .= DIRECTORY_SEPARATOR; 
}

$localFilePath = $tempPath . $filename;
$outputFilePath = $tempPath . $outputFilename;

// ftp pathname.
$path = '/';

/* 
file columns 

    input: 
        Part Number,
        Description,
        Price,
        Stock,
        Manufacturers Part Number,
        Brand

    output:
        Part Number,
        Description,
        Price,
        Stock,
        Manufacturers Part Number,
        Brand,
        Warranty (length and type)

*/

$columns = array();

$columns['partNumber'] = 0;
$columns['description'] = 1;
$columns['price'] = 2;
$columns['stock'] = 3;
$columns['manufacturersPartNumber'] = 4;
$columns['brand'] = 5;
$columns['warranty'] = 6;

/*
print PHP_EOL;
print 'input : ' . $filename . PHP_EOL;
print 'output : ' . $outputFilename . PHP_EOL;
print PHP_EOL;
*/

// set up basic connection
$ftp = ftp_connect($ftpServer); 

// login with username and password
$loginResult = ftp_login($ftp, $ftpUsername, $ftpPassword); 

// check connection
if ((!$ftp) || (!$loginResult)) { 
    echo "FTP connection has failed!";
    echo "Attempted to connect to $ftpServer for user $ftpUsername"; 
    exit; 
} else {
    //echo "Connected to $ftpServer, for user $ftpUsername";
}

// set passive mode. 
//ftp_set_option($ftp, FTP_USEPASVADDRESS, false);
ftp_pasv($ftp, true); 

// get filesize of the output file on the ftp server. 
// -1 means no file. 
$fileSize = ftp_size($ftp, $path . $outputFilename);

// check if output file older than input file, or just missing. 
$process = false;
if ( $fileSize == -1 ) {
    // file does not exists
    $process = true;
} else {
    $sourceFileModTime = ftp_mdtm($ftp, $filename);
    $targetFileModTime = ftp_mdtm($ftp, $path . $outputFilename);
    /*
    print PHP_EOL;
    print PHP_EOL;
    print 'Current: ' . date('Y-m-d H:i:s', date('U'));
    print PHP_EOL;
    print 'Original: ' . date('Y-m-d H:i:s', $sourceFileModTime);
    print PHP_EOL;
    print 'Target: ' . date('Y-m-d H:i:s', $targetFileModTime);
    print PHP_EOL;
    print PHP_EOL;
    */
    if ( $targetFileModTime <= $sourceFileModTime ) {
        // output file older than input file.
        $process = true;
    }
}

if ( !$process ) {
    //print PHP_EOL . "Skipping" . PHP_EOL . PHP_EOL;
} else {

    // grab the input file. 

    /*
    print PHP_EOL;
    print "Getting : " . $filename;
    print " to " . $localFilePath;
    print PHP_EOL;
    print PHP_EOL;
    */
    
    $downloaded = ftp_get($ftp, $localFilePath, $filename, $ftpMode );

    if ( $downloaded ) {

        $fpr = fopen($localFilePath, 'r');
        $fpw = fopen($outputFilePath, 'w');

        if ( $fpr && $fpw ) {

            $first = true;

            $counter = 0;
            $outputCount = 0;
            $skippedCounter = 0;

            while ( $fields = fgetcsv($fpr, 1024, $delimiter, $encloser, '\\') ) {

                $counter++;

                if ( $first ) { 

                    $first = false;

                    $fields[$columns['warranty']] = 'Warranty (length and type)';

                } else {

                    // check for zero stock levels. 
                    if ( $fields[$columns['stock']] < 1 ) {
                        $skippedCounter++;
                        // skip empty entries.
                        continue;
                    }

                    if ( substr($fields[$columns['partNumber']], -4) != '-RFP' ) {
                        $fields[$columns['partNumber']] .= '-RFP';
                    }
                    if ( substr($fields[$columns['manufacturersPartNumber']], -4) != '-RFP' ) {
                        $fields[$columns['manufacturersPartNumber']] .= '-RFP';
                    }
                    if ( substr($fields[$columns['description']], 0, 12) != 'REFURBISHED:' ) {
                        $fields[$columns['description']] = 'REFURBISHED: ' . trim($fields[$columns['description']]);
                    }

                    $outputCount++;

                    $fields[$columns['warranty']] = '12-months return to base';

                }

                fwrite($fpw, $encloser . implode($encloser . $delimiter . $encloser, $fields) . $encloser . PHP_EOL);

            }

            fclose($fpr); 
            fclose($fpw); 

            /*
            print PHP_EOL;
            print PHP_EOL;
            print "Processed: " . $counter;
            print PHP_EOL;
            print "Skipped: " . $skippedCounter;
            print PHP_EOL;
            print "Output: " . $outputCount;
            print PHP_EOL;
            */
            
            // upload.
            $uploaded = ftp_put($ftp, $outputFilename, $outputFilePath, $ftpMode);

        }

    }
}

ftp_close($ftp);