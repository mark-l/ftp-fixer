<?php 

namespace Sync\FTP;

define('FTP_MODE', FTP_BINARY);

class Ftp_Handler {

    private $_ftp;

    function connect($details) : bool {

        $returnValue = false;

        // set up basic connection
        $ftp = ftp_connect($details['server']); 

        // login with username and password
        $loginResult = ftp_login($ftp, $details['username'], $details['password']); 

        // check connection
        if ((!$ftp) || (!$loginResult)) { 
            echo "FTP connection has failed!";
            echo "Attempted to connect to " . $details['server'] . " for user " . $details['username']; 
            exit; 
        } else {
            // set passive mode. 
            //ftp_set_option($ftp, FTP_USEPASVADDRESS, false);
            ftp_pasv($ftp, true); 

            $this->_ftp = $ftp;

            $returnValue = true; 
        }

        return $returnValue;

    }

    // check remote file to local file to see if it is newer. 
    function checkRemoteFile($filename, $localFilename) : bool {

        // failsafe to false;
        $returnValue = false;

        // get filesize of the output file on the ftp server. 
        $fileSize = ftp_size($this->_ftp, $filename);
        if ( $fileSize == -1 ) {
            // file does not exists
            return $returnValue;
        }

        // check that local file exists. 
        if ( !is_file($localFilename) ) {
            $returnValue = true;
        } else {
            $remoteFileModTime = ftp_mdtm($this->_ftp, $filename);
            $localFileModTime = filemtime($localFilename);
            ///*
            print PHP_EOL;
            print PHP_EOL;
            print 'Current: ' . date('Y-m-d H:i:s', date('U'));
            print PHP_EOL;
            print 'Original: ' . date('Y-m-d H:i:s', $remoteFileModTime);
            print PHP_EOL;
            print 'Target: ' . date('Y-m-d H:i:s', $localFileModTime);
            print PHP_EOL;
            print PHP_EOL;
            //*/
            if ( $localFileModTime < $remoteFileModTime ) {
                // local file older than remote file.
                $returnValue = true;
            }
        }

        return $returnValue;

    }


    function getFile( $filename, $localFilename) {

        return ftp_get($this->_ftp, $localFilename, $filename, FTP_MODE );

    }

    function putFile($localFilename, $filename) {

        print "Sending" . PHP_EOL;
        print $localFilename . ' -> ' . $filename . PHP_EOL; 

        return ftp_put($this->_ftp, $filename, $localFilename, FTP_MODE );

    }


}
