<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class StatusPresenter extends Nette\Application\UI\Presenter
{
    /**
     * @inject
     * @var \App\Models\StatusModel
     */
    public $model;

	public function actionStatus() {
        if (file_exists('temp_files/status.json')) {
            $status = file_get_contents('temp_files/status.json', TRUE);
            echo json_encode($status);
            exit;
        }
        echo json_encode(['running' => false, 'error' => 'No file found']);
        exit;
    }

    public function renderDefault(): void {
        $controls = $this->model->getControl();
        $checkDuplicity = false;
        $errorsToXls = false;
        foreach ($controls as $control) {
            if ($control['job'] == 'checkDuplicity' && $control['execute'] === true) {
                $checkDuplicity = true;
            }
            if ($control['job'] == 'errorsToXls' && $control['execute'] === true) {
                $errorsToXls = true;
            }
        }
        $this->template->checkDuplicity = $checkDuplicity;
        $this->template->errorsToXls = $errorsToXls;

    }


}
