<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalogos extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->output->set_content_type('application/json');
    }

    public function formatos() {
        echo json_encode($this->db->get('cat_formatos')->result_array());
    }

    public function paises() {
        echo json_encode($this->db->get('cat_paises')->result_array());
    }

    public function acabados() {
        echo json_encode($this->db->get('cat_acabados')->result_array());
    }

    public function unidades() {
        echo json_encode($this->db->get_where('cat_unidad_base', ['estado' => 1])->result_array());
    }
}
