<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class MalaNasobilkaPresenter extends Nette\Application\UI\Presenter
{



    public function renderDefault(): void {
        //do something

        if(array_key_exists("submit", $_GET)) {
            $rows = $_GET["rows"];
            $cols = $_GET["cols"];
            if (isset($rows) && isset($cols)) {
                if (!is_numeric($rows) or !is_numeric($cols)) {
                    $rows = null;
                    $cols = null;
                }
            }
            if (isset($rows) && isset($cols)) {
                $this->template->rows = $rows;
                $this->template->cols = $cols;
            } else {
                //error message
            }
        }
    


    }


}
