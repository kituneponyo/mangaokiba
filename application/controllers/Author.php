<?php

defined('BASEPATH') OR exit('No direct script access allowed');
    
class Author extends MY_Controller {

    public function index ()
    {
    }
  
    public function detail ($id)
    {
    }
  
    public function create () {
        $this->twig->display('author/create.twig', [

        ]);
    }

    public function doCreate () {

    }

  public function edit ($id) {

      $idol = $this->idol->getById($id);

      $this->twig->display('idol/edit.twig', [
          'idol' => $idol
      ]);
  }

  public function edit_execute () {

      $values = [
          'name' => $this->input->post('name'),
          'kana' => $this->input->post('kana'),
      ];
      if ($year = $this->input->post('year')) {
          $values['birth_year'] = $year;
      }
      if ($month = $this->input->post('month')) {
          $values['birth_month'] = $month;
      }
      if ($day = $this->input->post('day')) {
          $values['birth_date'] = $day;
      }
      if ($year && $month && $day) {
          $values['birthday'] = "{$year}-{$month}-{$day}";
      }
      
      $keys = [
          'height', 'weight', 'bust', 'waist', 'hip', 'cup'
      ];
      foreach ($keys as $key) {
          if ($value = $this->input->post($key)) {
              $values[$key] = $value;
          }
      }
      
      $wheres = [
          'id' => $id = $this->input->post('id')
      ];

      $this->db->update('idol', $values, $wheres);

      header('location: /idol/' . $id);
  }

  private function getUnitsByIdolId ($idolId) {
  
      $q = $this->db->query("
          select
              u.*
          from
              belong b
              inner join unit u
                  on u.id = b.unit_id
          where
              b.idol_id = {$idolId}
      ");
      return $q->result();
  }

    private function getSnssByIdolId ($idolId) {
        $q = $this->db->query("
            select
                s.name,
                s.base_url,
                i.account,
                i.notice
            from
                idol_sns i
                inner join sns s
                    on s.id = i.sns_id
            where
                i.idol_id = {$idolId}
        ");
        return $q->result();
    }
}
