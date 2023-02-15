<?php

namespace App\Models;

use \Nette\Neon\Neon;
/**
 * Description of StatusModel
 *
 * @author garret
 */
final class StromModel {

    use \Nette\SmartObject;


    function __construct() {
        //$this->mail_config = $mail_config;
        //$this->flexibee = $flexibee;
    }

    public function getAllData($evidence) {
        $odpovedJson = file_get_contents("../app/schema/StromJson.json");
        $odpoved = json_decode($odpovedJson, $associative = true);
        return $odpoved;
    }

}
