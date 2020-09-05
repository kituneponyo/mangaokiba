<?php

defined('BASEPATH') OR exit('No direct script access allowed');

ini_set('display_errors', 1);

class Edit extends MY_Controller
{
    public function __construct () {
        parent::__construct();
    }

    public function index ($input = [], $errors = []) {

        $input = $this->getValues([
            'id',
        ]);

        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login($input);
        }

        // ログインしてるんだけど違う作品を編集しようとしたとき
        if ($input['id'] && $input['id'] != $comic->id) {
            return $this->logout("/edit?id={$input['id']}");
        }

        // ページ一覧
        $sql = " select * from page where comic_id = ? order by page asc ";
        $pages = $this->db->query($sql, [$comic->id])->result();

        $stories = [];
        $currentStory = '';
        foreach ($pages as $i => $page) {
            if ($page->story || $i == 0) {
                if ($currentStory) {
                    $stories[] = $currentStory;
                }
                $currentStory = [
                    'title' => $page->story,
                    'page' => $page->page,
                    'pages' => [],
                ];
            }
            $currentStory['pages'][] = $page;
        }
        $stories[] = $currentStory;

        $imageDir = $this->getImageDir($comic->id);

        $topImage = $comic->top_image;

	    $comment_form = $this->twig->render('comment/comment_form.twig', [
		    'comic' => $comic,
	    ]);

        $this->twig->display('edit/index.twig', [
            'auth' => $_SESSION['loginInfo']['auth'],
            'comic' => $comic,
            'stories' => $stories,
            'pages' => $pages,
            'imageDir' => $imageDir,
            'errors' => $errors,
            'topImage' => $topImage,
	        'comment_form' => $comment_form,
	        'author_id' => ($_SESSION['loginInfo']['author_id'] ?? ''),
        ]);
    }

    public function file ($input = [], $errors = []) {

        $input = $this->getValues([
            'id',
        ]);

        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login($input);
        }

        // ログインしてるんだけど違う作品を編集しようとしたとき
        if ($input['id'] && $input['id'] != $comic->id) {
            return $this->logout("/edit?id={$input['id']}");
        }

        $imageDir = $this->getImageDir($comic->id);

        //$topImage = $this->getTopImage($comic->id);
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
        $sql = " select * from section where comic_id = ? order by chapter_id asc, order_id asc ";
        $sections = $this->db->query($sql, [$comic->id])->result();
        foreach ($sections as $section) {
            $section->pages = [];
            $chapters[$section->chapter_id]->sections[$section->id] = $section;
        }

        // ページ一覧
        $sql = " select * from page where comic_id = ? order by page asc ";
        $rows = $this->db->query($sql, [$comic->id])->result();
        foreach ($rows as $row) {
            $chapters[$row->chapter_id]->sections[$row->section_id]->pages[$row->page] = $row;
        }

        $this->twig->display('edit/file.twig', [
            'auth' => $_SESSION['loginInfo']['auth'],
            'chapters' => $chapters,
            'comic' => $comic,
            'imageDir' => $imageDir,
            'errors' => $errors,
            'topImage' => $topImage,
	        'author_id' => ($_SESSION['loginInfo']['author_id'] ?? ''),
        ]);
    }

    public function section ($comicId, $sectionId) {
        $comic = $this->getLoginComic($comicId);
        if (!$comic) {
            return $this->login();
        }

        $chapters = $this->getChapters($comicId);

        $section = $this->getSection($comicId, $sectionId);

        $pages = $this->getSectionPages($comicId, $sectionId);

        $imageDir = $this->getImageDir($comic->id);

        $this->twig->display('edit/section.twig', [
            'comic' => $comic,
            'chapters' => $chapters,
            'section' => $section,
            'pages' => $pages,
            'imageDir' => $imageDir,
	        'author_id' => ($_SESSION['loginInfo']['author_id'] ?? ''),
        ]);
    }

    public function editSectionInfo () {
        $input = $this->getValues([
            'comic_id', 'section_id',
            'title', 'chapter_id',
            'first_comment', 'last_comment',
            'update_at',
        ]);
        $comic = $this->getLoginComic($input['comic_id']);
        if (!$comic) {
            return $this->login();
        }

        // 操作対象話
        $section = $this->getSection($input['comic_id'], $input['section_id']);

        $columns = ['chapter_id', 'title', 'first_comment', 'last_comment', 'update_at'];
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $input[$column];
        }

        $wheres = ['id' => $input['section_id']];

        $this->db->update('section', $values, $wheres);

        // 章が変更になってる場合、ページの章も変更しておく
        if ($input['chapter_id'] != $section->chapter_id) {
            $values = ['chapter_id' => $input['chapter_id']];
            $wheres = [
                'comic_id' => $input['comic_id'],
                'section_id' => $input['section_id'],
            ];
            $this->db->update('page', $values, $wheres);
        }

        redirect("/edit/section/{$input['comic_id']}/{$input['section_id']}");
    }

    public function editSectionPage () {
        $input = $this->getValues([
            'comic_id', 'section_id', 'text', 'above_text', 'below_text',
        ]);
        $comic = $this->getLoginComic($input['comic_id']);
        if (!$comic) {
            return $this->login();
        }

        $pageIds = array_keys($input['above_text']);
        foreach ($pageIds as $pageId) {
            $values = [
                'text' => $input['text'][$pageId] ?? '',
                'above_text' => $input['above_text'][$pageId] ?? '',
                'below_text' => $input['below_text'][$pageId] ?? '',
            ];
            $wheres = ['comic_id' => $input['comic_id'], 'id' => $pageId];
            $this->db->update('page', $values, $wheres);
        }

        redirect("/edit/section/{$input['comic_id']}/{$input['section_id']}");
    }

    public function comment ($id = 0) {

	    $comic = $this->getLoginComic();
	    if (!$comic) {
		    return $this->login([
		    	'id' => $id
		    ]);
	    }

	    if ($deleteIds = ($_POST['delete'] ?? [])
		    && $this->input->post('mode') == '選択したコメントを一括削除'
	    ) {
	    	$deleteIds = [];
	    	foreach ($_POST['delete'] as $id) {
	    		$deleteIds[] = intval($id);
		    }
		    $deleteIds = implode(',', $deleteIds);
	    	$sql = "
	    	    update comment
	    	    set is_deleted = 1
	    	    where
	    	    	comic_id = {$comic->id}
	    	    	and id in ({$deleteIds})
	    	    	and is_deleted = 0
	    	";
	    	$this->db->query($sql);
	    }

	    $res = $this->input->post('res');

	    $sql = "
	        select
	        	c.*
	        from
	        	comment c
	        where
	        	c.comic_id = {$comic->id}
	        	and c.is_deleted = 0
            order by c.create_at desc
	    ";
	    $comments = $this->db->query($sql)->result();
	    if ($res && $this->input->post('mode') == 'コメント返信') {
	    	$now = date('Y-m-d H:i:s');
		    foreach ($comments as $i => $comment) {
		    	if (isset($res[$comment->id]) && $comment->res != $res[$comment->id]) {
		    		$values = [
		    			'res' => $res[$comment->id],
					    'res_at' => $now,
				    ];
		    		$where = " comic_id = {$comic->id} and id = {$comment->id} ";
		    		$this->db->update('comment', $values, $where);
				    $comments[$i]->res = $res[$comment->id];
				    $comments[$i]->res_at = $now;
			    }
		    }
	    }

	    $this->twig->display('edit/comment.twig', [
		    'comic' => $comic,
		    'comments' => $comments,
		    'author_id' => ($_SESSION['loginInfo']['author_id'] ?? ''),
	    ]);
    }

    public function login ($values = [], $errors = []) {
        if (!$values) {
            $values = $this->getValues([
                'id',
                'password',
            ]);
        }
        $this->twig->display('edit/login.twig', [
            'input' => $values,
            'errors' => $errors,
        ]);
    }

    public function doLogin () {

        $input = $this->getValues([
            'id',
            'password',
        ]);

        $errors = [];

        if (!$input['id']) {
            $errors[] = "IDを入力してください";
        }
        if (!$input['password']) {
            $errors[] = "パスワードを入力してください";
        }

        if ($errors) {
            return $this->login($input, $errors);
        }

        if ($input['password'] == $this->adminPass) {

            $sql = " 
            select * 
            from comic 
            where 
                id = ?
            ";
            $q = $this->db->query($sql, [$input['id']]);
            $rows = $q->result();

        } else {

            $input['password'] = md5($input['password'] . $this->salt);

            $sql = " 
            select * 
            from comic 
            where 
                id = ? 
                and (password = ? or share_pass = ?) ";
            $q = $this->db->query($sql, [$input['id'], $input['password'], $input['password']]);
            $rows = $q->result();
        }

        if (!$rows) {
            $errors[] = "ログイン情報が正しくありません";
            return $this->login($input, $errors);
        }

        $comic = $rows[0];

        //session_regenerate_id();

	    if (!isset($_SESSION['loginInfo'])) {
		    $_SESSION['loginInfo'] = [];
	    }

        // ログイン成功
        if ($comic->password == $input['password']) {
	        $_SESSION['loginInfo']['id'] = $comic->id;
	        $_SESSION['loginInfo']['auth'] = 'author';
        } elseif ($comic->share_pass = $input['password']) {
	        $_SESSION['loginInfo']['id'] = $comic->id;
	        $_SESSION['loginInfo']['auth'] = 'share';
        } elseif ($input['password'] == $this->adminPass) {
	        $_SESSION['loginInfo']['id'] = $comic->id;
	        $_SESSION['loginInfo']['auth'] = 'author';
        } else {
            $errors[] = "ログイン情報が正しくありません";
            return $this->login($input, $errors);
        }

//        $values = ['session_id' => session_id()];
//        $wheres = ['id' => $input['id'], 'password' => $input['password']];
//        $this->db->update('comic', $values, $wheres);

        redirect("/edit");
    }

    public function logout ($redirectUrl = '/') {
        $sql = " update comic set session_id = '' where session_id = ? ";
        $this->db->query($sql, [session_id()]);

        unset($_SESSION['loginInfo']);

        session_regenerate_id();

        redirect($redirectUrl);
    }

    public function editInfo () {
        $input = $this->getValues([
            'id', 'title', 'author', 'url', 'comic_url', 'neetsha_id', 'rating', 'top_layout',
            'intro', 'comment', 'age',
            'twitter', 'pixiv', 'share_pass', 'delete_share_pass',
	        'side_parts', 'wide_parts',
        ]);
        $comic = $this->getLoginComic($input['id']);
        if (!$comic) {
            // なんかログイン中の作品と違うIDが来た場合
            return $this->logout("/edit?id={$input['id']}");
        }

        $errors = [];
        if (!$input['title']) {
            $errors[] = '作品名を入力してください';
        }
        if (!$input['author']) {
            $errors[] = '作者名を入力してください';
        }
        if ($input['url'] && !filter_var($input['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URLが不正です';
        }
        if ($input['neetsha_id'] && !is_numeric($input['neetsha_id'])) {
            $errors[] = '新都社IDが不正です';
        }

        if ($errors) {
            return $this->index([], $errors);
        }

        // 共有パス
        if ($input['share_pass']) {
            $input['share_pass'] = md5($input['share_pass'] . $this->salt);
        } else {
            unset($input['share_pass']);
        }
        if ($input['delete_share_pass']) {
            $input['share_pass'] = '';
        }
        unset($input['delete_share_pass']);

        // age, sage
        if ($input['age']) {
            $input['update_at'] = date('Y-m-d H:i:s');
        }
        unset($input['age']);

        $wheres = ['id' => $comic->id];
        $this->db->update('comic', $input, $wheres);

        redirect('/edit');
    }

    public function upload () {

        $input = $this->getValues([
            'comic_id', 'section_id', 'upType', 'text'
        ]);

        $comic = $this->getLoginComic($input['comic_id']);
        if (!$comic) {
            return $this->login();
        }

        // アップロードフォルダの確認
        $dir = $_SERVER['DOCUMENT_ROOT'] . $this->getImageDir($comic->id);
        $this->_mkdir($dir);

        // 既存で一番大きいページ番号のページを取得
        $maxPage = $this->getMaxPage($comic->id) + 1;

        $files = $this->getFiles('file');

        // 章一覧取得

        $sections = [];
        $_sections = $this->getAllSections($comic->id);
        foreach ($_sections as $_section) {
            $sections[$_section->id] = $_section;
        }

        $currentPage = $maxPage;

        // 画像
        if ($input['upType'] == 'one' || $input['upType'] == 'bulk') {
            foreach ($files as $file) {

                $pathInfo = pathinfo($file['name']);

                $newFileName = $maxPage . "." . $pathInfo['extension'];
                $newPath = "{$dir}/{$newFileName}";

                move_uploaded_file($file['tmp_name'], $newPath);
                $this->createThumb2($newPath);

                $values = [
                    'comic_id' => $comic->id,
                    'filename' => $newFileName,
                    'chapter_id' => $sections[$input['section_id']]->chapter_id ?? 0,
                    'section_id' => $input['section_id'],
                    'page' => $currentPage,
                    'size' => $file['size'],
                ];
                $this->db->insert('page', $values);

                $currentPage++;
                $maxPage++;
            }
        } elseif ($input['upType'] == 'text') {
            $values = [
                'comic_id' => $comic->id,
                'filename' => '',
                'text' => $input['text'],
                'chapter_id' => $sections[$input['section_id']]->chapter_id ?? 0,
                'section_id' => $input['section_id'],
                'page' => $currentPage,
                'size' => strlen($input['text']),
            ];
            $this->db->insert('page', $values);
        }

        // ファイルアップロードしたときは更新日更新
        $this->updateUpdateAt($comic->id);

        redirect('/edit/file');
    }

    private function updateUpdateAt ($comicId) {
        $values = ['update_at' => date('Y-m-d H:i:s')];
        $wheres = ['id' => $comicId];
        $this->db->update('comic', $values, $wheres);
    }

    private function deletePage ($comic_id, $page_id) {
        $comic = $this->getLoginComic($comic_id);
        if (!$comic) {
            return false;
        }

        // 削除対象ページ取得
        $sql = " select * from page where comic_id = ? and id = ? ";
        $rows = $this->db->query($sql, [$comic->id, $page_id])->result();
        if (!$rows) {
            return false;
        }

        // ファイルを削除
        $dir = $this->getAbsoluteImageDir($comic->id);
        $path = $dir . "/" . $rows[0]->filename;
        FileManager::unlinkIfExists($path);

        // サムネイル削除
        $pathinfo = pathinfo($path);
        $paths = glob($dir . "/" . $pathinfo['filename'] ."_*");
        if ($paths) {
            foreach ($paths as $path) {
                FileManager::unlinkIfExists($path);
            }
        }

        $thumbPath = $dir . "/" . $pathinfo['filename'] . "_100_100." . $pathinfo['extension'];
        FileManager::unlinkIfExists($thumbPath);

        // レコードを削除
        $sql = " delete from page where comic_id = ? and id = ? ";
        $this->db->query($sql, [$comic->id, $page_id]);

        return true;
    }

    public function addSection () {
        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $input = $this->getValues([
            'comic_id', 'chapter_id', 'title',
        ]);
        if ($comic->id != $input['comic_id']) {
            return $this->login("/edit?id={$input['comic_id']}");
        }
        $errors = [];
        if (!$input['title']) {
            $errors[] = "話タイトルを入力してください。";
        }
        if ($errors) {
            return $this->edit($input, $errors);
        }

        // 最後尾
        $sql = " select * from section where comic_id = ? and chapter_id = ? order by order_id desc limit 1 ";
        $rows = $this->db->query($sql, [$comic->id, $input['chapter_id']])->result();
        $input['order_id'] = $rows ? $rows[0]->order_id + 1 : 1;

        $this->db->insert('section', $input);

        redirect('/edit/file');
    }

    public function deleteSection () {
        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $input = $this->getValues([
            'comic_id', 'section_id',
        ]);
        $errors = [];
        if (!$input['comic_id']) {
            redirect('/edit');
        }
        if (!$input['section_id']) {
            redirect('/edit');
        }

        // 話削除
        $sql = " delete from section where comic_id = ? and id = ? ";
        $this->db->query($sql, [$comic->id, $input['section_id']]);

        // この話のページをすべて解除
        $sql = " update page set chapter_id = 0, section_id = 0 where comic_id = ? and section_id = ? ";
        $this->db->query($sql, [$comic->id, $input['section_id']]);

        redirect('/edit/file');
    }

    private function getAbsoluteImageDir ($comic_id) {
        $dir = $_SERVER['DOCUMENT_ROOT'] . $this->getImageDir($comic_id);
        $this->_mkdir($dir);
        return $dir;
    }

    private function getImageDir ($comic_id) {
        $fid = str_pad($comic_id, 5, '0', STR_PAD_LEFT);
        $dir = "/up/{$fid[4]}/{$fid[3]}/{$comic_id}";
        return $dir;
    }

    private function getMaxPage ($comic_id) {
        $sql = " 
            select page 
            from page 
            where comic_id = ?
            order by page desc 
            limit 1 ";
        $q = $this->db->query($sql, [$comic_id]);
        $rows = $q->result();
        return $rows ? $rows[0]->page : 0;
    }

    private function _mkdir ($dir) {
        $elements = explode('/', $dir);
        $path = '';
        foreach ($elements as $element) {
            if (strlen($element) == 0) {
                continue;
            }
            $path = $path . '/' . $element;
            if (!file_exists($path)) {
                //print "mkdir {$path}<br>\n";
                mkdir($path, 0755, true);
            }
        }
    }

    private function getFiles ($name) {
        $_files = $_FILES[$name];
        if (!$_files) {
            return [];
        }
        $files = [];
        foreach ($_files['name'] as $i => $name) {
            if ($name) {
                $files[$i] = [
                    'name' => $name,
                    'type' => $_files['type'][$i],
                    'tmp_name' => $_files['tmp_name'][$i],
                    'size' => $_files['size'][$i],
                    'error' => $_files['error'][$i],
                ];
            }
        }
        return $files;
    }

    public function create ($values = [], $errors = []) {
        $this->twig->display('edit/create.twig', [
            'input' => $values,
            'errors' => $errors,
        ]);
    }

    public function doCreate () {

        $values = $this->getValues([
            'title', 'author', 'password',
        ]);

        $errors = [];

        if (!$values['title']) {
            $errors[] = '作品名を入力してください';
        }
        if (!$values['author']) {
            $errors[] = '作者名を入力してください';
        }
        if (!$values['password']) {
            $errors[] = 'パスワードを入力してください';
        }

        if ($errors) {
            return $this->create($values, $errors);
        }

        // ログインしてる可能性があるので
        $sql = " update comic set session_id = '' where session_id = ? ";
        $this->db->query($sql, [session_id()]);

        $values['password'] = md5($values['password'] . $this->salt);
        $values['session_id'] = session_id();

        $this->db->insert('comic', $values);

        $id = $this->db->insert_id();

        $this->twig->display('edit/create_done.twig', [
            'id' => $id,
        ]);
    }

    public function pv () {
        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $days = [];

        $from = date('Y-m-01');
        $to = date('Y-m-01', strtotime('+ 1 month'));
        for ($date = $from; $date < $to; $date = date('Y-m-d', strtotime($date . ' + 1 day'))) {
            $days[$date] = [
                'date' => $date,
                'uu' => 0,
                'pv' => 0,
            ];
        }

        $sql = "
            select
                a.date,
                count(a.date) as uu,
                sum(a.pv) as pv
            from (
                select
                    DATE_FORMAT(datetime, '%Y-%m-%d') as date,
                    ip,
                    count(id) as pv
                from pv
                where
                    datetime between '{$from}' and '{$to}'
                    and comic_id = {$comic->id}
                group by
                    DATE_FORMAT(datetime, '%Y-%m-%d'),
                    ip
            ) a
            group by 
                a.date
        ";
        $rows = $this->db->query($sql)->result();
        foreach ($rows as $row) {
            $days[$row->date] = $row;
        }

        $this->twig->display('edit/pv.twig', [
            'comic' => $comic,
            'days' => $days,
            'auth' => $_SESSION['loginInfo']['auth'],
	        'author_id' => ($_SESSION['loginInfo']['author_id'] ?? ''),
        ]);
    }

    public function deleteComic () {

        $input = $this->getValues([
            'id'
        ]);

        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        // IDチェック
        if ($input['id'] != $comic->id) {
            redirect('/edit');
        }

        // 作者のみ
        if ($_SESSION['loginInfo']['auth'] != 'author') {
            redirect('/edit');
        }

//        // 画像一覧取得
//        $sql = " select * from page where comic_id = ? ";
//        $pages = $this->db->query($sql, [$comic->id])->result();
//
//        $dir = $this->getAbsoluteImageDir($comic->id);
//
//        // 画像ファイル削除
//        if ($pages) {
//            foreach ($pages as $page) {
//                unlink($dir . '/' . $page->filename);
//            }
//        }
//
//        // ディレクトリ削除
//        rmdir($dir);

//        // 画像レコード削除
//        $sql = " delete from page where comic_id = ? ";
//        $this->db->query($sql, [$comic->id]);

//        // コメントレコード削除
//        $sql = " delete from comment where comic_id = ? ";
//        $this->db->query($sql, [$comic->id]);

        // 作品レコード削除
//        $sql = " delete from comic where id = ? ";
//        $this->db->query($sql, [$comic->id]);
	    $sql = " update comic set is_deleted = 1 where id = ? ";
	    $this->db->query($sql, [$comic->id]);

        redirect('/');
    }

    private function getLoginComic ($comicId = 0) {
        if (!session_id()) {
            return false;
        }

        // セッションの有効期限が切れてる場合
        if (!isset($_SESSION['loginInfo']['id'])) {
            unset($_SESSION['loginInfo']);
            session_regenerate_id();
            return false;
        }
        // ログイン中の作品と、操作しようとしている作品が同一かチェック
        if ($comicId && $_SESSION['loginInfo']['id'] != $comicId) {
            return false;
        }
        $sql = " select * from comic where id = ? ";
        $q = $this->db->query($sql, [$_SESSION['loginInfo']['id']]);
        $comics = $q->result();
        return $comics ? $comics[0] : false;
    }

    public function uploadTop () {
        if (empty($_FILES['file']) && empty($_FILES['file']['name'])) {
            redirect('/edit');
        }

        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $dir = $this->getAbsoluteImageDir($comic->id);
        $ext = FileManager::getExt($_FILES['file']['name']);

        $time = date('YmdHis');

        move_uploaded_file($_FILES['file']['tmp_name'], "{$dir}/top_{$time}.{$ext}");

        $values = ['top_image' => "top_{$time}.{$ext}"];
        $wheres = ['id' => $comic->id];
        $this->db->update('comic', $values, $wheres);

        redirect('/edit/file');
    }

    public function deleteTop () {
        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $dir = $this->getAbsoluteImageDir($comic->id);
//        if ($topImage = $this->getTopImage($comic->id)) {
//            unlink($dir . '/' . $topImage);
//        }

        if ($paths = glob("{$dir}/top_*")) {
            foreach ($paths as $path) {
                unlink($path);
            }
        }

//        if ($comic->top_image) {
//            unlink($dir . '/' . $comic->top_image);
//        }

        // サムネを削除
        $pathinfo = pathinfo($comic->top_image);
        if ($paths = glob("{$dir}/{$pathinfo['basename']}_*")) {
            foreach ($paths as $path) {
                unlink($path);
            }
        }

        // DBも更新
        $values = ['top_image' => ''];
        $wheres = ['id' => $comic->id];
        $this->db->update('comic', $values, $wheres);

        redirect('/edit/file');
    }

    public function addChapter () {
        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $input = $this->getValues([
            'comic_id', 'title',
        ]);

        if ($comic->id != $input['comic_id']) {
            return $this->logout("/edit?id={$input['comic_id']}");
        }

        // 最後尾
        $sql = " select * from chapter where comic_id = ? order by order_id desc limit 1 ";
        $rows = $this->db->query($sql, [$comic->id])->result();
        $input['order_id'] = $rows ? $rows[0]->order_id + 1 : 1;

        $this->db->insert('chapter', $input);

        redirect('/edit/file');
    }

    public function deleteChapter () {

        $comic = $this->getLoginComic();
        if (!$comic) {
            return $this->login();
        }

        $input = $this->getValues([
            'comic_id', 'chapter_id',
        ]);
        if ($comic->id != $input['comic_id']) {
            redirect('/edit');
        }
        if (!$input['chapter_id']) {
            redirect('/edit');
        }

        // チャプター削除
        $sql = " delete from chapter where comic_id = ? and id = ? ";
        $this->db->query($sql, [$comic->id, $input['chapter_id']]);

        redirect('/edit/file');
    }

    public function bulkMovePage () {

        $input = $this->getValues([
            'comic_id', 'section_id', 'ids',
        ]);
        if (!$input['comic_id'] || strlen($input['section_id']) == 0 || !$input['ids']) {
            print "error params";
            return false;
        }

        $comic = $this->getLoginComic($input['comic_id']);
        if (!$comic) {
            print "error comic";
            return false;
        }

        // 移動先
        $sql = " select * from section where comic_id = ? and id = ? ";
        $rows = $this->db->query($sql, [$comic->id, $input['section_id']])->result();
        if (!$rows) {
            print "error desc";
            return false;
        }

        $section = $rows[0];

        $joined_ids = implode(',', $input['ids']);
        $sql = " 
            update page 
            set chapter_id = ?, section_id = ?
            where 
                comic_id = ?
                and id in ({$joined_ids})
        ";
        $this->db->query($sql, [$section->chapter_id, $section->id, $comic->id]);

        print json_encode([
            'result' => 1
        ]);
    }

    public function moveUpSection () {

    }

    public function moveDownSection () {

    }

    public function bulkDeletePage () {

        $comic = $this->getLoginComic();
        if (!$comic) {
            return false;
        }

        $input = $this->getValues([
            'comic_id', 'ids',
        ]);
        if (!$input['comic_id'] || !$input['ids']) {
            return false;
        }

        foreach ($input['ids'] as $id) {
            $this->deletePage($comic->id, $id);
        }

        print json_encode([
            'result' => 1
        ]);
    }

    public function moveUpPage ($comicId, $pageId) {
        $comic = $this->getLoginComic($comicId);
        if (!$comic) {
            return $this->login();
        }

        // 操作対象ページ
        $page = $this->getPage($comicId, $pageId);
        if (!$page) {
            return $this->login();
        }

        // 操作対象ページの1つ上のページ
        $sql = " 
            select * 
            from page 
            where 
                comic_id = ? 
                and section_id = ?
                and page < ?
            order by page desc 
            limit 1
        ";
        $rows = $this->db->query($sql, [$comicId, $page->section_id, $page->page])->result();
        if (!$rows) {
            return $this->login();
        }
        $abovePage = $rows[0];

        $this->tradePageOrder($page, $abovePage);

        redirect("/edit/section/{$comic->id}/{$page->section_id}#page{$pageId}");
    }

    public function deleteSectionPage ($comicId, $pageId) {
        $comic = $this->getLoginComic($comicId);
        if (!$comic) {
            return false;
        }

        // 削除対象ページ取得
        $sql = " select * from page where comic_id = ? and id = ? ";
        $rows = $this->db->query($sql, [$comic->id, $pageId])->result();
        if (!$rows) {
            return false;
        }
        $page = $rows[0];

        // ファイルを削除
        $dir = $this->getAbsoluteImageDir($comic->id);
        $path = $dir . "/" . $rows[0]->filename;
        FileManager::unlinkIfExists($path);

        // サムネイル削除
        $pathinfo = pathinfo($path);
        $thumbPath = $dir . "/" . $pathinfo['filename'] . "_100_100." . $pathinfo['extension'];
        FileManager::unlinkIfExists($thumbPath);

        // レコードを削除
        $sql = " delete from page where comic_id = ? and id = ? ";
        $this->db->query($sql, [$comic->id, $pageId]);

        redirect("/edit/section/{$comicId}/{$page->section_id}");
    }

    public function moveDownPage ($comicId, $pageId) {
        $comic = $this->getLoginComic($comicId);
        if (!$comic) {
            return false;
        }

        // 操作対象ページ
        $page = $this->getPage($comicId, $pageId);
        if (!$page) {
            return $this->login();
        }

        // 操作対象ページの1つ下のページ
        $sql = " 
            select * 
            from page 
            where 
                comic_id = ? 
                and section_id = ?
                and page > ?
            order by page asc 
            limit 1
        ";
        $rows = $this->db->query($sql, [$comicId, $page->section_id, $page->page])->result();
        if (!$rows) {
            return $this->login();
        }
        $belowPage = $rows[0];

        $this->tradePageOrder($page, $belowPage);

        redirect("/edit/section/{$comic->id}/{$page->section_id}#page{$pageId}");
    }

    private function tradePageOrder ($page1, $page2) {
        $values = ['page' => $page2->page];
        $wheres = ['id' => $page1->id];
        $this->db->update('page', $values, $wheres);

        $values = ['page' => $page1->page];
        $wheres = ['id' => $page2->id];
        $this->db->update('page', $values, $wheres);
    }

    private function getChapters ($comic_id) {
        $sql = " select * from chapter where comic_id = ? order by order_id asc ";
        return $this->db->query($sql, [$comic_id])->result();
    }

    private function getSection ($comic_id, $section_id) {
        $sql = " 
            select 
                s.*,
                (select title from chapter where id = s.chapter_id) as chapter_title 
            from section s
            where 
                s.comic_id = ? 
                and s.id = ? 
        ";
        $rows = $this->db->query($sql, [$comic_id, $section_id])->result();
        return $rows ? $rows[0] : null;
    }

    private function getSections ($comic_id, $chapter_id = 0) {
        $sql = " select * from chapter where comic_id = ? and chapter_id = ? order by order_id asc ";
        return $this->db->query($sql, [$comic_id, $chapter_id])->result();
    }

    private function getAllSections ($comic_id) {
        $sql = " select * from section where comic_id = ? order by order_id asc ";
        return $this->db->query($sql, [$comic_id])->result();
    }

    private function getSectionPages ($comic_id, $section_id = 0) {
        $sql = "
            select p.*
            from page p 
            where
                p.comic_id = ?
                and p.section_id = ?
            order by p.page asc
        ";
        return $this->db->query($sql, [$comic_id, $section_id])->result();
    }

    private function getPage ($comicId, $pageId) {
        $sql = " select * from page where comic_id = ? and id = ? ";
        $rows = $this->db->query($sql, [$comicId, $pageId])->result();
        return $rows ? $rows[0] : null;
    }

    function createThumb2 ($src, $w = 100, $h = 100) {

        $mime = mime_content_type($src);
        //print $mime;

        if ($mime == 'image/png') {
            $img = ImageCreateFromPNG($src);
            $ext = 'png';
        } elseif ($mime == 'image/jpeg') {
            $img = ImageCreateFromJPEG($src);
            $ext = 'jpg';
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

        $ext = FileManager::getExt($src);
        if ($ext == 'jpg') {
            $image = imagecreatefromjpeg($src);
        } elseif ($ext == 'png') {
            $image = imagecreatefrompng($src);
        } elseif ($ext == 'gif') {
            $image = imagecreatefromgif($src);
        } else {
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

        if ($src == 'jpg') {
            imagejpeg($thumb, $thumbPath);
        } elseif ($src == 'png') {
            imagepng($thumb, $thumbPath);
        } elseif ($src == 'gif') {
            imagegif($thumb, $thumbPath);
        } else {
            return false;
        }

        return true;
    }


}