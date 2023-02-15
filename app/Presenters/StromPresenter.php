<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class StromPresenter extends Nette\Application\UI\Presenter {

    /**
     * @inject
     * @var \App\Models\StromModel
     */
    public $model;

    public $poleUzlu;

    function __construct() {
        //$this->config = \Nette\Neon\Neon::decode(file_get_contents('../app/config/common.neon'))['parameters']['config'];
    }

	public function renderDefault(){  

        $evidence = "strom";
        $poleUzlu = [];
        $maxHladina = 0;
        $hladinaMaxCount = [];

        $odpoved = $this->model->getAllData($evidence);
        //dumpe($odpoved);
        //bdump($odpoved);

        foreach($odpoved as $odpUzel) {
            //$odpUzel = $this->model->getData($evidence, (int)$odp["id"])[0];
            //$poleUzlu[$odpUzel["cesta"]] =  $odpUzel; //pro zobrazeni stromu po sort, ale nefungovalo tak spravim nize
            $poleIds[$odpUzel["id"]]["nazev"] =  $odpUzel["nazev"]; //pro nalezeni hodnot podle id
            if ($odpUzel["hladina"] > $maxHladina) {
                $maxHladina = $odpUzel["hladina"];
            }
            //new
            $levels = explode("/", rtrim($odpUzel["cesta"], "/"));
            $lev = 1;
            foreach ($levels as $level) {
                if (isset($hladinaMaxCount[$lev])) {
                    if (strlen($level) > $hladinaMaxCount[$lev]) {
                        $hladinaMaxCount[$lev] = strlen($level);
                    }
                } else {
                    $hladinaMaxCount[$lev] = strlen($level);
                }
                $lev++;
            }
            //bdump($odpUzel);
        }

        //bdump($poleIds);

        //pro zobrazeni stromu po sort musim spravit cestu
        foreach($odpoved as $odpUzel) {
            $cesta = "";
            $levels = explode("/", rtrim($odpUzel["cesta"], "/"));
            $lev = 1;
            foreach ($levels as $level) {
                for ($i = 0; $i < $hladinaMaxCount[$lev] - strlen($level); $i++) {
                    $cesta .= "0";
                }
                $cesta .= $level."/";
                $lev++;
            }
            $poleUzlu[$cesta] = $odpUzel; //pro zobrazení stromu po sort
            $poleUzlu[$cesta]["hasChild"] = false; //pro zbrazeni +/- ve strom default.latte
            $poleIds[$odpUzel["id"]]['mojecesta'] = $cesta; //pro kontrolu presouvani pod sebe
        }

        foreach($poleUzlu as $cesta => $odpUzel) {
            if ($odpUzel["hladina"] > 1) {
                $cestaProChild = substr($cesta, 0, strrpos(rtrim($cesta, "/"), "/"))."/";
                //bdump(substr($cesta, 0, strrpos(rtrim($cesta, "/"), "/"))."/", $cesta);
                if (array_key_exists($cestaProChild, $poleUzlu)) {
                    $poleUzlu[$cestaProChild]["hasChild"] = true;
                }
            }
        }

        //bdump($poleUzlu);
        ksort($poleUzlu);
        $this->poleUzlu = $poleUzlu;
        $this->template->poleUzlu = $poleUzlu;
        $this->template->maxHladina = $maxHladina;

        if(isset($_POST["strom-submit"])) {
            //bdump($_POST);

            //kontrola že vybráno
            if (isset($_POST["od"]) && isset($_POST["do"])) {

                //$idOd = (int)$_POST["od"];
                $idOdPole = $_POST["od"];
                $idDo = $_POST["do"];

                //$uzelOd = $poleIds[$idOd];
                //$uzelDo = $poleIds[$idDo];

                //bdump($uzelOd);
                //bdump($uzelDo);

                //kontroly - do nesmí být v rámci vybrané a nižších položek

                /*
                $seznamPolozekPod = [];
                $seznamOtcuIds = [$poleIds[$idOd]["id"]];
                $seznamOtcuKody = [$poleIds[$idOd]["kod"]];
                for ($i = $uzelOd["hladina"]; $i <= $maxHladina; $i++) {
                    foreach($poleIds AS $uzel) {
                        if (substr($uzel["otec"], 0, 5)  == "code:") {
                            //bdump(substr($uzel["otec"], 5));
                            //bdump($seznamOtcuKody);
                            if (in_array(substr($uzel["otec"], 5), $seznamOtcuKody)) {
                                if (!in_array($uzel["id"],  $seznamPolozekPod)) {
                                    $seznamPolozekPod[] = $uzel["id"];
                                }
                                if (!in_array($uzel["id"],  $seznamOtcuIds)) {
                                    $seznamOtcuIds[] = $uzel["id"];
                                }
                                if (!in_array($uzel["kod"],  $seznamOtcuKody)) {
                                    $seznamOtcuKody[] = $uzel["kod"];
                                }
                            }
                        } else {
                            if (in_array($uzel["otec"], $seznamOtcuIds)) {
                                if (!in_array($uzel["id"],  $seznamPolozekPod)) {
                                    $seznamPolozekPod[] = $uzel["id"];
                                }
                                if (!in_array($uzel["id"],  $seznamOtcuIds)) {
                                    $seznamOtcuIds[] = $uzel["id"];
                                }
                                if (!in_array($uzel["kod"],  $seznamOtcuKody)) {
                                    $seznamOtcuKody[] = $uzel["kod"];
                                }
                            }
                        }
                    } 
                }
                */

                //bdump($seznamPolozekPod);

                //seznam ceniku pod uzlem od, coz mi nepomuze
                //bdump($this->model->getSubtree("cenik", $idOd), "SUBTREE");

                //pokud cesta Od je soucasti cesty Do, tak KO, protoze presouvam pod sebe
                $nazevOd = "";
                $indexOd = 0;
                $nazevError = "";
                $indexError = 0;
                $idOdOK = [];
                foreach ($idOdPole AS $idOd) {
                    if (substr($poleIds[$idDo]['mojecesta'], 0, strlen($poleIds[$idOd]['mojecesta'])) == $poleIds[$idOd]['mojecesta']) {
                        //bdump(substr($poleIds[$idDo]['mojecesta'], 0, strlen($poleIds[$idOd]['mojecesta'])) . " " . $poleIds[$idOd]['mojecesta'], "KO");
                        $nazevError .= $indexError ? ", ".$poleIds[$idOd]["nazev"] : $poleIds[$idOd]["nazev"];
                        $indexError++;
                    } else {
                        //bdump(substr($poleIds[$idDo]['mojecesta'], 0, strlen($poleIds[$idOd]['mojecesta'])) . " " . $poleIds[$idOd]['mojecesta'], "OK");
                        $idOdOK[] = $idOd;
                        $nazevOd .= $indexOd ? ", ".$poleIds[$idOd]["nazev"] : $poleIds[$idOd]["nazev"];
                        $indexOd++;
                    }
                    
                }

                if ($indexError) {
                    $this->flashMessage("Nelze přesunout " . ($indexError > 1 ? "uzly " : "uzel ") . "$nazevError pod {$poleIds[$idDo]["nazev"]}.", "danger");
                }

                if (!empty($idOdOK)) {
                    //$result = $this->model->putNovyOtec($idOd, $idDo, $evidence);
                    //$this->flashMessage("Uzel stromu {$uzelOd["nazev"]} byl přesunut pod {$uzelDo["nazev"]}.", "success");
                    //neudelam hned, ale pridam potvrzovaci tlacitko (potvrzeni.latte)
                    $this->template->idOdPole = $idOdOK;
                    $this->template->idDo = $idDo;
                    $this->template->nazevOd = $nazevOd;
                    $this->template->nazevDo = $poleIds[$idDo]["nazev"];
                    $this->setView('potvrzeni');
                    //bdump($result);
                } else {
                    $this->flashMessage("Nic jsem nezměnil.", "info");
                }

            } else {
                $this->flashMessage("Musíte vybrat, co se má přesunout a kam", "danger");
            }
            
            //$this->redirect("this");

        }

        if(isset($_POST["potvrdit-submit"])) {
            //bdump($_POST);
            if (isset($_POST["idOd"]) && isset($_POST["idDo"])) {
                $evidence = "strom";
                $idOdPole = explode(",", $_POST["idOd"]);
                $idDo = $_POST["idDo"];
                $nazevOd = "";
                $indexOd = 0;
                $nazevError = "";
                $indexError = 0;
                //$idDoPuvodni = $idDo; //test chyb
                //$poleIds["A"]["nazev"] = "Chybná položka"; //test chyb
                foreach($idOdPole As $idOd) {
                    //$idDo = rand(0,1) ? $idDoPuvodni : "A"; //test chyb
                    try {
                        $result = $this->model->putNovyOtec($idOd, $idDo, $evidence);
                        $nazevOd .= $indexOd ? ", ".$poleIds[$idOd]["nazev"] : $poleIds[$idOd]["nazev"];
                        $indexOd++;
                    }catch(\Throwable $e){
                        //doslo na vyjimku, vypisu uzivateli zneni chyby
                        $this->flashMessage($e->getMessage(), "danger");
                        $nazevError .= $indexError ? ", ".$poleIds[$idOd]["nazev"] : $poleIds[$idOd]["nazev"];
                        $indexError++;
                    }
                }
                if ($indexError) {
                    $this->flashMessage("Nešlo přesunout " . ($indexError > 1 ? "uzly " : "uzel ") . "$nazevError pod {$poleIds[$idDo]["nazev"]}.", "danger");
                    if ($indexError < count($idOdPole)) {
                        $this->flashMessage("Přesunul jsem " . ($indexOd > 1 ? "uzly " : "uzel ") . "$nazevOd pod {$poleIds[$idDo]["nazev"]}.", "success");
                    }
                } else {
                    $this->flashMessage("Hotovo. Přesunul jsem " . ($indexOd > 1 ? "uzly " : "uzel ") . "$nazevOd pod {$poleIds[$idDo]["nazev"]}.", "success");
                //bdump($result);
                }
            } else {
                $this->flashMessage("Něco selhalo.", "danger");
            }
            $this->redirect("this");
        }
        if(isset($_POST["potvrdit-cancel"])) {
            $this->flashMessage("Nic jsem nezměnil.", "info");
        }

	}

    public function createComponentTestInfo(){
        //bdump($_SESSION);

        //Zobrazi textarea s dulezitymi hodnotami; v latte pak skryt pres {*control Test*}

        $poleUzlu = $this->poleUzlu;
        $textPoleUzlu = [];
        foreach ($poleUzlu as $cesta => $odpUzel) {
            $textUzlu = "id: ".$odpUzel["id"];
            //$textUzlu .= ", kod: ".$odpUzel["kod"];
            $textUzlu .= ", nazev: ".$odpUzel["nazev"];
            $textUzlu .= ", hladina: ".$odpUzel["hladina"];
            //$textUzlu .= ", poradi: ".$odpUzel["poradi"];
            $textUzlu .= ", cesta: ".$odpUzel["cesta"];
            $textUzlu .= ", mojecesta: ".$cesta;
            //$textUzlu .= ", otec: ".$odpUzel["otec"];
            $textUzlu .= ", hasChild: ".$odpUzel["hasChild"];
            $textPoleUzlu[] = $textUzlu;
        }
        

        $text = implode("\n", $textPoleUzlu);
        $form = new Form;
        $form->addTextArea('vypis')
                ->setDefaultValue($text)
                ->setHtmlAttribute('readonly')
                ->setHtmlAttribute('rows', 10)
                ->setHtmlAttribute('cols', 100);
                
        return $form;
    }

    // public function renderDefault(){
    //     try {
    //         //$this->template->doklady = $this->model->getPokladna(["kod", "titul", "jmeno", "prijmeni", "titulZa", "mobil", "email", "pravoZamykat", "addUser", "role"]);
    //     } catch (\Exception $e){
    //         $this->flashMessage($e->getMessage(), "danger");
    //     }
    // }

}