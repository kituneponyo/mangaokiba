<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends MY_Controller {

	public function __construct () {
		parent::__construct();
	}

	public function index () {

		$showEro = $_COOKIE['showEro'] ?? 0;
		if ($this->input->get('showEro')) {
			setcookie('showEro', 1, time()+60*60*24*30*12);
			$showEro = 1;
		}
		if ($this->input->get('hideEro')) {
			setcookie('showEro', 0, time()+60*60*24*30*12);
			$showEro = 0;
		}

		if ($showEro) {
			$whereEro = '';
		} else {
			$whereEro = " and c.rating = 0 ";
		}

		// ランダムトップ
		$sql = " 
            select c.* 
            from comic c
            where 
                c.top_image != '' 
                and c.neetsha_id = 0
                and c.is_deleted = 0
                {$whereEro}
            order by rand() limit 1 ";
		$rows = $this->db->query($sql)->result();
		$randomTop = $rows ? $rows[0] : false;
		$randomTopImage = ComicManager::getImageDir($randomTop->id) . "/" . $randomTop->top_image;

		// 更新順（非エロ）
		$sql = " 
            select
                c.*
            from 
                comic c
            where 
                c.neetsha_id = 0 
                and (select id from page where comic_id = c.id limit 1) is not null
                and is_deleted = 0
                {$whereEro}
            order by
                c.update_at desc 
        ";
		$comics = $this->db->query($sql)->result();
		foreach ($comics as $i => $comic) {
			$comics[$i] = ComicManager::decorate($comic);
		}

		// トップ下広告
		$sql = "
			select a.* from (
		        select dlsite_wide_parts as wide_parts from author where dlsite_wide_parts is not null 
		        union
		        select wide_parts from comic where wide_parts is not null
	        ) a
	        order by rand()
	    ";
		$wide_parts = $this->db->query($sql)->row();

		$this->twig->display('index.twig', [
			'randomTop' => $randomTop,
			'randomTopImage' => $randomTopImage,
			'comics' => $comics,
			'wide_parts' => $wide_parts,
			'showEro' => $showEro,
		]);
	}


}
