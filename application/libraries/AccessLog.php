<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AccessLog
{
    public static function log ($comic_id = 0, $page = 0) {

        $host = gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? '');
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $check = strtolower($host . ' ' . $agent);
        if (strpos($check, 'bot') !== false
            || strpos($check, 'crawl') !== false
            || strpos($check, 'spider') !== false
            || strpos($check, 'Hatena Antenna') !== false
        ) {
            return false;
        }

        //print "<!-- log --> ";
        $CI =& get_instance();

        $CI->load->database();

        $url = $_SERVER['REQUEST_URI'] ?? '';
        if ($url && $pos = strpos($url, '?')) {
            $url = substr($url, 0, $pos);
            while (strpos($url, '//')) {
                $url = str_replace('//', '/', $url);
            }
        }
        $values = [
            'url' => $url,
            'comic_id' => $comic_id,
            'page' => $page,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'host' => gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        $CI->db->insert('pv', $values);
    }
}