<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class StatusPresenter extends Nette\Application\UI\Presenter
{
	public function actionStatus() {
        if (file_exists('temp_files/status.json')) {
            $status = file_get_contents('temp_files/status.json', TRUE);
            echo json_encode($status);
            exit;
        }
        echo json_encode(['running' => false, 'error' => 'No file found']);
        exit;
    }


}
