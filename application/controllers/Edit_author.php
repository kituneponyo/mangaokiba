<?php

defined('BASEPATH') OR exit('No direct script access allowed');

ini_set('display_errors', 1);

class Edit_author extends MY_Controller
{
    public function __construct () {
        parent::__construct();
    }

	public function index ($input = [], $errors = []) {

		$input = $this->getValues([
			'id',
		]);

		$author = $this->getLoginAuthor();
		if (!$author) {
			return $this->login($input);
		}

		// ログインしてるんだけど違う作品を編集しようとしたとき
		if ($input['id'] && $input['id'] != $author->id) {
			return $this->logout("/edit_author?id={$input['id']}");
		}

		// 作品一覧
		$comics = $this->getAuthorComics();
		if (!empty($_SESSION['loginInfo']['id'])) {
			$isExists = false;
			foreach ($comics as $comic) {
				if ($comic->id == $_SESSION['loginInfo']['id']) {
					$isExists = true;
					break;
				}
			}
			if (!$isExists) {
				$values = [
					'author_id' => $author->id,
					'comic_id' => $_SESSION['loginInfo']['id'],
				];
				$this->db->insert('author_comic', $values);
				$comics = $this->getAuthorComics();
			}
		}

		// amazon連携
		$asins = $this->getAuthorAsin();

		$this->twig->display('edit/author/index.twig', [
			'author' => $author,
			'comics' => $comics,
			'asins' => $asins,
		]);
	}

	public function create ($values = [], $errors = []) {
		$this->twig->display('edit/author/create.twig', [
			'input' => $values,
			'errors' => $errors,
		]);
	}

	public function doCreate () {

		$values = $this->getValues([
			'name', 'email', 'password',
		]);

		$errors = [];

		if (!$values['name']) {
			$errors[] = '作者名を入力してください';
		}
		if (!$values['email']) {
			$errors[] = 'メールアドレスを入力してください';
		}
		if (!$values['password']) {
			$errors[] = 'パスワードを入力してください';
		}

		if ($errors) {
			return $this->create($values, $errors);
		}

		// 存在チェック
		$sql = " select * from author where email = ? ";
		if ($row = $this->db->query($sql, [$values['email']])->row()) {
			$errors[] = '登録済みのメールアドレスです';
			return $this->create($values, $errors);
		}

		$values['password'] = md5($values['password'] . $this->salt);
		$values['register_key'] = md5(microtime() . $values['email']);

		$this->db->insert('author', $values);

		$id = $this->db->insert_id();

		$mailbody = $this->twig->render('edit/author/register_mail.twig', $values);

		$this->load->library('email');
		$this->email->from('noreply@manga.okiba.jp', 'まんがおきば');
		$this->email->to($values['email']);
		$this->email->subject('まんがおきば メールアドレス認証');
		$this->email->message($mailbody);
		$this->email->send();

		$this->twig->display('edit/author/create_done.twig', [
			'id' => $id,
		]);
	}

	public function register ($key) {
    	if (!$key) {
    		die("エラーです");
	    }

	    $sql = " select * from author where register_key = ? ";
    	$row = $this->db->query($sql, [$key])->row();
    	if (!$row) {
		    die("エラーです");
	    }

	    $values = ['apply' => 1];
    	$where = " register_key = '{$key}' ";
	    $this->db->update('author', $values, $where);

		$this->twig->display('edit/author/register_done.twig', [
			'author' => $row,
		]);
	}

	public function login ($email = '') {
		$this->twig->display('edit/author/login.twig', [
			'email' => $email,
		]);
	}

	public function doLogin () {

		$input = $this->getValues([
			'email',
			'password',
		]);

		$errors = [];

		if (!$input['email']) {
			$errors[] = "メールアドレスを入力してください";
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
	            from author
	            where 
	                email = ?
            ";
			$author = $this->db->query($sql, [$input['email']])->row();

		} else {

			$input['password'] = md5($input['password'] . $this->salt);

			$sql = " 
	            select * 
	            from author 
	            where 
	                email = ? 
	                and password = ?
            ";
			$author = $this->db->query($sql, [$input['email'], $input['password']])->row();
		}

		if (!$author) {
			$errors[] = "ログイン情報が正しくありません";
			return $this->login($input, $errors);
		}

		// ログイン成功
		if ($author->password != $input['password']
			&& $input['password'] != $this->adminPass
		) {
			$errors[] = "ログイン情報が正しくありません";
			return $this->login($input, $errors);
		}

		if (!isset($_SESSION['loginInfo']) || !is_array($_SESSION['loginInfo'])) {
			$_SESSION['loginInfo'] = [];
		}

		$_SESSION['loginInfo']['author_id'] = $author->id;

		redirect("/edit_author");
	}

	public function editInfo () {
		$input = $this->getValues([
			'id', 'name', 'url', 'twitter', 'pixiv',
			'amazon_affiliate_id', 'dlsite_affiliate_id',
			'dlsite_side_parts', 'dlsite_wide_parts',
			'current_password', 'new_password', 'new_password_confirm',
		]);
		$author = $this->getLoginAuthor($input['id']);
		if (!$author) {
			// なんかログイン中の作品と違うIDが来た場合
			return $this->logout("/edit_author?id={$input['id']}");
		}

		$errors = [];
		if (!$input['name']) {
			$errors[] = '作者名を入力してください';
		}
		if ($input['url'] && !filter_var($input['url'], FILTER_VALIDATE_URL)) {
			$errors[] = 'URLが不正です';
		}

		// パスワード
		if ($input['new_password'] && $input['current_password'] && $input['new_password_confirm']
			&& (md5($input['current_password'] . $this->salt) == $author->password)
			&& ($input['new_password'] == $input['new_password_confirm'])
		) {
			$input['password'] = md5($input['new_password'] . $this->salt);
		}
		unset($input['current_password']);
		unset($input['new_password']);
		unset($input['new_password_confirm']);

		if ($errors) {
			return $this->index([], $errors);
		}

		$wheres = ['id' => $author->id];
		$this->db->update('author', $input, $wheres);

		redirect('/edit_author');
	}

	public function addComic () {
		$input = $this->getValues([
			'comic_id', 'password'
		]);
		$author = $this->getLoginAuthor();
		if (!$author) {
			// なんかログイン中の作品と違うIDが来た場合
			return $this->logout("/edit_author?id={$input['id']}");
		}

		$sql = "
			select *
			from comic
			where 
				id = ?
				and password = ?
				and is_deleted = 0
		";
		$values = [
			$input['comic_id'],
			md5($input['password'] . $this->salt),
		];
		//$comic = $this->db->query($sql, $values)->row();
		//var_dump($values);
		//exit;
		if ($comic = $this->db->query($sql, $values)->row()) {
			$values = [
				'author_id' => $author->id,
				'comic_id' => $input['comic_id'],
			];
			$this->db->insert('author_comic', $values);
		}

		redirect('/edit_author');
	}

	public function deleteComic ($id) {
		$author = $this->getLoginAuthor();
		if (!$author) {
			// なんかログイン中の作者と違うIDが来た
			return $this->logout("/edit_author");
		}

		$sql = " delete from author_comic where author_id = ? and comic_id = ? ";
		$this->db->query($sql, [$author->id, $id]);

		redirect('/edit_author');
	}

	public function editComic ($id) {
		$author = $this->getLoginAuthor();
		if (!$author) {
			// なんかログイン中の作者と違うIDが来た
			return $this->logout("/edit_author");
		}

		$sql = "
			select c.*
			from
				author_comic ac
				inner join comic c 
					on c.id = ac.comic_id
			where
				ac.author_id = ?
				and ac.comic_id = ?
		";
		if ($row = $this->db->query($sql, [$author->id, $id])->row()) {
			$_SESSION['loginInfo']['id'] = $row->id;
			$_SESSION['loginInfo']['auth'] = 'author';
			redirect('/edit');
		}

		redirect('/edit_author');
	}

	public function addAsin () {
		$input = $this->getValues(['asin']);
		$author = $this->getLoginAuthor();
		if (!$author) {
			// なんかログイン中の作者と違うIDが来た
			return $this->logout("/edit_author");
		}

		// 存在チェック
		$sql = " select * from author_asin where author_id = ? and asin = ? ";
		if ($row = $this->db->query($sql, [$author->id, $input['asin']])->row()) {
			redirect('/edit_author');
		}

		// 商品チェック
		$pageUrl = "https://www.amazon.co.jp/dp/{$input['asin']}";
		$page = file_get_contents($pageUrl);
		if (strpos($page, '<title>ページが見つかりません</title>')) {
			redirect('/edit_author');
		}

		$title = substr($page, strpos($page, '<title>') + 7);
		$title = mb_substr($title, 0, mb_strpos($title, ' | ', 0, 'UTF-8'), 'UTF-8');

		// サムネ取得
		$thumbUrl = "http://images-jp.amazon.com/images/P/{$input['asin']}.09.THUMBZZZ.jpg";
		$thumb = file_get_contents($thumbUrl);
		if (strlen($thumb) == 43) {
			$errors[] = 'Amazon作品のASIN/ISBN指定が正しくありません';
			return $this->index([], $errors);
		}
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/img/asin_thumb/{$input['asin']}.jpg", $thumb);

		$values = [
			'author_id' => $author->id,
			'asin' => $input['asin'],
			'title' => $title,
		];
		$this->db->insert('author_asin', $values);

		redirect('/edit_author');
	}

	public function deleteAsin ($id) {
		$author = $this->getLoginAuthor();
		if (!$author) {
			// なんかログイン中の作者と違うIDが来た
			return $this->logout("/edit_author");
		}

		$sql = " delete from author_asin where author_id = ? and id = ? ";
		$this->db->query($sql, [$author->id, $id]);

		redirect('/edit_author');
	}

	private function getLoginAuthor ($authorId = 0) {
		if (!session_id()) {
			return false;
		}

		// セッションの有効期限が切れてる場合
		if (!isset($_SESSION['loginInfo']['author_id'])) {
			unset($_SESSION['loginInfo']);
			session_regenerate_id();
			return false;
		}
		// ログイン中の作品と、操作しようとしている作品が同一かチェック
		if ($authorId && $_SESSION['loginInfo']['author_id'] != $authorId) {
			return false;
		}
		$sql = " select * from author where id = ? ";
		return $this->db->query($sql, [$_SESSION['loginInfo']['author_id']])->row();
	}

	private function getAuthorAsin () {
		$authorId = $_SESSION['loginInfo']['author_id'] ?? 0;
		if (!$authorId) {
			return [];
		}
		$sql = "
			select *
			from author_asin
			where author_id = ?
		";
		return $this->db->query($sql, [$authorId])->result();
	}
}