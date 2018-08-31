<?php

/** 
 * Copyright 2018 Social Manager.
 * PHP version 7.2.8
 *
 * It will Zip the file
 * 
 * @category Album_Manager
 * @package  Zipper
 * @author   Kishan Jasani <kishanjasani007@yahoo.in>
 * @license  https://rtfbchallenge.000webhostapp.com/privacy_policy/privacy_policy.php 
 * @link     ""
 * 
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Kishan Jasani.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND
 */
$zip_folder = "";
/** 
 * Starting of zipping folders recursively 
 */

class Zipper
{
    /**
     * It will load the file which willl zip
     * 
     * @param String $source Source of the folder
     * 
     * @return ""
     */
    public function loadZipFiles($source) 
    {
        if (!file_exists($source)) {
            return false;
        }
        $source = str_replace('\\', '/', realpath($source));
        $a = array();
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
                    continue;
                $file = realpath($file);
                if (is_dir($file) === true) {
                    $a[] = array(
                    'type' => 'dir',
                    'source' => str_replace($source . '/', '', $file . '/'),
                    'file' => $file,
                    'size' => 0
                    );
                } else if (is_file($file) === true) {
                    $src = str_replace($source . '/', '', $file);
                    $size = filesize($file);
                    $a[] = array(
                    'type' => 'file',
                    'source' => $src,
                    'file' => $file,
                    'size' => false != $size ? $size : 16000 
                    );
                }
            }
        }
        return $a;
    }
    /**
     * It will process the zip
     * 
     * @param String $foldercontent content of the folder
     * @param String $folder        folder name
     * @param String $maxsize       maximum size of the folder
     * 
     * @return ""
     */
    public function processZip($foldercontent, $folder, $maxsize)
    {
        $split = array();
        $splits = 1;
        $t = 0;
        // Determine how many zip files to create
        if (isset($foldercontent)) {
            foreach ($foldercontent as $entry) {
                $t = $t + $entry['size'];
                if ($entry['type'] == 'dir') {
                    $lastdir = $entry;
                }
                if ($t >= $maxsize) {
                    $splits++;
                    $t = 0;
                    // create lastdir in next archive, in case files still exist
                    // even if the next file is not in this archive it doesn't hurt
                    if ($lastdir !== '') {
                        $split[$splits][] = $lastdir;
                    }
                }
                $split[$splits][] = $entry;
            }
            // delete the $foldercontent array
            unset($foldercontent);
            // Create the folder to put the zip files in
            $date = new DateTime();
            $tS = $date->format('YmdHis');
            // Process the splits
            foreach ($split as $idx => $sp) {
                // create the zip file
                $zip = new ZipArchive();
                $destination = $folder . '.zip';
                if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
                    return false;
                }
                $i = 1;
                $dir = "";
                foreach ($sp as $entry) {
                    if ($entry['type'] === 'dir') {
                        $dir = explode('\\', $entry['file']);
                        $zip->addEmptyDir(end($dir));
                    } else {
                        $zip->addFromString(
                            end($dir).'/'.$i.'.jpg', 
                            file_get_contents($entry['file'])
                        );
                        $i++;
                    }
                }
                $zip->close();
            }
            return array(
                'splits' => count($split),
                'foldername' => ''
            );
        }
    }
    /**
     * It will get the memory limit
     * 
     * @return ""
     */
    public function getMemoryLimit()
    {
        $memory_limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
            } else if ($matches[2] == 'K') {
                $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
            }
        }
        return $memory_limit;
    }

    /**
     * It will Make the zip
     * 
     * @param String $album_download_directory folder will be zip 
     * 
     * @return ""
     */
    public function makeZip($album_download_directory) 
    {
        $zipfilename = "";
        if (isset($album_download_directory)) {
            //$zipfilename = 'libs/resources'.DIRECTORY_SEPARATOR.'albums'.DIRECTORY_SEPARATOR.'fb-album_'.date("Y-m-d").'_'.date("H-i-s");
            $zipfilename = 'public/fb-album_'.date("Y-m-d").'_'.date("H-i-s");
            
            $folder = dirname($_SERVER['PHP_SELF']).'/'.$album_download_directory;
            // Server Root
            $root = $_SERVER["DOCUMENT_ROOT"];
            // source of the folder to unpack
            $sourcedir = $root . $folder; // target directory
            // Don't use more than half the memory limit
            $memory_limit = $this->getMemoryLimit();
            $maxsize = $memory_limit / 2;
            // Is zipping possible on the server ?
            if (!extension_loaded('zip')) {
                echo 'Zipping not possible on this server';
                exit;
            }
            // Get the files to zip
            $foldercontent = $this->loadZipFiles($sourcedir);
            if ($foldercontent === false) {
                echo 'Something went wrong gathering the file entries';
                exit;
            }
            // Process the files to zip
            $zip = $this->processZip($foldercontent, $zipfilename, $maxsize);
            if ($zip === false) {
                echo 'Something went wrong zipping the files';
            }    
            // clear the stat cache (created by filesize command)
            clearstatcache();
            include_once 'Unlink_Directory.php';
            $unlink_directory = new Unlink_Directory();
            $unlink_directory->removeDirectory($album_download_directory);
        }
        return $zipfilename;
    }
    /**
     * It will get the zip file
     * 
     * @param String $album_download_directory folder will be zip 
     * 
     * @return ""
     */
    public function getZip($album_download_directory) 
    {
        $response = '<span style="color: #ffffff;">Sorry due to some reasons albums is not downloaded.</span>';
        if (isset($album_download_directory)) {
            $zip_folder = $this->makeZip($album_download_directory);
            if (!empty($zip_folder)) {
                $response = '<a href="' . $zip_folder . '.zip" id="download-link" target="_blank" class="btn" >Download Zip Folder</a>';
            }
        }
        return $response;
    }
}
?>