<?php

namespace App\Models;

use \Nette\Neon\Neon;
/**
 * Description of StatusModel
 *
 * @author garret
 */
final class FormModel {

    use \Nette\SmartObject;


    function __construct() {
        //$this->mail_config = $mail_config;
        //$this->flexibee = $flexibee;
    }

    public function odeslaniDotazu() {
        $_SESSION['autoscript'] = true;
        foreach (self::DOTAZY as $id => $nazev) {
            echo "$nazev";
            $this->getDotazCsv($id);
        }

        $this->OdeslaniMailem();
    }

    public function getDotazCsv($id) {
        $dotaz = $this->flexibee->getCsv('uzivatelsky-dotaz', $id, [
            'limit' => 0,
        ]);

        file_put_contents('../temp/' . self::DOTAZY[$id] . '.csv', $dotaz);
    }

    public function OdeslaniMailem() {
        $mail = new \Nette\Mail\Message();
        foreach (self::DOTAZY as $nazev) {
            $mail->addAttachment('../temp/' . $nazev . '.csv');
        }

        $mail->setFrom('Blackhole <blackhole@arit.cz>')
                ->addTo('vitnekolny@gmail.com')
                ->setSubject('Odselani dotazu mailem')
                ->setBody("Dobrý den,\nvaše objednávka byla přijata.");
        $mailer = new \Nette\Mail\SmtpMailer($this->mail_config);
        try {
            $mailer->send($mail);
            echo date('d.m.Y H:i:s') . ' - Email odeslan' . PHP_EOL;
        } catch (\Nette\Mail\SmtpException $ex) {
            echo date('d.m.Y H:i:s') . ' - Email se nepodarilo odeslat' . PHP_EOL;
            print_r($ex);
        }
    }

    public function ChybaOdeslaniMailem() {
        $mail = new \Nette\Mail\Message();
        $mail->addAttachment('../temp/dotaz.csv');
        $mail->setFrom('Blackhole <blackhole@arit.cz>')
                ->addTo('vitnekolny@gmail.com')
                ->setSubject('Odselani dotazu mailem')
                ->setBody("Soubor ja prazdny");
        $mailer = new \Nette\Mail\SmtpMailer($this->mail_config);
        try {
            $mailer->send($mail);
            echo date('d.m.Y H:i:s') . ' - Email odeslan' . PHP_EOL;
        } catch (Exception $ex) {
            echo date('d.m.Y H:i:s') . ' - Email se nepodarilo odeslat' . PHP_EOL;
            print_r($ex);
        }
    }

    public function uhradaNaProdejne($id, $datVyst) {
        $faktura = $this->getFakturaVydana($id, ['kod', 'datVyst', 'formaDopravy', 'sumCelkem', 'sumOsv', 'sumCelkSniz', 'sumCelkSniz2', 'sumCelkZakl', 'primUcet', 'varSym']);
        if (strpos($faktura[0]['formaDopravy'], 'ODBER_KORALKY') == false) {
            return ['success' => false, 'error' => 'Faktura není s odběrem na prodejně'];
        }
        // Vytvoření pohledávky
        $pohledavka = [
            'id' => 'ext:pohlkfakv:' . $id,
            'typDokl' => 'code:OSTATNIPOHL - ' . explode('_', $faktura[0]['formaDopravy'])[2],
            'datVyst' => $datVyst,
            'popis' => date('d.m.Y', strtotime($faktura[0]['datVyst'])) . ' - ' . $faktura[0]['kod'],
            'bezPolozek' => true,
            'sumOsv' => $faktura[0]['sumCelkem'],
            'protiUcet' => $faktura[0]['primUcet'],
            'varSym' => $faktura[0]['varSym']
        ];
        $resPohled = $this->createPohledavka($pohledavka);
        if ($resPohled['winstrom']['success'] !== 'true') {
            return ['success' => false, 'error' => 'Nepovedlo se vytvořit pohledávku - ' . $resPohled['winstrom']['results'][0]['errors'][0]['message']];
        }
        // Vytvoření vazby mezi fakturou a pohledávkou
        $pohledavka = [
            'id' => 'ext:pohlkfakv:' . $id,
            'uzivatelske-vazby' => [
                'uzivatelska-vazba' => [
                    'evidenceType' => 'faktura-vydana',
                    'object' => $id,
                    'vazbaTyp' => 'code:PLATBA KARTOU'
                ]
            ]
        ];
        $resVazba = $this->createPohledavka($pohledavka);
        if ($resVazba['winstrom']['success'] !== 'true') {
            return ['success' => false, 'error' => 'Nepovedlo se vytvořit vazbu - ' . $resPohled['winstrom']['results'][0]['errors'][0]['message']];
        }
        // Uhrazení faktury
        $res = $this->uhraditFakturu($id, $datVyst);
        if ($res['winstrom']['success'] !== 'true') {
            return ['success' => false, 'error' => 'Nepovedlo se uhradit fakturu - ' . $resPohled['winstrom']['results'][0]['errors'][0]['message']];
        } else {
            return ['success' => true, 'error' => 'Faktura byla ručně uhrazena'];
        }
    }

    /**
     * Get faktury vydané
     * @param type $id
     * @param type $detail
     * @return type
     */
    public function getFakturaVydana($id, $detail) {
        return $this->flexibee->get('faktura-vydana', [
                    'id' => $id
                        ], [
                    'detail' => $detail,
        ]);
    }

    /**
     * Vytvoření pohledávky
     * @param type $data
     * @return type
     */
    public function createPohledavka($data) {
        return $this->flexibee->put('pohledavka', [
                    'winstrom' => [
                        'pohledavka' => $data
                    ]
        ]);
    }

    /**
     * Ruční uhrazení faktury
     * @param type $id
     * @return type
     */
    public function uhraditFakturu($id, $datUhr) {
        return $this->flexibee->put('faktura-vydana', ['winstrom' => [
                        'faktura-vydana' => [
                            'id' => $id,
                            'stavUhrK' => 'stavUhr.uhrazenoRucne',
                            'datUhr' => $datUhr
                        ]
        ]]);
    }

    public function getKodDokladu(string $typDokladu, string $idDokladu) {
        $res = $this->flexibee->get($typDokladu, [
            'id' => $idDokladu], [
            'detail' => ['kod'],
        ]);
        try {
            $kodDokladu = $res[0]['kod'];
        } catch (Exception $e) {
            $kodDokladu = '';
        }

        return $kodDokladu;
    }

    /**
     * @param type $prijemka
     * @param type $sklad
     * @return array|null
     */
    public function prijemkaZmenaSkladu($prijemka, $sklad): ?array {
        $doklad = $this->flexibee->get('skladovy-pohyb', ['id' => $prijemka], [
                    'detail' => 'full',
                    'relations' => 'polozkyDokladu',
                        ]
                )[0];
        $doklad['sklad'] = $sklad;
        $doklad['polozkyDokladu@removeAll'] = 'true';
        foreach ($doklad['polozkyDokladu'] as $key => $polozka) {
            unset($doklad['polozkyDokladu'][$key]['id']);
            $doklad['polozkyDokladu'][$key]['sklad'] = $sklad;
            unset($doklad['polozkyDokladu'][$key]['cenaMjPoriz']);
        }

        $res = $this->flexibee->put('skladovy-pohyb', ['winstrom' => [
                'skladovy-pohyb' => $doklad
            ]
        ]);
        if ($res['winstrom']['success'] == 'true') {
            return ['success' => true, 'message' => 'Změna skladu u příjemky ' . $doklad['kod'] . ' a jejích položek - proběhla úspěšně'];
        } else {
            return ['success' => false, 'message' => 'Změna skladu u příjemky ' . $doklad['kod'] . ' a jejích položek - se nepodařila' . PHP_EOL
                . $res['winstrom']['results'][0]['errors'][0]['message']];
        }
    }

    public function prepareTiskEtiket($ids, $evidence) {
        return file_put_contents('temp_files/tisk' . $evidence . '.json', json_encode([
            'fb-session' => $_SESSION['fb-session'],
            'fb-company' => $_SESSION['fb-company'],
            'data' => [
                'ids' => $ids
            ]
                        ]
        ));
    }

    public function updateStatusTisk($status, $finished, $run, $evidence, $error = false, $link = null) {
        file_put_contents('temp_files/status-tisk-' . $evidence . '.json', json_encode([
            'running' => $run,
            'status' => $status,
            'count' => 4,
            'finished' => $finished,
            'link' => $link,
            'statusPercent' => round(($finished / 4) * 100),
                ])
        );
    }

    public function tiskEtiketObp($ids) {
        //$id = $this->getId('POP_ETIKETA_ESHOP');
        $objednavky = explode(',', $ids);
        // Vymazání položek poptávky
        $this->updateStatusTisk('Získávám data...', 0, true, 'obp');
        $polozky = array();
        foreach ($objednavky as $objednavka) {
            $polozky = array_merge($this->flexibee->get('uzivatelsky-dotaz', 57, ['id' => $objednavka, 'limit' => 0]), $polozky);
        }
        $this->updateStatusTisk('Připravuji položky...', 1, true, 'obp');
        
        $json = [
            'winstrom' => [
                        'poptavka-prijata' => [
                            'polozkyObchDokladu' => [],
                            'typDokl' => 'code:POP_ETIKETA_ESHOP'                    
                        ]
                    ]
        ];
        foreach ($polozky as $polozka) {
            $polJson = [
                'kod' => $polozka['obp'],
                'cenik' => $polozka['idcenik'],
                'nazev' => $polozka['nazev'],
                'nazevA' => $polozka['etiketa0'],
                'nazevB' => $polozka['etiketa2'],
                'typPolozkyK' => 'typPolozky.katalog',
                'mnozMj' => $polozka['mnoz'],
                'cenaMj' => $polozka['cenazakl'],
                'cenaMjNakup' => 0,
                'eanKod' => $polozka['ean'],
                'sklad' => 'code:' . $polozka['sklad']
            ];
            $json['winstrom']['poptavka-prijata']['polozkyObchDokladu'][] = $polJson;
        }
        
        $this->updateStatusTisk('Plním doklad daty...', 2, true, 'obp');
        $res = $this->flexibee->put('poptavka-prijata', $json);
        $id = $res['winstrom']['results'][0]['id'];
        file_put_contents('../log/tiskobpres.json', json_encode($res));
        $this->updateStatusTisk('Získávám pdf s etiketami...', 3, true, 'obp');
//        $pdf = $this->flexibee->getRaw('poptavka-prijata/52.pdf');
//        file_put_contents('temp_files/tisketiketobp.pdf', $pdf);
        //pdf to png funguje i na vicestrankovy
        $image = new \Imagick();
        $image->setResolution( 400, 400 );
        $image->readImage('https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);        
        $pdfNumPage = $image->getNumberImages();
        for ($i = 0; $i < $pdfNumPage; $i++){
            $image->resetIterator();
            $imageSer = $image->appendImages(true);
        }
        $imageSer->setImageFormat( "png" );
        //header('Content-Type: image/png');
        file_put_contents('temp_files/resultobp.png', $imageSer->getImageBlob());
        $this->updateStatusTisk('Hotovo', 4, false, 'skl', false, '/temp_files/resultobp.png');
		//die();
       //$this->updateStatusTisk('Hotovo', 4, false, 'skl', false, 'https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);
    }

    public function tiskEtiketObv($ids) {
        //$id = $this->getId('POP_ETIKETA_VYROBA');
        $objednavky = explode(',', $ids);
        // Vymazání položek poptávky
        $this->updateStatusTisk('Získávám data...', 0, true, 'obv');
        $polozky = array();
        foreach ($objednavky as $objednavka) {
            $polozky = array_merge($this->flexibee->get('uzivatelsky-dotaz', 58, ['id' => $objednavka, 'limit' => 0]), $polozky);
        }
        $this->updateStatusTisk('Připravuji položky...', 1, true, 'obv');
        $json = [
            'winstrom' => [
                'poptavka-prijata' => [
                    'polozkyObchDokladu' => [],
                    'typDokl' => 'code:POP_ETIKETA_VYROBA'
                ]
            ]
        ];
        foreach ($polozky as $polozka) {
            $polJson = [
                'nazev' => $polozka['nazev'],
                'nazevA' => $polozka['etiketa0'],
                'nazevB' => $polozka['etiketa2'],
                'oznaceni' => '',
                'typPolozkyK' => 'typPolozky.katalog',
//                'mnozMj' => $polozka['mnoz'], 
                'cenaMj' => $polozka['cenazakl'],
                'cenik' => $polozka['idcenik'],
            ];
            if ($polozka['skladove'] == 'true') {
                $polJson['sklad'] = 4;
            }
            $json['winstrom']['poptavka-prijata']['polozkyObchDokladu'][] = $polJson;
        }
        $this->updateStatusTisk('Plním doklad daty...', 2, true, 'obv');
        $res = $this->flexibee->put('poptavka-prijata', $json);
        $id = $res['winstrom']['results'][0]['id'];
        file_put_contents('../log/tiskobvres.json', json_encode($res));
        $this->updateStatusTisk('Získávám pdf s etiketami...', 3, true, 'obv');
//        $pdf = $this->flexibee->getRaw('poptavka-prijata/4886.pdf');
//        file_put_contents('temp_files/tisketiketobv.pdf', $pdf);
        //pdf to png funguje i na vicestrankovy
        $image = new \Imagick();
        $image->setResolution( 400, 400 );
        $image->readImage('https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);        
        $pdfNumPage = $image->getNumberImages();
        for ($i = 0; $i < $pdfNumPage; $i++){
            $image->resetIterator();
            $imageSer = $image->appendImages(true);
        }
        $imageSer->setImageFormat( "png" );
        //header('Content-Type: image/png');
        file_put_contents('temp_files/resultobv.png', $imageSer->getImageBlob());
        $this->updateStatusTisk('Hotovo', 4, false, 'skl', false, '/temp_files/resultobv.png');
		//die();
       //$this->updateStatusTisk('Hotovo', 4, false, 'skl', false, 'https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);
    }

    public function tiskEtiketSkl($ids) {
        //$id = $this->getId('POP_ETIKETA_PRIJEM');
        $objednavky = explode(',', $ids);
        // Vymazání položek poptávky
        $this->updateStatusTisk('Získávám data...', 0, true, 'skl');
        $polozky = array();
        foreach ($objednavky as $objednavka) {
            $polozky = array_merge($this->flexibee->get('uzivatelsky-dotaz', 70, ['id' => $objednavka, 'limit' => 0]), $polozky);
        }
        $this->updateStatusTisk('Připravuji položky...', 1, true, 'skl');
        
        $json = [
            'winstrom' => [
                'poptavka-prijata' => [
                    'polozkyObchDokladu' => [],
                    'typDokl' => 'code:POP_ETIKETA_PRIJEM'
                ]
            ]
        ];
        foreach ($polozky as $polozka) {
            for ($i = 1; $i <= $polozka['mnoz']; $i++) {
                $polJson = [
                    'kod' => $polozka['obp'],
                    'nazev' => $polozka['nazev'],
                    'nazevA' => $polozka['etiketa0'],
                    'nazevB' => $polozka['etiketa2'],
                    'typPolozkyK' => 'typPolozky.katalog',
                    'mnozMj' => 1,
                    'cenaMj' => $polozka['cenazakl'],
                    'cenik' => $polozka['idcenik'],
                ];
                if ($polozka['skladove'] == 'true') {
                    $polJson['sklad'] = 4;
                }
                $json['winstrom']['poptavka-prijata']['polozkyObchDokladu'][] = $polJson;
            }
        }
        $this->updateStatusTisk('Plním doklad daty...', 2, true, 'skl');
        $res = $this->flexibee->put('poptavka-prijata', $json);
        $id = $res['winstrom']['results'][0]['id'];
        file_put_contents('../log/tisksklres.json', json_encode($res));
        $this->updateStatusTisk('Získávám pdf s etiketami...', 3, true, 'skl');
//        $pdf = $this->flexibee->getRaw('poptavka-prijata/54.pdf');
//        file_put_contents('temp_files/tisketiketskl.pdf', $pdf);
        //pdf to png funguje i na vicestrankovy
        $image = new \Imagick();
        $image->setResolution( 400, 400 );
        $image->readImage('https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);
        $pdfNumPage = $image->getNumberImages();
        for ($i = 0; $i < $pdfNumPage; $i++){
            $image->resetIterator();
            //$imageSer = $image->appendImages(true);
            $imageName = 'temp_files/etikety/imageskl_' . $i . '.png';
            $image->setImageFormat( "png" );
            file_put_contents($imageName, $image->getImageBlob(), FILE_APPEND);
        }
        //$imageSer->setImageFormat( "png" );
        //header('Content-Type: image/png');
        //file_put_contents('temp_files/resultskl.png', $imageSer->getImageBlob());
        $this->updateStatusTisk('Hotovo', 4, false, 'skl', false, '/temp_files/resultskl.png');
		//die();
       //$this->updateStatusTisk('Hotovo', 4, false, 'skl', false, 'https://koralky.arit.cz:5448/c/koralky_cz_ostra1/poptavka-prijata/' . $id . '.pdf?authSessionId=' . $_SESSION['fb-session']);
    }

    public function getDokladNaMazani($evidence) {
        $doklady = $this->flexibee->get($evidence,[], 
            [
            'detail' => ['id','lastUpdate', 'typDokl'],
            'limit' => 0,
            'order' => 'lastUpdate@d'
            ]
        );

        //mazani dolkadu bude se volat cronem
        //$datZmeny = $doklady[0]['lastUpdate'];
        $vymazane = [];
        foreach ($doklady as $doklad) {
            $typDokl = explode(":", $doklad['typDokl']);
            if ($typDokl[1] == 'POP_ETIKETA_PRIJEM' || $typDokl[1] == 'POP_ETIKETA_ESHOP' || $typDokl[1] == 'POP_ETIKETA_VYROBA') {
                $this->flexibee->put($evidence,
                ['winstrom' =>
                   [$evidence => 
                      [
                         'id' => $doklad['id'],
                         '@action' => 'delete'
                      ]
                   ]
                ]
             );
             $vymazane[] = $doklad['id'];
            }
        }
        $vymazane = implode(",", $vymazane);
        echo(date("d.m.Y H:i:s") . " " . "Byly vymazaný poptávky přijaté s id: " . $vymazane . PHP_EOL);
    }

    /**
     * @param string $filename
     * @param string $name
     * @return array|null
     */
    public function realizaceObjednavky($filename, $datumSplatnosti, $datumVystaveni, $cisDosle, $varSym): ?array {
        $objIds = explode(',', $_SESSION['obj-ids']);
        $indexy = [];
        $objednavky = $this->flexibee->get('objednavka-vydana', [
            'id' => [
                '@in' => $objIds
            ]
                ], [
            'relations' => 'polozkyDokladu,vazby',
            'detail' => ['id', 'kod', 'mena', 'polozkyDokladu(id,cenik,mnozMj,mnozMjReal,cisRad,szbDph,sklad)', 'vazby(typVazbyK,b)'],
            'order' => 'datVyst@a',
            'limit' => 0
        ]);
        if (empty($objednavky)) {
            return ['success' => false, 'message' => 'Objednávky s id ' . $_SESSION['obj-ids'] . ' nenalezeny'];
        }

        $filetype = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($filetype);
        $objReader->setReadDataOnly(true);

        $objSpreadsheet = $objReader->load($filename);
        $objWorksheet = $objSpreadsheet->getSheet(0);

        $indexy = $this->prepareIndex($indexy, $objWorksheet);
        $polozky = [];
        for ($r = 2; $r <= $objWorksheet->getHighestRow() &&
                !empty($objWorksheet->getCell($indexy['kod'] . $r)->getValue()); $r++) {
            $polozky[] = [
                'kod' => trim($objWorksheet->getCell($indexy['kod'] . $r)->getValue()),
                'mnozstvi' => (float) trim($objWorksheet->getCell($indexy['mnozstvi'] . $r)->getValue()),
                'cena' => (float) trim($objWorksheet->getCell($indexy['cena'] . $r)->getValue()),
                'used' => false
            ];
        }
        $errors = [
            'notall' => [],
            'nothing' => []
        ];
        //secteni řádků položek
        $done = [];
        foreach ($polozky as $key => $polozka) {
            foreach ($polozky as $key1 => $pol) {
                if ($polozka['kod'] === $pol['kod'] && $key !== $key1 && !in_array($pol['kod'], $done)) {
                    $polozky[$key]['mnozstvi'] = $polozky[$key]['mnozstvi'] + $pol['mnozstvi'];
                    unset($polozky[$key1]);
                    $done[] = $pol['kod'];
                }
            }
        }
        $extId = strtotime('now');
        // Procházení objednávek
        foreach ($objednavky as $objednavka) {
            $polozkyRealizace = [];
            // Procházení položek objednávky
            foreach ($objednavka['polozkyDokladu'] as $polDokladu) {
                $zbyva = $polDokladu['mnozMj'] - $polDokladu['mnozMjReal'];
                // Procházení položek z XLS
                foreach ($polozky as $key => $polozka) {
                    if ($polozka['mnozstvi'] > 0) {
                        if (substr($polDokladu['cenik'], 5) === $polozka['kod']) {
                            $polozky[$key]['zbyva'] = $zbyva;
                            $sklad = $this->getSkladId($polDokladu['sklad@ref']);
                            $polozky[$key]['sklad'] = $sklad;
                            $pocet = $zbyva > $polozka['mnozstvi'] ? $polozka['mnozstvi'] : $zbyva;
                            $polozky[$key]['pocet'] = $pocet;
                            $polozky[$key]['mnoz'] = $polDokladu['mnozMj'];
                            $polozky[$key]['real'] = $polDokladu['mnozMjReal'];

                            if ($pocet > 0.0) {
                                $polozkyRealizace[] = [
                                    'cisRad' => $polDokladu['cisRad'],
                                    'mj' => $pocet,
                                    'cenaMj' => $polozka['cena'],
                                    'szbDph' => $polDokladu['szbDph'],
                                    'kod' => substr($polDokladu['cenik'], 5),
                                    'sklad' => $sklad,
                                ];
                                $polozky[$key]['mnozstvi'] = $polozky[$key]['mnozstvi'] - $pocet;
                                $polozky[$key]['used'] = true;
                            }
                        }
                    }
                }
            }
            $realizace = [
                'winstrom' => [
                    'objednavka-vydana' => [
                        'id' => $objednavka['id'],
                        'realizaceObj@type' => 'faktura-prijata',
                        'realizaceObj' => [
                            'id' => 'ext:id:' . $extId,
                            'cisDosle' => $cisDosle,
                            'typDokl' => 9,
                            'datSplat' => $datumSplatnosti,
                            'mena' => $objednavka['mena'],
                            'datVyst' => $datumVystaveni,
                            'varSym' => $varSym,
                            'polozkyObchDokladu' => $polozkyRealizace
                        ]
                    ]
                ]
            ];
            $res = $this->flexibee->put('objednavka-vydana', $realizace);
        }
        if ($res['winstrom']['success'] == 'true') {
            $faktura = $this->flexibee->get('faktura-prijata', [
                        'id' => 'ext:id:' . $extId,
                            ], [
                        'relations' => 'polozkyDokladu',
                        'detail' => ['id', 'polozkyDokladu(id,cenik,sklad,typPolozkyK)'],
                        'order' => 'datVyst@a'
                            ]
                    )[0] ?? [];
            $zbylePolozky = [];
            foreach ($polozky as $polozka) {
                if ($polozka['mnozstvi'] > 0) {
                    $polozka['szbDph'] = $this->flexibee->get('cenik', ['kod' => $polozka['kod']], ['detail' => ['szbDph']]);
                    $zbylePolozky[] = [
                        'mnozMj' => $polozka['mnozstvi'],
                        'cenaMj' => $polozka['cena'],
                        'szbDph' => $polozka['szbDph'],
                        'cenik' => 'code:' . $polozka['kod'],
                        'sklad' => (($polozka['sklad'] !== 12 && $polozka['sklad'] !== 7 ) ? 9 : $polozka['sklad']),
                    ];
                }
            }
            $newPolozky = [];
            foreach ($faktura['polozkyDokladu'] as $polozka) {
                foreach ($polozky as $polozkaxls) {
                    if (substr($polozka['cenik'], 5) == $polozkaxls['kod']) {
                        if ($polozka['typPolozkyK'] !== 'typPolozky.text') {
                            $sklad = $this->getSkladId($polozka['sklad@ref']);
                            if ($sklad !== 12 && $sklad !== 7) {
                                $newPolozky[] = [
                                    'id' => $polozka['id'],
                                    'sklad' => 9,
                                    'cenaMj' => $polozkaxls['cena']
                                ];
                            } else {
                                $newPolozky[] = [
                                    'id' => $polozka['id'],
                                    'cenaMj' => $polozkaxls['cena']
                                ];
                            }
                        }
                    }
                }
            }
            $res = $this->flexibee->put('faktura-prijata', [
                'winstrom' => [
                    'faktura-prijata' => [
                        'id' => $faktura['id'],
                        'varSym' => $varSym,
                        'polozkyDokladu' => array_merge($newPolozky, $zbylePolozky)
                    ]
                ]
            ]);
        } else {
            return [
                'success' => false,
                'message' => 'Chyba - ' . $res['winstrom']['results'][0]['errors'][0]['message'] ?? 'Neznámá chyba'
            ];
        }

        if (empty($errors['notall']) && empty($errors['nothing'])) {
            return [
                'success' => true,
                'message' => 'Vše proběhlo v pořádku'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Uspokojení objednávky proběhlo, ale některé položky nebyly vyřízeny kompletně.',
                'log' => $errors
            ];
        }
    }

    public function groupByValue($array, $index) {
        $results = array();
        foreach ($array as $item) {
            $sklad = ($this->getSkladId($item['sklad@ref']) !== '12' && $this->getSkladId($item['sklad@ref']) !== '7') ? '9' : $this->getSkladId($item['sklad@ref']);
            $results[$sklad][] = $item;
        }
        return $results;
    }

    public function getSkladId($sklad) {
        $stage1 = explode('/', $sklad);
        $stage2 = explode('.', end($stage1));
        return (int) reset($stage2);
    }

    public function preparePrevodNaProdejnu($prodejna) {
        return file_put_contents('temp_files/prevod.json', json_encode([
            'fb-session' => $_SESSION['fb-session'],
            'fb-company' => $_SESSION['fb-company'],
            'data' => [
                'prodejna' => $prodejna
            ]
                        ]
        ));
    }

    public function updateStatusPrevod($status, $finished, $run, $error = false) {
        file_put_contents('temp_files/status-prevod.json', json_encode([
            'running' => true,
            'status' => $status,
            'count' => 6,
            'finished' => $finished,
            'statusPercent' => round(($finished / 6) * 100),
                ])
        );
    }

    public function prevodNaProdejnu($prodejna) {
        $this->updateStatusPrevod('Získávání dat...', 0, true);
        $dotazResult = $this->flexibee->get('uzivatelsky-dotaz', 60, ['LOC' => $prodejna, 'limit' => 0]);
        $this->updateStatusPrevod('Získávání xls...', 1, true);
        $xls60 = $this->flexibee->getRaw('uzivatelsky-dotaz/60/call.xls?LOC=' . $prodejna . '&limit=0');
        $this->updateStatusPrevod('Získávání xls...', 2, true);
        $xls61 = $this->flexibee->getRaw('uzivatelsky-dotaz/61/call.xls?LOC=' . $prodejna . '&limit=0');
        $this->updateStatusPrevod('Příprava dat...', 3, true);
        $polozky = [];
        foreach ($dotazResult as $key => $result) {
            if ($result['doplnit'] == '0.0' || (int) $result['doplnit'] <= 0 || (int) $result['zasobactr'] <= 0) {
                unset($dotazResult[$key]);
            } else {
                $polozka = [];
                $polozka['cenik'] = 'code:' . $dotazResult[$key]['kod'];
                $polozka['stredisko'] = 'code:' . $dotazResult[$key]['stredisko'];
                $polozka['mnozMj'] = ($result['doplnit'] <= $result['zasobactr'] ? $result['doplnit'] : $result['zasobactr']);
                $polozka['sklad'] = 4;
                $polozka['source'] = $result['internirazeni'];
                $polozky[] = $polozka;
            }
        }
        $prodejnaKod = $this->flexibee->get('sklad', ['id' => $prodejna], ['detail' => ['kod', 'nazev']]);
        $typDokladu = $this->flexibee->get('typ-skladovy-pohyb', ['kod' => ['@like similar' => 'CTR-' . explode('_', $prodejnaKod[0]['kod'])[1]]])[0]['kod'];
        $this->updateStatusPrevod('Získávání typu pohybu...', 4, true);
        $vydejka = ['winstrom' => [
                'skladovy-pohyb' => [
                    'typDokl' => 'code:' . $typDokladu,
                    'typPohybuK' => 'typPohybu.vydej',
                    'sklad' => 4,
                    'polozkyDokladu' => $polozky,
                ]
        ]];

        $this->updateStatusPrevod('Vytváření výdejky...', 5, true);
        $res = $this->flexibee->put('skladovy-pohyb', $vydejka);



        if ($res['winstrom']['success'] == 'true') {
            $resPriloha1 = $this->flexibee->put('skladovy-pohyb', ['winstrom' => [
                    'skladovy-pohyb' => [
                        'id' => $res['winstrom']['results'][0]['id'],
                        'prilohy' => [
                            'priloha' => [
                                'nazSoub' => 'Rozsirena data pro nakupciho.xls',
                                'typK' => 'typPrilohy.ostatni',
                                'contentType' => 'application/xls',
                                'IdDoklSklad' => $res['winstrom']['results'][0]['id'],
                                'content@encoding' => 'base64',
                                'content' => base64_encode($xls61),
                            ],
                        ]
                    ]
            ]]);
            $resPriloha2 = $this->flexibee->put('skladovy-pohyb', ['winstrom' => [
                    'skladovy-pohyb' => [
                        'id' => $res['winstrom']['results'][0]['id'],
                        'prilohy' => [
                            'priloha' => [
                                'nazSoub' => 'Zakladni data pro logistika.xls',
                                'typK' => 'typPrilohy.ostatni',
                                'contentType' => 'application/xls',
                                'IdDoklSklad' => $res['winstrom']['results'][0]['id'],
                                'content@encoding' => 'base64',
                                'content' => base64_encode($xls60),
                            ]
                        ]
                    ]
            ]]);
            $this->updateStatusPrevod('Hotovo', 6, false);
        } else {
            $this->updateStatusPrevod('Výdejku pro prodejnu ' . $prodejnaKod[0]['nazev'] . ' se nepodařilo vytvořit - ' . $res['winstrom']['results'][0]['errors'][0]['message'], 0, false, true);
        }
    }

    /**
     * @param string $ids
     * @return array|null
     */
    public function prerazeniPolozekObjednavky(string $ids): ?array {
        $objsId = explode(',', $ids);
        $results = [];
        foreach ($objsId as $obj) {
            if (!empty($obj)) {
                $polozky = $this->flexibee->get('uzivatelsky-dotaz', 11, ['id' => $obj, 'limit' => 0]);
                $json = [
                    'winstrom' => [
                        'objednavka-prijata' => [
                            'id' => $obj,
                            'polozkyObchDokladu' => []
                        ]
                    ]
                ];
                foreach ($polozky as $polozka) {
                    $json['winstrom']['objednavka-prijata']['polozkyObchDokladu'][] = [
                        'id' => $polozka['idpolobch'],
                        'cisRad' => $polozka['poradi'],
                        'source' => $polozka['umisteni'],
                        'kod' => $polozka['oznaceni'],
                    ];
                }
                $res = $this->flexibee->put('objednavka-prijata', $json);
                if ($res['winstrom']['success'] == 'true') {
                    try {
                        $idObjednavky = $res['winstrom']['results'][0]['id'];
                    } catch (Exception $e) {
                        $idObjednavky = '';
                    }
                    $objednavka = $this->flexibee->get('objednavka-prijata', [
                        'id' => $idObjednavky
                            ], [
                        'detail' => ['kod', 'typDokl', 'vazby(b)'],
                        'relations' => 'vazby'
                    ]);
                    $typDokl = $this->flexibee->get('typ-objednavky-prijate', ['id' => $objednavka[0]['typDokl']], ['detail' => ['poznam']]);
                    if (strpos($typDokl[0]['poznam'], 'stitek:UHRAZENO') !== false) {
                        $this->flexibee->put('objednavka-prijata', [
                            'winstrom' => [
                                'objednavka-prijata' => [
                                    'id' => $idObjednavky,
                                    'stitky' => 'UHRAZENO'
                                ]
                            ]
                                ]
                        );
                    }
                    $existsFak = false;
                    foreach ($objednavka[0]['vazby'] as $vazba) {
                        if (explode('/', $vazba['b@ref'])[3] == 'faktura-vydana') {
                            $existsFak = true;
                        }
                    }
                    if ((strpos($objednavka[0]['typDokl'], 'code:UCET') !== false) && $existsFak == false) {

                        $this->flexibee->put('objednavka-prijata', [
                            'winstrom' => [
                                'objednavka-prijata' => [
                                    'id' => $idObjednavky,
                                    'tvorbaZalohy' => [
                                        'typDokl' => 'code:ZÁLOHA',
                                        'procent' => 100
                                    ]
                                ]
                            ]
                                ]
                        );
                    }
                    $results[] = 'Objednávka č. ' . $objednavka[0]['kod'] . ' byla seřazena dle umístění na skladu';
                } else {
                    $results[] = 'Objednávka s id ' . $idObjednávky . ' se nezdařila seřadit. - ' . $res['winstrom']['results'][0]['errors'][0]['message'];
                }
            }
        }
        return $results;
    }

    /**
     * @param array $indexy
     * @param type $objWorksheet
     * @return array
     */
    public function prepareIndex(array $indexy, $objWorksheet): array {
        for ($c = 'A'; $c <= 'Z'; $c++) {
            if (trim($objWorksheet->getCell($c . '1')->getValue()) == 'kód položky') {
                $indexy['kod'] = $c;
            }
            if (trim($objWorksheet->getCell($c . '1')->getValue()) == 'množství') {
                $indexy['mnozstvi'] = $c;
            }
            if (trim($objWorksheet->getCell($c . '1')->getValue()) == 'cena bez dph') {
                $indexy['cena'] = $c;
            }
        }
        return $indexy;
    }

    /**
     * @return array|null
     */
    public function getProdejny(): ?array {
        $sklady = $this->flexibee->get('sklad', ['nazev' => ['@like' => 'Prodejna']], ['detail' => ['id', 'nazev']]);
        $prodejny = [];
        foreach ($sklady as $key => $sklad) {
            $prodejny[$sklad['id']] = $sklad['nazev'];
        }
        return $prodejny;
    }

    public function prepareDataCenik($filename) {
        if (file_exists($filename)) {
            copy($filename, "temp_files/xlsx_temp.xlsx");
        }
        $filetype = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($filetype);
        $objReader->setReadDataOnly(true);
        $objSpreadsheet = $objReader->load($filename);
        $objWorksheet = $objSpreadsheet->getSheet(0);

        $schema = $this->getSchema();
        //bdump($schema);
        $indexy = $this->getCenikColumns($schema, $objWorksheet);

        return file_put_contents('temp_files/cenik_temp.json', json_encode(['fb-session' => "",
            'fb-company' => "",
            'data' => ($this->getDataCenikFromXls($indexy, $objWorksheet, $schema))]));
    }

    public function spravaCeniku($data) {
        set_time_limit(20000);
        ini_set('memory_limit', '2000M');
        //bdump($data);
        $count = count($data);
        $finished = 0;
        $this->updateStatus(true, $count, $finished);
        $controls = $this->getControl();
        $errorsToXls = false;
        foreach ($controls as $control) {
            if ($control['job'] == 'errorsToXls' && $control['execute'] === true) {
                $errorsToXls = true;
            }
        }
        $errors = [];
        $results = [];
        foreach ($data as $polozka) {
            //bdump($polozka);
            /*
            $exist = $this->flexibee->get('cenik', ['kod' => $polozka['cenik']['kod']], [
                'relations' => ['dodavatele','odberatele'],
                'detail' => ['dodavatele(id)','odberatele(id)']
            ]);
            */
            $exist = [];
            file_put_contents('../log/log.log', json_encode($polozka));
            if (!empty($exist)) {
                $polozka['cenik']['id'] = 'code:' . $polozka['cenik']['kod'];
                unset($polozka['cenik']['kod']);
            }
            $json = ['cenik' => $polozka['cenik']];
            //dodavatele
            //$polozka['dodavatel']['primarni'] = true;
            if (isset($polozka['dodavatel']['kodIndi']) && strlen($polozka['dodavatel']['kodIndi']) > 30) {
                $polozka['dodavatel']['poznam'] = $polozka['dodavatel']['kodIndi'];
                $polozka['dodavatel']['kodIndi'] = substr($polozka['dodavatel']['kodIndi'], 0, 30);
            }
            if (!empty($polozka['dodavatel'])) {
                if (isset($exist[0]['dodavatele'][0]['id'])) {
                    //$polozka['dodavatel']['id'] = $exist[0]['dodavatele'][0]['id'];
                }
                $json['cenik']['dodavatele']['dodavatel'] = $polozka['dodavatel'];
            }
            if (!empty($polozka['odberatel'][1])) {
                $json['cenik']['odberatele'][] = $polozka['odberatel'][1];
            }
            if (!empty($polozka['odberatel'][2])) {
                $json['cenik']['odberatele'][] = $polozka['odberatel'][2];
            }
            /*
            //odberatele
            if (isset($exist[0]['odberatele'][0]['id'])) {
                $polozka['odberatel']['id'] = $exist[0]['odberatele'][0]['id'];
            }
            if (!empty($polozka['odberatel'])) {
                $json['cenik']['odberatele']['odberatel'] = $polozka['odberatel'];
            }
            */
            //bdump($polozka);
            //bdump($json);
            /*
            $skladoveKarty = [];
            if (isset($polozka['skladova-karta']['sklady'])) {
                foreach ($polozka['skladova-karta']['sklady'] as $sklad) {
                    if ($sklad == '9') {
                        $karta = [
                            'cenik' => !empty($exist) ? 'code:' . $polozka['cenik']['id'] : $polozka['cenik']['kod'],
                            'sklad' => $sklad,
                            'ucetObdobi' => 'code:' . date('Y')
                        ];
                    } else if ($sklad == '4') {
                        $karta = [
                            'cenik' => !empty($exist) ? 'code:' . $polozka['cenik']['id'] : $polozka['cenik']['kod'],
                            'sklad' => $sklad,
                            'ucetObdobi' => 'code:' . date('Y'),
                        ];
                        if (isset($polozka['skladova-karta']['minMjs'][$sklad])) {
                            $karta['minMJ'] = (float) $polozka['skladova-karta']['minMjs'][$sklad];
                        }
                    } else {
                        $karta = [
                            'cenik' => !empty($exist) ? 'code:' . $polozka['cenik']['id'] : $polozka['cenik']['kod'],
                            'sklad' => $sklad,
                            'ucetObdobi' => 'code:' . date('Y'),
                        ];
                        if (isset($polozka['skladova-karta']['minMjs'][$sklad])) {
                            $karta['minMJ'] = (float) $polozka['skladova-karta']['minMjs'][$sklad];
                        }
                    }
                    $skladoveKarty[] = $karta;
                }
                $json['cenik']['sklad-karty'] = $skladoveKarty;
            }
            */

            /*
            $res = $this->flexibee->put('cenik', ['winstrom' => $json]);
            //bdump($json, "JSON");
            if ($res['winstrom']['success'] == 'false') {
                if (isset($res['winstrom']['message'])) {
                    $results[] = $res['winstrom']['message'];
                }
                $results[] = 'Položku - ' . (!empty($exist) ?
                        substr($polozka['cenik']['id'], 5) . ' se nepodařilo aktualizovat - ' :
                        $polozka['cenik']['kod'] . ' se nepodařilo založit - ') . $res['winstrom']['results'][0]['errors'][0]['message'];
                $errors[$polozka['radek']] = 'Položku - ' . (!empty($exist) ?
                        substr($polozka['cenik']['id'], 5) . ' se nepodařilo aktualizovat - ' :
                        $polozka['cenik']['kod'] . ' se nepodařilo založit - ') . $res['winstrom']['results'][0]['errors'][0]['message'];
            } else {
                $results[] = !empty($exist) ?
                        'Položka - ' . (!empty($exist) ? substr($polozka['cenik']['id'], 5) : $polozka['cenik']['kod']) . ' byla aktualizována.' :
                        'Položka - ' . (!empty($exist) ? substr($polozka['cenik']['id'], 5) : $polozka['cenik']['kod']) . ' byla vytvořena.';
                if (isset($polozka['cenik']['zatrid'])) {
                    $existZatridId = $this->flexibee->get('strom', ['nazev' => $polozka['cenik']['zatrid']], [
                        'detail' => ['id']
                    ]);
                    //bdump($existZatridId);
                    if (!empty($existZatridId)) {
                        //"kód" uzlu stromu převedu na id
                        $polozka['cenik']['zatrid'] = $existZatridId[0]['id'];
                    }
                    $existZaznamId = $this->flexibee->get('strom-cenik', ['idZaznamu' => $res['winstrom']['results'][0]['id']], [
                        'detail' => ['id']
                    ]);
                    //bdump($existZaznamId);
                    if (!empty($existZaznamId)) {
                        //vazba na uzel uz existuje, aktualizuj
                        $resStrom = $this->flexibee->put('strom-cenik', ['winstrom' => [
                            'strom-cenik' => [
                                'id' => $existZaznamId[0]['id'],
                                'idZaznamu' => $res['winstrom']['results'][0]['id'],
                                'uzel' => '' . $polozka['cenik']['zatrid']
                                //původní zápis s ect:C:
                                //'uzel' => 'ext:C:' . $polozka['cenik']['zatrid']
                            ]
                        ]]);
                    } else {
                        $resStrom = $this->flexibee->put('strom-cenik', ['winstrom' => [
                            'strom-cenik' => [
                                'idZaznamu' => $res['winstrom']['results'][0]['id'],
                                'uzel' => '' . $polozka['cenik']['zatrid']
                                //původní zápis s ect:C:
                                //'uzel' => 'ext:C:' . $polozka['cenik']['zatrid']
                            ]
                        ]]);
                    }
                    if ($resStrom['winstrom']['success'] == 'true') {
                        $results[] = 'Položku - ' . (!empty($exist) ?
                                substr($polozka['cenik']['id'], 5) :
                                $polozka['cenik']['kod']) . ' se podařilo přidat do stromu';
                    } else {
                        $results[] = 'Položku - ' . (!empty($exist) ?
                                substr($polozka['cenik']['id'], 5) :
                                $polozka['cenik']['kod']) . ' se nepodařilo přidat do stromu - ' . $resStrom['winstrom']['results'][0]['errors'][0]['message'];
                        if (array_key_exists($polozka['radek'], $errors)) {
                            $errors[$polozka['radek']] .= ", " . 'Položku - ' . (!empty($exist) ?
                            substr($polozka['cenik']['id'], 5) :
                            $polozka['cenik']['kod']) . ' se nepodařilo přidat do stromu - ' . $resStrom['winstrom']['results'][0]['errors'][0]['message'];
                        } else {
                            $errors[$polozka['radek']] = 'Položku - ' . (!empty($exist) ?
                            substr($polozka['cenik']['id'], 5) :
                            $polozka['cenik']['kod']) . ' se nepodařilo přidat do stromu - ' . $resStrom['winstrom']['results'][0]['errors'][0]['message'];
                        }
                    }
                }
                
                
            }
            */
            $results[] = 'Položku - ' . (!empty($exist) ?
                        substr($polozka['cenik']['id'], 5) . ' se nepodařilo aktualizovat - ' :
                        $polozka['cenik']['kod'] . ' se nepodařilo založit - ') ;
            $errors[$polozka['radek']] = 'Položku - ' . (!empty($exist) ?
                        substr($polozka['cenik']['id'], 5) . ' se nepodařilo aktualizovat - ' :
                        $polozka['cenik']['kod'] . ' se nepodařilo založit - ') ;
            // pro zpomalení
            for ($i = 0; $i < 100000; $i++) {
                $a = 1;
            }
            $finished++;
            $this->updateStatus(true, $count, $finished);
        }
        $this->updateStatus(false, $count, $finished);
        file_put_contents('temp_files/result.log', implode(PHP_EOL, $results));

        if ($errorsToXls) {
            $filename = "temp_files/xlsx_temp.xlsx";
            $fileErrorsToXls = "temp_files/chyby.xlsx";
            $filetype = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
            $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($filetype);
            $objReader->setReadDataOnly(true);
            $objSpreadsheet = $objReader->load($filename);
            $objWorksheet = $objSpreadsheet->getSheet(0);

            $schema = $this->getSchema();
            $indexy = $this->getCenikColumns($schema, $objWorksheet);
            $this->exportXls($objWorksheet, $schema, $indexy, $errors, $fileErrorsToXls);
        }

    }

    public function getDataCenikFromXls($indexy, $objWorksheet, $schema) {
        $controls = $this->getControl();
        $checkDuplicity = false;
        foreach ($controls as $control) {
            if ($control['job'] == 'checkDuplicity' && $control['execute'] === true) {
                $checkDuplicity = true;
            }
        }
        $kody = [];
        $duplicity = [];
        $polozky = [];
        for ($r = 7; !empty(trim($objWorksheet->getCell($indexy['C1'] . $r)->getValue() ?? '')); $r++) {
            //$polozka = ['cenik' => [], 'dodavatel' => [], 'odberatel' => [], 'skladova-karta' => [], 'atribut' => []];
            $polozka = ['cenik' => [], 'dodavatel' => [], 'odberatel' => [], 'skladova-karta' => []];
            //$polozka['cenik']['skladove'] = false;
            //$polozka['cenik']['typCenyK'] = 'typCeny.bezDph'; //bere se z cenik.neon
            //$polozka['cenik']['desetinMj'] = 4; //bere se z cenik.neon
            $polozka['radek'] = $r; //pro export do excelu
            foreach ($schema as $item) {
                //misto zahardcodovanych nahore je brano v cenik.neon jako polozky s fixed value
                if (!$item['xls'] && $item['fixedValue']) {
                    $polozka[$item['evidence']][$item['label']] = $item['dataType'] === 'relation' ? 'code:' . strtoupper($item['value']) : $item['value'];
                }
                /*
                // pro kontrolu jak se nacita boolean, z Libreoffice obcas blbne NEPRAVDA (nacte se jako "")
                if ($item['xls'] && isset($indexy[$item['columnName']]) && $item['dataType'] === 'boolean') {
                    bdump("Radek: " . $r . ", sloupec: " . $indexy[$item['columnName']] . ", hodnota: " . $objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue());
                }
                */
                if ($item['xls'] && isset($indexy[$item['columnName']]) && (!empty(trim($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue() ?? '')) ||
                    trim($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue() ?? '') === '0')) {

                    //trim($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue()) === '0' || $item['evidence'] == 'atribut'
                    $value = trim($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue());

                    //oprava booleanu
                    if ($item['dataType'] === 'boolean' ) {
                        if ($value === "1" || $value === "true" || $value === "ANO" || $value === "=TRUE()") {
                            $value = true;
                        } elseif ($value === "0" || $value === "false" || $value === "NE" || $value === "=FALSE()") {
                            $value = false;
                        }
                    } 

                    /*
                    if ($item['label'] === 'typZasobyK') {
                        $polozka[$item['evidence']][$item['label']] = $item['values'][$value];
                    } elseif ($item['label'] === 'skupZboz') {
                        $polozka[$item['evidence']][$item['label']] = 'code:' . $item['values'][$value];
                    } elseif ($item['label'] === 'typDphK') {
                        $polozka[$item['evidence']][$item['label']] = $item['values'][(int) $value];
                    } elseif ($item['label'] === 'sklad') {
                        $kod = $value;
                        if (strlen($kod) === 8) {
                            $sklady = [];
                            for ($i = 0; $i <= 7; $i++) {
                                if ($i == 0) {
                                    if ($kod[$i] === '1') {
                                        foreach (explode(',', $item['values'][$i]) as $sklad) {
                                            $sklady[] = $sklad;
                                        }
                                    }
                                } else {
                                    if ($kod[$i] === '1') {
                                        $sklady[] = $item['values'][$i];
                                    }
                                }
                            }
                            $polozka[$item['evidence']]['sklady'] = $sklady;
                        }
                    } elseif ($item['label'] === 'minMj') {
                        $string = $value;
                        $arr = explode(';', $string);
                        $minMjs = [];
                        for ($i = 0; $i <= 7; $i++) {
                            if (isset($arr[$i])) {
                                if ($i === 0) {
                                    $minMjs[4] = (int) $arr[$i];
                                    $minMjs[9] = (int) $arr[$i];
                                } else {
                                    $minMjs[$item['values'][$i]] = (int) $arr[$i];
                                }
                            }
                        }
                        $polozka[$item['evidence']]['minMjs'] = $minMjs;
                    } elseif ($item['label'] === 'stitky') {
                    */
                    
                    if ($item['label'] === 'stitky') {
                        if (!empty($value) || strtoupper($value) == 'DELETE') {
                            $polozka['cenik']['stitky@removeAll'] = 'true';
                        }
                        if (strtoupper($value) !== 'DELETE') {
                            $polozka['cenik']['stitky'] = strtoupper($value);
                        }
                    } elseif ($item['evidence'] === 'odberatel') {
                        //odberatele mohou byt 2 na jednom radku (pro vsechny firmy a pro cenikovou skupinu)
                        $polozka[$item['evidence']][$item['poradi']][$item['label']] = $item['dataType'] === 'relation' ? 'code:' . strtoupper($value) : $value ;
                    } else {
                        //$polozka[$item['evidence']][$item['label']] = ($item['dataType'] === 'relation' ? 'code:' : '') . $value;
                        $polozka[$item['evidence']][$item['label']] = $item['dataType'] === 'relation' ? 'code:' . strtoupper($value) : $value ;
                    }
                }
            }
            if (isset($polozka['cenik']['kod0'])) {
                $polozka['cenik']['kod'] = $polozka['cenik']['kod0'].$polozka['cenik']['kod1'];
                unset($polozka['cenik']['kod0']);
                unset($polozka['cenik']['kod1']);
            } else {
                $polozka['cenik']['kod'] = $polozka['cenik']['kod1'];
                unset($polozka['cenik']['kod1']);
            }
            if ($checkDuplicity) {
                if (in_array($polozka['cenik']['kod'], $kody)) {
                    //pridat do duplicit
                    $duplicity[$r] = "duplicita se řádkem " . array_search($polozka['cenik']['kod'], $kody);
                } else {
                    //neni duplicita
                    $kody[$r] = $polozka['cenik']['kod'];
                    $polozky[] = $polozka;
                }
            } else {
                $polozky[] = $polozka;
            }
            //bdump($polozka);
        }

        //bdump($polozky, "Polozky");
        //bdump($duplicity, "Duplicity");

        if ($checkDuplicity) {
            $fileDuplicity = "temp_files/duplicity.xlsx";
            $this->exportXls($objWorksheet, $schema, $indexy, $duplicity, $fileDuplicity);
        }
        return $polozky;
    }

    public function exportXls($objWorksheet, $schema, $indexy, $chyby, $filename) {
        $objSpreadsheetW = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $objWorksheetW = $objSpreadsheetW->getSheet(0);

        //$schema = $this->getSchema();
        //bdump($schema);
        //$indexy = $this->getCenikColumns($schema, $objWorksheet);
        $radky = count($chyby);

        for ($r = 1; $r < 7; $r++) {
            if ($r == 1) {
                $objWorksheetW->getCell('A'.'1')->setValue("Řádek, chyba");
            }
            foreach ($schema as $item) {
                if ($item['xls'] && isset($indexy[$item['columnName']])) {
                    $objWorksheetW->getCell($indexy[$item['columnName']] . $r)->setValue($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue());
                }
            }
        }

        $row = 7;
        foreach ($chyby as $r => $chyba) {
            $objWorksheetW->getCell('A'.$row)->setValue($r . ', ' . $chyba);
            foreach ($schema as $item) {
                if ($item['xls'] && isset($indexy[$item['columnName']])) {
                    //bdump("Radek: " . $row . ", sloupec: " . $indexy[$item['columnName']] . ", hodnota: " . $objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue());
                    $objWorksheetW->getCell($indexy[$item['columnName']] . $row)->setValue($objWorksheet->getCell($indexy[$item['columnName']] . $r)->getValue());
                }
            }
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objSpreadsheetW);
        $writer->save($filename);

        return true;
    }

    public function getSchema() {
        return \Nette\Neon\Neon::decode(file_get_contents('../app/schema/cenik.neon'));
    }

    public function getControl() {
        return \Nette\Neon\Neon::decode(file_get_contents('../app/schema/control.neon'));
    }

    public function getCenikColumns($schema, $objWorksheet) {
        $indexy = [];
        for ($c = 'A'; $c <= 'ZZ'; $c++) {
            //nedaří se načíst sloupec s NEPRAVDA
            //bdump($objWorksheet->getCell($c . '2')->getStyle()->setQuotePrefix(true));
            //bdump(trim($objWorksheet->getCell($c . '2')->getStyle()->getNumberFormat()->getFormatCode()));
            //bdump(trim($objWorksheet->getCell($c . '2')->getStyle()->getQuotePrefix()));
            //getValue() vrací formuli getFormattedValue() getCalculatedValue() - vrací výsledek concat
            //bdump(trim($objWorksheet->getCell($c . '2')->getValue()));
            $d = $c;
            $d++;
            //bdump([$c, $d]);
            if (empty(trim($objWorksheet->getCell($c . '2')->getValue() ?? '')) && empty(trim($objWorksheet->getCell($d . '2')->getValue() ?? ''))) {
                break;
            }
            foreach ($schema as $item) {
                if ($item['xls'] === true) {
                    $name = trim($objWorksheet->getCell($c . '2')->getValue() ?? '');
                    if (explode(',', $name)[0] == $item['columnName']) {
                        $indexy[$item['columnName']] = $c;
                    }
                }
            }
        }
        //bdump($indexy);
        return $indexy;
    }

    public function saveImages($values) {
        $results = [];
        $images = [];
        foreach ($values["file"] as $key => $image) {
            $images[] = [
                "name" => $image->getName(),
                "contenttype" => $image->getContentType(),
                "content" => base64_encode($image->getContents())];
        }
        foreach ($images as $key => $image) {

            $objIds = explode(".", $image["name"])[0];
            $polozky = $this->flexibee->get('cenik', [
                'kod' => [
                    '@like' => $objIds
                ]
                    ], [
                "detail" => ["id", "kod"],
                'limit' => 0
            ]);
            foreach ($polozky as $polozka) {
                $this->checkExistingImagesAndDelete($polozka['id']);
                $res = $this->flexibee->put('cenik', [
                    'winstrom' => [
                        'cenik' => [
                            'id' => $polozka['id'],
                            'prilohy' => [
                                'priloha' => [
                                    'nazSoub' => $image['name'],
                                    'typK' => $image['contenttype'],
                                    'contentType' => $image['contenttype'],
                                    'exportNaEshop' => 'true',
                                    'mainAttachment' => 'true',
                                    'cenik' => $polozka['id'],
                                    'content@encoding' => 'base64',
                                    'content' => $image['content'],
                                ]
                            ]
                        ]
                    ]
                ]);
                if ($res['winstrom']['success'] == 'true') {
                    $results[] = 'U produktu s kódem ' . $polozka['kod'] . ' byla aktualizována příloha';
                } else {
                    $results[] = 'U produktu s kódem ' . $polozka['kod'] . ' se nepovedlo aktualizovat přílohu';
                }
            }
        }
        return ['success' => true, 'message' => $results];
    }

    public function checkExistingImagesAndDelete($id) {
        $prilohy = $this->flexibee->get('cenik', [
            'id' => $id
                ], [
            'relations' => 'prilohy',
            'detail' => ['prilohy(id)']
        ]);

        foreach ($prilohy[0]['prilohy'] as $priloha) {
            $this->flexibee->put('priloha', [
                'winstrom' => [
                    'prilohy' => [
                        'id' => $priloha['id'],
                        '@action' => 'delete',
                    ]
                ]
            ]);
        }
    }

    public function getKusovnikColumns($objWorksheet) {
        $indexy = [];
        for ($c = 'B'; $c <= 'G'; $c++) {
            $name = trim($objWorksheet->getCell($c . '1')->getValue());
            if (!empty($name)) {
                $indexy[trim($objWorksheet->getCell($c . '1')->getValue())] = $c;
            }
        }
        return $indexy;
    }

    public function getDataKusovnikFromXls($indexy, $objWorksheet) {
        $polozky = [];
        for ($r = 4; !empty(trim($objWorksheet->getCell($indexy['K1'] . $r)->getValue())); $r++) {
            $polozka = [];
            foreach ($indexy as $key => $index) {
                if (!empty(trim($objWorksheet->getCell($indexy['K1'] . $r)->getValue()))) {
                    $polozka[$key] = $objWorksheet->getCell($index . $r)->getValue();
                }
            }
            $polozky[] = $polozka;
        }
        return $polozky;
    }

    public function prepareDataKusovnik($filename) {
        $filetype = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($filetype);
        $objReader->setReadDataOnly(true);
        $objSpreadsheet = $objReader->load($filename);
        $objWorksheet = $objSpreadsheet->getSheet(0);

        $indexy = $this->getKusovnikColumns($objWorksheet);

        $data = $this->getDataKusovnikFromXls($indexy, $objWorksheet);

        $forCreate = [];
        foreach ($data as $polozka) {
            $otec = $this->flexibee->get('cenik', ['kod' => $polozka['K1']], [
                'detail' => ['id', 'kod', 'typZasobyK', 'skupZboz']
            ]);
            if (!isset($forCreate[$otec[0]['id']])) {
                $polozky = [];
                foreach ($data as $key => $pol) {
                    if ($polozka['K1'] === $pol['K1']) {
                        $komp = $this->flexibee->get('cenik', ['kod' => $pol['K2']], [
                            'detail' => ['id']
                        ]);
                        $polozky[] = [
                            'id' => $komp[0]['id'],
                            'kod' => $pol['K2'],
                            'mnozMj' => $pol['K3'],
                            'poradi' => $pol['K4']
                        ];
                    }
                }
                $what = "";
                if ($otec[0]['typZasobyK'] === 'typZasoby.vyrobek') {
                    $what = 'kusovnik';
                } else if ($otec[0]['typZasobyK'] === 'typZasoby.zbozi' &&
                        $otec[0]['skupZboz'] === 'code:KOMPLETY') {
                    $what = 'sady&komplety';
                } else {
                    $what = null;
                }
                $forCreate[$otec[0]['id']] = [
                    'id' => $otec[0]['id'],
                    'kod' => $otec[0]['kod'],
                    'what' => $what,
                    'polozky' => $polozky
                ];
            }
        }
        $res = file_put_contents('temp_files/kusovnik_temp.json', json_encode(['fb-session' => $_SESSION['fb-session'],
            'fb-company' => $_SESSION['fb-company'],
            'data' => $forCreate]));
        return $res;
    }

    public function kusovnik($forCreate) {
        $count = count($forCreate);
        $finished = 0;
        $this->updateStatus(true, $count, $finished);
        $result = [];
        foreach ($forCreate as $item) {
            // file_put_contents('temp_files/logota.json', json_encode($forCreate));
            if ($item['what'] === 'kusovnik') {
                $this->deleteWholeKusovnik($item['id']);
                $otec = -1;
                foreach ($item['polozky'] as $pol) {
                    $otec = $this->getKusovnikRoot($item['id']);
                    $res = $this->addItemKusovnik($pol['id'], $otec[0]['id'] ?? -1, $item['id'], $pol['mnozMj'], $pol['poradi']);
                    $result[] = json_encode($otec[0]);
                    if ($res['winstrom']['success'] == 'true') {
                        $result[] = 'Položka ' . $pol['kod'] . ' byla přidána do kusovníku položky ' . $item['kod'];
                    } else {
                        $result[] = 'Položku ' . $pol['kod'] . ' se nepovedlo přidat do kusovníku položky ' . $item['kod'] . ' - ' . $res['winstrom']['results'][0]['errors'][0];
                    }
                }
            } else if ($item['what'] === 'sady&komplety') {
                $this->deleteSadyakomplety($item['id']);
                foreach ($item['polozky'] as $pol) {
                    $res = $this->createSadyakomplety($item['id'], $pol['id'], $pol['mnozMj']);
                    if ($res['winstrom']['success'] == 'true') {
                        $result[] = 'Položka ' . $pol['kod'] . ' byla přidána do sad a kompletů položky ' . $item['kod'];
                    } else {
                        $result[] = 'Položku ' . $pol['kod'] . ' se nepovedlo přidat do sad a kompletů položky ' . $item['kod'] . ' - ' . $res['winstrom']['results'][0]['errors'][0];
                    }
                }
            }
            $finished++;
            $this->updateStatus(true, $count, $finished);
        }
        file_put_contents('temp_files/result.log', implode(PHP_EOL, $result));
        $this->updateStatus(false, $count, $finished);
        return ['success' => true, 'message' => 'Připojené položky aktualizovány'];
    }

    public function createSadyakomplety($cenikSada, $cenik, $mnozMj) {
        return $this->flexibee->put('sady-a-komplety', ['winstrom' => [
                        'sady-a-komplety' => [
                            'cenikSada' => $cenikSada,
                            'cenik' => $cenik,
                            'mnozMj' => $mnozMj
                        ]
        ]]);
    }

    public function deleteSadyakomplety($id) {
        $sady = $this->flexibee->get('sady-a-komplety', ['cenikSada' => $id], ['limit' => 0]);
        foreach ($sady as $sada) {
            $this->flexibee->put('sady-a-komplety', ['winstrom' => [
                    'sady-a-komplety' => [
                        '@action' => 'delete',
                        'id' => $sada['id']
                    ]
            ]]);
        }
    }

    // Funkce ohledně kusovníku

    public function deleteWholeKusovnik(int $cenikoveId) {
        $parents = [];
        $kusovnik = $this->getKusovnik($cenikoveId);
        foreach ($kusovnik as $item) {
            if ($item['hladina'] == 1) {
                $parents[] = $item['id'];
            } else {
                $this->deleteItemKusovnik($item['id']);
            }
        }
        foreach ($parents as $parent) {
            $res = $this->deleteItemKusovnik($parent);
        }
        return $res['winstrom']['success'] ?? 'true';
    }

    public function deleteItemKusovnik(int $idPolozky) {
        $res = $this->flexibee->put('kusovnik', [
            'winstrom' => [
                'kusovnik@action' => 'delete',
                'kusovnik' => [
                    'id' => $idPolozky
                ]
            ]
        ]);
    }

    public function addItemKusovnik(string $cenik, int $otec, int $otecCenik, float $mnoz, $poradi) {
        if ($otec <= 0) {
            $res = $this->flexibee->put('kusovnik', [
                'winstrom' => [
                    'kusovnik' => [
                        'cenik' => $otecCenik,
                        'otecCenik' => $otecCenik,
                        'mnoz' => 1,
                        'poradi' => 1,
                    ],
                ]
            ]);
            $otec = $res['winstrom']['results'][0]['id'];
        }

        // zjištění pořadí
        $kusovnik = $this->flexibee->get('kusovnik', [
            'otec' => $otec
                ], [
            'detail' => ['poradi', 'otec', 'hladina'],
            'limit' => 0
        ]);
        $poradi = ((int) end($kusovnik)['poradi'] ?? 0) + 1;
        $data = $this->flexibee->put('kusovnik', [
            'winstrom' => [
                'kusovnik' => [
                    'cenik' => $cenik,
                    'otecCenik' => $otecCenik,
                    'mnoz' => $mnoz,
                    'otec' => $otec,
                    'poradi' => $poradi,
                ],
            ]
        ]);
        return $data;
    }

    public function getKusovnikRoot($otecCenik): ?array {
        $data = $this->flexibee->get('kusovnik', [
            'otecCenik' => $otecCenik,
            'hladina' => 1], [
            'detail' => ['cenik', 'mnoz', 'id', 'otec', 'nazev', 'hladina', 'poradi'],
            'limit' => 0,
        ]);
        return $data;
    }

    public function getKusovnik($id): ?array {
        // data z FlexiBee
        $data = $this->flexibee->get('kusovnik', [
            'otecCenik' => $id], [
            'detail' => ['cenik', 'mnoz', 'id', 'otec', 'otecCenik', 'nazev', 'hladina', 'poradi'],
            'limit' => 0,
        ]);
        foreach ($data ?? [] as $key => $value) {
            $data[$key]['cenik'] = substr($value['cenik'], 5);
        }
        return $data;
    }

    public function stitek($stitek, $ids) {
        foreach ($ids as $id) {
            $json = [
                'id' => $id,
                'stitky' => $stitek
            ];
            if ($stitek == 'STORNO') {
                $dobropisyCreated = [];
                $obj = $this->flexibee->get('objednavka-prijata', [
                    'id' => $id
                        ], [
                    'relations' => 'vazby',
                    'detail' => ['id', 'polozkyDokladu(id)', 'stavUzivK', 'vazby(b,typVazbyK)', 'mena'],
                ]);
                if ($obj[0]['stavUzivK'] === 'stavDoklObch.hotovo') {
                    $fak = array();
                    foreach ($obj[0]['vazby'] as $vazba) {
                        if ($vazba['typVazbyK'] === 'typVazbyDokl.obchod_faktura_hla') {
                            $fak = $this->flexibee->get('faktura-vydana', ['id' => $vazba['b']], [
                                'relations' => 'vazby',
                                'detail' => ['firma', 'typUcOp', 'stredisko', 'cisObj', 'formaDopravy',
                                    'doprava', 'primUcet', 'protiUcet', 'dphZaklUcet', 'statDph', 'slevaDokl',
                                    'clenKonVykDph', 'polozkyDokladu(cenik,mnozMj,cenaMj,typPolozkyK,nazev,szbDph,typCenyDphK,slevaPol)',
                                    'mena', 'vazby(typVazbyK)'],
                            ]);
                        }
                    }
                    if (!empty($fak)) {
                        $dobropisExists = false;
                        foreach ($fak[0]['vazby'] as $v) {
                            if ($v['typVazbyK'] === 'typVazbyDokl.hlavaDobropis') {
                                $dobropisExists = true;
                            }
                        }
                        if ($dobropisExists) {
                            foreach ($fak[0]['polozkyDokladu'] as $k => $p) {
                                if ($p['typPolozkyK'] === 'typPolozky.odpocetZdd') {
                                    unset($fak[0]['polozkyDokladu'][$k]);
                                } else {
                                    unset($fak[0]['polozkyDokladu'][$k]['id']);
                                    unset($fak[0]['polozkyDokladu'][$k]['sumCelkem']);
                                    $fak[0]['polozkyDokladu'][$k]['mnozMj'] = -$p['mnozMj'];
                                }
                            }
                        }
                        $dobropis = $fak[0];
                        unset($dobropis['id']);
                        unset($dobropis['vazby']);
                        $dobropis['stavUhrK'] = 'stavUhr.uhrazenoRucne';
                        $dobropis['typDokl'] = ($fak[0]['mena'] === 'code:CZK') ? 'code:DOBROPIS-CZK' : 'code:DOBROPIS-EUR';
                        $dobropis['vytvor-vazbu-dobropis'] = [
                            'dobropisovanyDokl' => $fak[0]['id']
                        ];
                        \Tracy\Debugger::log('Dobropis:' . json_encode($dobropis, JSON_UNESCAPED_UNICODE));
                        $resDobropis = $this->flexibee->put('faktura-vydana', ['winstrom' => [
                                'faktura-vydana' => $dobropis
                        ]]);
                        if ($resDobropis['winstrom']['success'] === 'true') {
                            $dobropisyCreated[] = $resDobropis['winstrom']['results'][0]['id'];
                            $resFak = $this->flexibee->put('faktura-vydana', ['winstrom' => [
                                    'faktura-vydana' => [
                                        'id' => $fak[0]['id'],
                                        'stavUhrK' => 'stavUhr.uhrazenoRucne'
                            ]]]);
                        } else {
                            \Tracy\Debugger::log('Nepovedlo se vytvořit dobropis pro objednávku s id: ' .
                                    $obj[0]['id'] . PHP_EOL . json_encode($resDobropis['winstrom']['results'][0], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
                $json['stavUzivK'] = 'stavDoklObch.storno';
                foreach ($obj[0]['polozkyDokladu'] as $p) {
                    $json['polozkyObchDokladu'][] = [
                        'objednavka-vydana-polozka' => [
                            'id' => $p['id'],
                            'rezervovatMj' => 0,
                            'rezervovat' => false
                        ]
                    ];
                }
            }

            \Tracy\Debugger::log($this->flexibee->put('objednavka-prijata', ['winstrom' => [
                            'objednavka-prijata' => $json
            ]]));
        }

        return [
            'success' => true,
            'message' => 'Štítky aktualizovány' . (!empty($dobropisyCreated) ? ' a byli vytvořeny dobropisy: ' . implode(', ', $dobropisyCreated) : "")];
    }

    public function updateStatus($running, $count, $finished) {
        file_put_contents('temp_files/status.json', json_encode([
            'running' => $running,
            'statusPercent' => round(($finished / $count) * 100),
            'count' => $count,
            'finished' => $finished
                ])
        );
    }

    public function getCompanyFromUrl(string $url) {        
        return explode('/', urldecode($url))[4];
    }
    
    public function getPrijemka($prijemka, $sklad){
        return  $this->flexibee->get('skladovy-pohyb', ['id' => $prijemka], [
                    'detail' => ['sklad', 'id', 'kod', 'firma', 'varSym', 'datVyst'],
                        ]
                )[0];
    }

    public function getId($type){
        $return =  $this->flexibee->get('poptavka-prijata', ['typDokl' => 'code:' . $type], [
                'detail' => ['id'],
            ]
        );

        return $return[0]['id'];
    }

}
