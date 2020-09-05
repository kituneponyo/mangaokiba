<?php

ini_set('max_file_uploads', 1000);

//ini_set( 'session.gc_divisor', 1);
//ini_set( 'session.gc_maxlifetime', 5);


/**
 * Class MY_Controller
 *
 * @property CI_Input $input
 *
 * @property CI_DB_mysqli_driver $db
 *
 * @property CI_Session $session
 *
 * @property Twig $twig
 */
class MY_Controller extends CI_Controller {

	var $amazonAccessKey = '';
	var $amazonSecretKey = '';
	var $adminPass = 'adminpass';
	var $salt = 'salt';

  public function __construct() {
      parent::__construct();

      $this->load->helper('form');
      $this->load->helper('url');

      $this->load->library('twig');
//      $this->load->library('session');
      //$this->twig->addFilter(new \Twig\TwigFilter('url2link', [$this, 'url2link']));

	  //$this->twig->addFilter(new \Twig\TwigFilter('is_numeric', 'is_numeric'));

//      print "<!-- ";
//      var_dump($this->twig);
//      print " -->";

      $this->twig;

      $this->load->database();

      $this->load->library('AccessLog');

      $this->load->library('FileManager');
      $this->load->library('ComicManager');

      session_start();
  }

	protected function getValues ($columns) {
	$values = [];
	foreach ($columns as $column) {
		$values[$column] = $this->input->post_get($column);
	}
	return $values;
	}

	protected function getAuthorComics () {
		$authorId = $_SESSION['loginInfo']['author_id'] ?? 0;
		if (!$authorId) {
			return [];
		}
		$sql = "
			select
				c.*
			from
				author_comic ac
				inner join comic c
					on c.id = ac.comic_id
			where
				ac.author_id = ?
		";
		return $this->db->query($sql, [$authorId])->result();
	}

	protected function file_post_contents ($url, $params) {

		// POSTデータ
		$data = http_build_query($params);

		// header
		$header = array(
			"Content-Type: application/x-www-form-urlencoded",
			"Content-Length: ".strlen($data)
		);

		$context = array(
			"http" => array(
				"method"  => "POST",
				"header"  => implode("\r\n", $header),
				"content" => $data
			)
		);

		return file_get_contents($url, false, stream_context_create($context));
	}
}
