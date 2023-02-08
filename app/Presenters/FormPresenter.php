<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class FormPresenter extends Nette\Application\UI\Presenter {

    /**
     * @inject
     * @var \App\Models\FormModel
     */
    public $model;

    //public function actionSpravaceniku($session, $url) {
        public function renderDefault(): void
    {
        //$_SESSION['fb-session'] = $session;
        //$_SESSION['fb-company'] = $this->model->getCompanyFromUrl($url);

        $this->template->fileLink = '/files/sprava_ceniku-template.xlsx';
    }

    protected function createComponentCenik(): \Nette\ComponentModel\Component {
        $form = new \Nette\Application\UI\Form;
        $form->addUpload('file')->setRequired();
        $form->addSubmit('submit', 'Provést')
                ->setHtmlAttribute('class', 'btn btn-primary mt-4');

        $form->onSuccess[] = [$this, 'cenikFormSubmitted'];
        return $form;
    }

    public function cenikFormSubmitted(\Nette\Application\UI\Form $form, \Nette\Utils\ArrayHash $values) {
        set_time_limit(20000);
        ini_set('memory_limit', '2000M');
        try {
            $fileStatus = "temp_files/status.json";
            if (file_exists($fileStatus)) {
                unlink($fileStatus);
            }
            $fileErrorsToXls = "temp_files/chyby.xlsx";
            if (file_exists($fileErrorsToXls)) {
                unlink($fileErrorsToXls);
            }
            $fileDuplicity = "temp_files/duplicity.xlsx";
            if (file_exists($fileDuplicity)) {
                unlink($fileDuplicity);
            }
            $resPrepare = $this->model->prepareDataCenik($values['file']->getTemporaryFile());
            if ($resPrepare) {
                //Linux
                //exec("php index.php Cli:spravaceniku > /dev/null &");
                //Windows
                //exec("php index.php Cli:spravaceniku"); //zakomentovat pro bdump v $this->model->spravaCeniku($data['data'])
                $cmd = "php index.php Cli:spravaceniku";
                if (substr(php_uname(), 0, 7) == "Windows"){
                    pclose(popen("start /B ". $cmd, "r")); 
                }
                else {
                    exec($cmd . " > /dev/null &");  
                }
                //$this->redirect('Cli:spravaceniku'); //odkomentovat pro bdump v $this->model->spravaCeniku($data['data'])
                $this->redirect('Status:default');
            }
        } catch (\Nette\UnexpectedValueException $exc) {
            $this->template->error = $exc->getMessage();
        }
    }

}