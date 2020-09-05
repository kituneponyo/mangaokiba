<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class That extends MY_Controller {

    public function __construct () {
        parent::__construct();
    }

    public function index () {

	    // ランダムトップ
	    $sql = " 
            select * 
            from comic 
            where 
                top_image != '' 
                and neetsha_id = 0
            order by rand() limit 1 ";
	    $rows = $this->db->query($sql)->result();
	    $randomTop = $rows ? $rows[0] : false;
	    $randomTopImage = ComicManager::getImageDir($randomTop->id) . "/" . $randomTop->top_image;

	    // 更新順
	    $sql = " 
            select
                c.*
            from 
                comic c
            where 
                c.neetsha_id = 0 
                and (select id from page where comic_id = c.id limit 1) is not null
            order by
                c.update_at desc 
        ";
	    $comics = $this->db->query($sql)->result();
	    foreach ($comics as $i => $comic) {
		    $comics[$i] = ComicManager::decorate($comic);
	    }

	    $this->twig->display('that.twig', [
		    'randomTop' => $randomTop,
		    'randomTopImage' => $randomTopImage,
		    'comics' => $comics,
	    ]);
    }



}
