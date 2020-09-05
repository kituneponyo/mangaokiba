<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ComicManager
{
    public static function log($comic_id = 0, $page = 0)
    {
        //print "<!-- log --> ";
        $CI =& get_instance();

        $CI->load->database();
    }

    public static function getAbsoluteImageDir ($comicId) {
        return $_SERVER['DOCUMENT_ROOT'] . self::getImageDir($comicId);
    }

    public static function getImageDir ($comicId) {
        $fid = str_pad($comicId, 5, '0', STR_PAD_LEFT);
        return "/up/{$fid[4]}/{$fid[3]}/{$comicId}";
    }

    public static function getComic ($id) {
        $CI =& get_instance();
        $CI->load->database();
        $sql = " select * from comic where id = ? ";
        $rows = $CI->db->query($sql, [$id])->result();
        if (!$rows) {
            return false;
        }
        return self::decorate($rows[0]);
    }

    public static function decorate ($comic) {
        $comic->imageDir = self::getImageDir($comic->id);
        return $comic;
    }
}