<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class FileManager
{
    public static function getExt ($filename) {
        $pathInfo = pathinfo($filename);
        return strtolower($pathInfo['extension']);
    }

    public static function unlinkIfExists ($path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}