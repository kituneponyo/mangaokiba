<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Comic extends MY_Controller {

    public function __construct () {
        parent::__construct();
    }

    public function top ($id = 0) {
        if (!$id) {
	        return $this->twig->display('comic/notfound.twig');

        }

        $comic = $this->getComic($id);
        if (!$comic) {
	        return $this->twig->display('comic/notfound.twig');
        }

	    AccessLog::log($comic->id);

        if ($comic->comic_url) {
	        redirect($comic->comic_url);
        }

        $imageDir = $this->getImageDir($comic->id);

        $topImage = $comic->top_image;

        //章一覧
        $sql = " select * from chapter where comic_id = ? order by order_id asc ";
        $rows = $this->db->query($sql, [$comic->id])->result();
        $chapters = [];
        foreach ($rows as $row) {
            $row->sections = [];
            $chapters[$row->id] = $row;
        }

        //話一覧
        $sql = "
            select
                s.*,
                (select count(id) from page where section_id = s.id) as page_count
            from section s
            where s.comic_id = ? 
            order by s.chapter_id asc, s.order_id asc ";
        $sections = $this->db->query($sql, [$comic->id])->result();
        foreach ($sections as $section) {
            $section->pages = [];
            $chapters[$section->chapter_id]->sections[$section->id] = $section;
        }

        // 話未設定ページ
        $sql = " select count(id) as count from page where comic_id = ? and section_id = 0 ";
        if ($rows = $this->db->query($sql, [$comic->id])->result()) {
            $unCompiledPageCount = $rows[0]->count;
        } else {
            $unCompiledPageCount = 0;
        }

        // 最新更新話
        $sql = " select * from section where comic_id = ? order by update_at desc, id desc limit 1 ";
        $rows = $this->db->query($sql, [$comic->id])->result();
        $latestSection = $rows ? $rows[0] : false;

        $author = $this->getAuthor($id);

//        // Amazonアフィリエイト
//	    if ($author && !$comic->neetsha_id /*&& $author->amazon_affiliate_id*/) {
//	    	$sql = "
//	    	    select *
//	    	    from author_asin
//	    	    where author_id = ?
//	    	    order by rand()
//	    	    limit 3;
//	    	";
//	    	$asins = $this->db->query($sql, [$author->id])->result();
//	    }

	    // コメント
	    $sql = " select * from comment where comic_id = ? and is_deleted = 0 order by create_at desc limit 5 ";
	    $latest_comments = $this->db->query($sql, [$comic->id])->result();

	    // コメント件数
	    $sql = " select count(id) as comment_count from comment where comic_id = ? and is_deleted = 0 ";
	    $comment_count = $this->db->query($sql, [$comic->id])->row();

        $this->twig->display('comic/top.twig', [
            'comic' => $comic,
            'latestSection' => $latestSection,
            'chapters' => $chapters,
            'unCompiledPageCount' => $unCompiledPageCount,
            'imageDir' => $imageDir,
            'topImage' => $topImage,
	        'author' => $author,
			'latest_comments' => $latest_comments,
			'comment_count' => $comment_count->comment_count,
//	        'asins' => ($asins ?? false),
        ]);
    }

    public function story ($id = 0, $section_id = 0) {

        if (!$id) {
            redirect('/');
        }

        $comic = $this->getComic($id);
        if (!$comic) {
            redirect('/');
        }

        // 話一覧
        $sql = "
            select
                c.title as chapter_title, 
                s.*
            from 
                section s
                left outer join chapter c 
                    on c.id = s.chapter_id
            where 
                s.comic_id = ? 
            order by
                coalesce(c.order_id, 99999) asc,
                coalesce(s.order_id, 99999) asc
        ";
        $sections = $this->db->query($sql, [$comic->id])->result();
        if ($section_id && !$sections) {
            redirect("/comic/{$id}");
        }

        if ($section_id) {

            $currentIndex = -1;

            foreach ($sections as $i => $section) {
                if ($section->id == $section_id) {
                    $currentIndex = $i;
                    break;
                }
            }

            if ($currentIndex == -1) {
                redirect("/comic/{$id}");
            }

            // 次があれば
            $current_section = $sections[$currentIndex];
            $prev_section_id = $sections[$currentIndex - 1]->id ?? -1;
            $next_section_id = $sections[$currentIndex + 1]->id ?? -1;

            // 完全未整理があれば次がある
            if ($next_section_id == -1) {
                $sql = " select id from page where comic_id = ? and section_id = 0 ";
                if ($rows = $this->db->query($sql, [$comic->id])->result()) {
                    $next_section_id = 0;
                }
            }

        } else {

            // section_id = 0 であれば、かならず最後
            $current_section = -1;
            $prev_section_id = $sections[count($sections) - 1]->id ?? -1;
            $next_section_id = -1;

        }

        $sql = " select * from page where comic_id = ? and section_id = ? order by page asc ";
        $pages = $this->db->query($sql, [$comic->id, $section_id])->result();
        if (!$section_id && !$pages) {
            redirect("/comic/{$id}");
        }

        AccessLog::log($comic->id, $section_id);


	    $author = $this->getAuthor($id);

	    // Amazonアフィリエイト
	    if ($author && !$comic->neetsha_id /*&& $author->amazon_affiliate_id*/) {
		    $sql = "
	    	    select *
	    	    from author_asin
	    	    where author_id = ?
	    	    order by rand()
	    	    limit 3;
	    	";
		    $asins = $this->db->query($sql, [$author->id])->result();
	    }

        $this->twig->display('comic/story.twig', [
            'comic' => $comic,
            'pages' => $pages,
            'imageDir' => $this->getImageDir($comic->id),
            'current_section' => $current_section,
            'prev_section_id' => $prev_section_id,
            'next_section_id' => $next_section_id,
	        'author' => $author,
	        'asins' => $asins ?? [],
        ]);
    }

    public function sectionThumb ($sectionId = 0) {
        if (!$sectionId) {
            return false;
        }

        $sql = "
            select *
            from page p
            where
                p.section_id = ?
            order by p.page asc 
            limit 1
        ";
        $rows = $this->db->query($sql, [$sectionId])->result();
        if (!$rows) {
            return false;
        }
        $page = $rows[0];

        $path = $this->getAbsoluteImageDir($page->comic_id) . '/' . $page->filename;

        $w = $this->input->get('w') ?? 200;
        $h = $this->input->get('h') ?? 200;

        $dir = dirname($path);
        $pathInfo = pathinfo($path);
        $thumbPath = $dir . "/" . $pathInfo['filename'] . "_{$w}_{$h}." . $pathInfo['extension'];

        if (!file_exists($thumbPath)) {
            if (!$this->createThumb2($path, $w, $h)) {
                print "create thumb error";
                return false;
            }
        }

        header("Content-Type: image/{$pathInfo['extension']}");
        readfile($thumbPath);
    }

    public function thumb () {
        $path = $this->input->get('path');
        if (!$path) {
            return false;
        }
        $path = $_SERVER['DOCUMENT_ROOT'] . "/" . $path;
        if (!file_exists($path)) {
            print "file not exists";
            return false;
        }

        $w = $this->input->get('w') ?? 200;
        $h = $this->input->get('h') ?? 200;

        $dir = dirname($path);
        $pathInfo = pathinfo($path);
        $thumbPath = $dir . "/" . $pathInfo['filename'] . "_{$w}_{$h}." . $pathInfo['extension'];

        if (!file_exists($thumbPath)) {
            if (!$this->createThumb2($path, $w, $h)) {
                //print "create thumb error";
                return false;
            }
        }

        header("Content-Type: image/{$pathInfo['extension']}");
        readfile($thumbPath);
    }

    private function getImageDir ($comic_id) {
        $fid = str_pad($comic_id, 5, '0', STR_PAD_LEFT);
        return "/up/{$fid[4]}/{$fid[3]}/{$comic_id}";
    }

    private function getPages ($id) {
        $sql = " select * from page where comic_id = ? ";
        return $this->db->query($sql, [$id])->result();
    }

    private function getComic ($id) {
        $sql = " 
 			select * 
 			from comic 
 			where 
 				id = ? 
 				and is_deleted = 0
        ";
        $rows = $this->db->query($sql, [$id])->result();
        if (!$rows) {
            return false;
        }
        return $rows[0];
    }


    private function getAbsoluteImageDir ($comic_id) {
        return $_SERVER['DOCUMENT_ROOT'] . $this->getImageDir($comic_id);
    }

    function createThumb2 ($src, $w = 100, $h = 100) {

        $mime = mime_content_type($src);
        //print $mime;

        if ($mime == 'image/png') {
            $img = ImageCreateFromPNG($src);
            $ext = 'png';
        } elseif ($mime == 'image/jpeg') {
            $img = ImageCreateFromJPEG($src);
            $ext = pathinfo($src, PATHINFO_EXTENSION);
        } elseif ($mime == 'image/gif') {
            $img = ImageCreateFromGIF($src);
            $ext = 'gif';
        } else {
            return false;
        }

        $width = ImageSx($img);
        $height = ImageSy($img);

        $long = $width >= $height ? $width : $height;

        $newW = $width * $w / $long;
        $newH = $height * $h / $long;

        $out = ImageCreateTrueColor($newW, $newH);
        ImageCopyResampled($out, $img,
            0,0,0,0, $newW, $newH, $width, $height);

        $dir = dirname($src);
        $pathInfo = pathinfo($src);
        $thumbPath = $dir . "/" . $pathInfo['filename'] . "_{$w}_{$h}." . $ext;

        if ($mime == 'image/png') {
            imagepng($out, $thumbPath);
        } elseif ($mime == 'image/jpeg') {
            imagejpeg($out, $thumbPath);
        } elseif ($mime == 'image/gif') {
            imagegif($out, $thumbPath);
        }

        return true;
    }

    private function createThumb ($src, $w = 100, $h = 100) {

        $pathInfo = pathInfo($src);
        $ext = $pathInfo['extension'];
        if ($ext == 'jpg') {
            $image = imagecreatefromjpeg($src);
        } elseif ($ext == 'png') {
            $image = imagecreatefrompng($src);
        } elseif ($ext == 'gif') {
            $image = imagecreatefromgif($src);
        } else {
            print "extension error : {$ext}";
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        if ( $width >= $height ) {
            //横長の画像の時
            $side = $height;
            $x = floor(($width - $height) / 2 );
            $y = 0;
            $width = $side;
        } else {
            //縦長の画像の時
            $side = $width;
            $y = floor( ( $height - $width ) / 2 );
            $x = 0;
            $height = $side;
        }

        $thumb = imagecreatetruecolor($w, $h);

        imagecopyresized($thumb, $image, 0, 0, $x, $y, $w, $h, $width, $height );

        $dir = dirname($src);
        $pathInfo = pathinfo($src);
        $thumbPath = $dir . "/" . $pathInfo['filename'] . "_{$w}_{$h}." . $ext;

        if ($ext == 'jpg') {
            imagejpeg($thumb, $thumbPath);
        } elseif ($ext == 'png') {
            imagepng($thumb, $thumbPath);
        } elseif ($ext == 'gif') {
            imagegif($thumb, $thumbPath);
        } else {
            print "extension error : {$ext}";
            return false;
        }

        return $thumbPath;
    }

    private function getAuthor ($comicId) {
    	$sql = "
    	    select a.*
    	    from
    	    	author_comic ac
    	    	inner join author a 
    	    		on a.id = ac.author_id
    	    where
    	    	ac.comic_id = ?
    	";
    	return $this->db->query($sql, [$comicId])->row();
    }

}
