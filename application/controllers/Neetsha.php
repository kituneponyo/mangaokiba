<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Neetsha extends MY_Controller {

    public function __construct () {
        parent::__construct();
    }

    public function index () {

        $sql = " select * from comic where neetsha_id > 0 order by update_at desc ";
        $comics = $this->db->query($sql)->result();

        $this->twig->display('neetsha.twig', [
            'comics' => $comics,
        ]);
    }

}
