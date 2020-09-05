<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class About extends MY_Controller {

    public function __construct () {
        parent::__construct();
    }

    public function index () {
        $this->twig->display('about.twig', [
        ]);
    }

    public function phpinfophpinfo () {
        phpinfo();
    }

    public function imagickTest () {
        $thumbnail = new \Imagick(); // Imagickオブジェクトを生成
    }


}
