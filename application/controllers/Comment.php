<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Comment extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
    }

    public function index () {
        $this->twig->display('comment/index.twig', [

        ]);
    }

    public function view($id = 0, $messages = [])
	{
	    if (!$id) {
	        return $this->index();
        }

        $sql = " 
            select * 
            from comic 
            where 
            	id = ? 
 				and is_deleted = 0
 		";
	    $rows = $this->db->query($sql, [$id])->result();
	    if (!$rows) {
	        return $this->index();
        }
        $comic = $rows[0];

	    if ($comic->neetsha_id) {
	        header("location: https://neetsha.jp/inside/comment.php?id={$comic->neetsha_id}");
	        exit;
        }

	    $sql = "
            select * 
            from comment
            where 
                comic_id = ? 
                and is_deleted = 0
            order by 
                create_at desc 
            limit 1000
        ";
	    $params = [$id];
	    $comments = $this->db->query($sql, $params)->result();

		$author = $this->getAuthor($id);

		$this->twig->display('comment/view.twig', [
			'author' => $author,
		    'comic' => $comic,
            'comments' => $comments,
			'messages' => $messages
        ]);
	}

    /**
     * 最新コメント
     */
	public function latest () {
        $sql = " 
            select
                comment.*,
                comic.title,
                comic.author
            from 
                comment
                inner join comic
                    on comic.id = comment.comic_id
                    and comic.is_deleted = 0
                    and comic.neetsha_id = 0
            order by create_at desc
            limit 100 ";
        $rows = $this->db->query($sql)->result();
        $this->twig->display('comment/latest.twig', [
            'comments' => $rows,
        ]);
    }

	public function post () {

        $input = $this->getValues([
            'comic_id', 'comment'
        ]);
        $errors = [];
        if (!$input['comic_id']) {
            redirect('/comment');
        }
        if (!$input['comment']) {
            $errors[] = 'コメントを入力してください';
        }
		mb_regex_encoding("UTF-8");
		if (!preg_match("/[ぁ-んァ-ヶー一-龠]+/u", $input['comment'])) {
			$errors[] = 'コメントには日本語を含めてください';
		}

        if ($errors) {
//            $comicController = new Comic();
//            return $comicController->detail($input['comic_id']);
            return $this->view($input['comic_id'], $errors);
        }

        $values = [
            'comic_id' => $input['comic_id'],
            'comment' => $input['comment'],
	        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
	        'host' => gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? ''),
	        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        $this->db->insert('comment', $values);

        redirect("/comment/view/{$input['comic_id']}");
    }

    public function storage () {

	    $this->twig->display('comment/storage.twig', [
	    ]);
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
