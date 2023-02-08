<?php

namespace App\Presenters;

use Nette;

//class CliPresenter extends \App\Presenters\BasePresenter {
class CliPresenter extends Nette\Application\UI\Presenter {
    
    /**
     * @inject
     * @var \App\Models\FormModel
     */
    public $model;
 
   
    
    function __construct() {
        //$this->simplia = $simplia;
    }

    
    protected function startup(): void {

        parent::startup();
        if (php_sapi_name() !== 'cli') {
            echo "This is fault";
            $this->terminate(); //zakomentovat pro bdump v $this->model->spravaCeniku($data['data'])
        }
    }
    

    public function actionKusovnik() {
        $data = json_decode(file_get_contents("temp_files/kusovnik_temp.json"), TRUE);
        $_SESSION['fb-session'] = $data['fb-session'];
        $_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/kusovnik_temp.json");
        $res = $this->model->kusovnik($data['data']);
        
        $this->terminate();
    }
    
    public function actionSpravaceniku() {
        $data = json_decode(file_get_contents("temp_files/cenik_temp.json"), TRUE);
        //bdump($data);
        //die(); //timto zobrazim soubor temp_files/cenik_temp.json, ktery se jinak dale smaze
        //$_SESSION['fb-session'] = $data['fb-session'];
        //$_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/cenik_temp.json");
        $res = $this->model->spravaCeniku($data['data']);
        unlink("temp_files/xlsx_temp.xlsx");
        
        //$this->redirect('Status:default');
        $this->terminate();
    } 

    public function actionPrevodnaprodejnu() {
        $data = json_decode(file_get_contents("temp_files/prevod.json"), TRUE);
        $_SESSION['fb-session'] = $data['fb-session'];
        $_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/prevod.json");
        $res = $this->model->prevodNaProdejnu($data['data']['prodejna']);
        
        
        $this->terminate();
    } 
    
    public function actionTisketiketskl() {
        $data = json_decode(file_get_contents("temp_files/tiskskl.json"), TRUE);
        $_SESSION['fb-session'] = $data['fb-session'];
        $_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/tiskskl.json");
        $res = $this->model->tiskEtiketSkl($data['data']['ids']);
        
        
        $this->terminate();
    }
    public function actionTisketiketobv() {
        $data = json_decode(file_get_contents("temp_files/tiskobv.json"), TRUE);
        $_SESSION['fb-session'] = $data['fb-session'];
        $_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/tiskobv.json");
        $res = $this->model->tiskEtiketObv($data['data']['ids']);
        
        
        $this->terminate();
    }
    public function actionTisketiketobp() {
        $data = json_decode(file_get_contents("temp_files/tiskobp.json"), TRUE);
        $_SESSION['fb-session'] = $data['fb-session'];
        $_SESSION['fb-company'] = $data['fb-company'];
        unlink("temp_files/tiskobp.json");
        $res = $this->model->tiskEtiketObp($data['data']['ids']);
        
        
        $this->terminate();
    }
    
        public function actionSimpliaToFb() {
        $kek = $this->simplia->simpliaToFb();
        file_put_contents('log/kek.log', print_r($kek, 1));
        $this->terminate();
    }

    public function actionDeletePomocneDoklady() {
        $_SESSION['autoscript'] = true;
        $this->model->getDokladNaMazani('poptavka-prijata');
    }
}

