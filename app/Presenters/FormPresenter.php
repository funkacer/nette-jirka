<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class FormPresenter extends Nette\Application\UI\Presenter {

    protected function createComponentCenik(): \Nette\ComponentModel\Component {
        $form = new \Nette\Application\UI\Form;
        $form->addUpload('file')->setRequired();
        $form->addSubmit('submit', 'Provést')
                ->setHtmlAttribute('class', 'btn btn-primary mt-4');

        $form->onSuccess[] = [$this, 'cenikFormSubmitted'];
        return $form;
    }

    public function cenikFormSubmitted(\Nette\Application\UI\Form $form, \Nette\Utils\ArrayHash $values) {
        set_time_limit(10000);
        ini_set('memory_limit', '1048M');
        try {
            $resPrepare = $this->model->prepareDataCenik($values['file']->getTemporaryFile());
            if ($resPrepare) {
                //Linux
                //exec("php index.php Cli:spravaceniku > /dev/null &");
                //Windows
                exec("php index.php Cli:spravaceniku"); //zakomentovat pro bdump v $this->model->spravaCeniku($data['data'])
                //$this->redirect('Cli:spravaceniku'); //odkomentovat pro bdump v $this->model->spravaCeniku($data['data'])
                $this->redirect('Worker:resultcli');
            }
        } catch (\Nette\UnexpectedValueException $exc) {
            $this->template->error = $exc->getMessage();
        }
    }

}