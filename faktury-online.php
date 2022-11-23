<?php

// $setting = [
// "key" => "Faktury-Online-APIkey",
// "email" => "Faktury-Online registered email",
// "d_id" => 0, "Faktury-Online firma id"
// ];

// $results = $fasetting->insert($setting); 

if (isset($_POST["submit"])) {

    require_once('vendor/autoload.php');

    $databaseDirectory = __DIR__ . "/myDatabase";
    $fasetting = new \SleekDB\Store("faktury-online-setting", $databaseDirectory);
    $rowsetting = $fasetting->findAll();

    $faktcisold = 0;
    $data = array();

    $data['key'] = $rowsetting[0]['key'];
    $data['email'] = $rowsetting[0]['email'];
    $data['apitest'] = 1; //hodnota môže byť 1 alebo 0
    $pdffile = $_FILES["pdffile"]["tmp_name"];
    $dodavatel = array();
    $dodavatel['d_id'] = $rowsetting[0]['d_id'];;

    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($pdffile);
    $pages = $pdf->getPages();
    $pagecount = count($pages);

    for ($i = 0; $i <= $pagecount - 1; $i++) {
        $polozky = array();
        $roww = $pages[$i]->getDataTm();
        $faktcis = $roww[1][1];
        $platcadph = true;
        if ($faktcis <> $faktcisold) {
            $faktcisold = $faktcis;
            $odberatelc = $pages[$i]->getTextXY(314.65);
            $icoc = $pages[$i]->getTextXY(394.02);
            $prevobj = $pages[$i]->getTextXY(354.33);// [0] peňažný prevod [1] cislo obj 
            $datum = array();
            $rowex = false;
            $datumex = false;
            $rownext = false;
            foreach ($roww as &$rowc) {

                if ($rowc[1] == "Neplatiteľ DPH") {
                    $platcadph = false;
                }

                if ($rowc[1] == "Popis položky") {
                    $datumex = false;
                }

                if ($datumex) {
                    @$d = $d + 1;
                    $datum[$d] = $rowc[1];
                }

                if ($rowc[1] == "Dátum splatnosti") {
                    $datumex = true;
                }

                if ($rowc[1] == "Spolu:") {
                    $rowex = false;
                    $polozky[] = $polozka;
                    $rownext = false;
                }

                if ($rownext) {
                    $polozky[] = $polozka;
                    $polozka = array();
                    $rownext = false;
                }

                if ($rowex) {
                    if ($platcadph) {
                        switch (true) {
                            case ($rowc[0][4] == 45.35):   // Popis položky
                                @$polozka['p_text'] .= $rowc[1];
                                break;
                            case ($rowc[0][4] == 285.49): //Množstvo
                                $polozka['p_quantity'] = $rowc[1];
                                break;
                            case ($rowc[0][4] == 316.31):  //MJ
                                $polozka['p_unit'] = $rowc[1];
                                break;
                            case ($rowc[0][4] > 330 and $rowc[0][4] < 370): //Cena za MJ bez DPH
                                $polozka['p_price'] = number_format((float)str_replace(",", ".", $rowc[1]), 4, '.', '');
                                break;
                            case ($rowc[0][4] > 450 and $rowc[0][4] < 470):  //percenta DPH
                                $polozka['p_vat'] = str_replace("%", "", $rowc[1]);
                                break;
                            case ($rowc[0][4] > 500 and $rowc[0][4] < 520):
                                // 
                                $rownext = true;
                                break;
                            default:
                                break;
                        }
                    } else {
                        switch (true) {
                            case ($rowc[0][4] == 45.35):   // Popis položky
                                @$polozka['p_text'] .= $rowc[1];
                                break;
                            case ($rowc[0][4] > 330 and $rowc[0][4] < 350): //Množstvo
                                $polozka['p_quantity'] = $rowc[1];
                                break;
                            case ($rowc[0][4] > 380 and $rowc[0][4] < 400):  //MJ
                                $polozka['p_unit'] = $rowc[1];
                                break;
                            case ($rowc[0][4] > 420 and $rowc[0][4] < 460): //Cena za MJ
                                $polozka['p_price'] = number_format((float)str_replace(",", ".", $rowc[1]), 4, '.', '');
                                break;
                            case ($rowc[0][4] > 495 and $rowc[0][4] < 510):
                                // 
                                $rownext = true;
                                break;
                            default:
                                break;
                        }
                        $polozka['p_vat'] = 0;
                    }
                }
                if (($rowc[1] == "Celkom s DPH") or ($rowc[1] == "Celková cena")) {
                    $rowex = true;
                    $polozka = array();
                    $polozka['p_text'] = "";
                }
            }

            $odberatel = array();
            $odberatel['o_name'] = $odberatelc[0][1]; // Názov odberateľa
            $odberatel['o_street'] = ""; // Ulica
            $odberatel['o_city'] = ""; // Mesto
            $odberatel['o_zip'] = ""; // PSČ
            $odberatel['o_state'] = ""; // Štát
            $odberatel['o_ico'] = @$icoc[0][1];; // IČO
            $odberatel['o_dic'] = @$icoc[1][1]; // DIČ
            $odberatel['o_icdph'] = @$icoc[2][1]; // IČDPH
            $odberatel['o_email'] = ""; // Email odberateľa

            $faktura = array();
            // Číslo faktúry môžeme zadať ručne:
            $faktura['f_number'] = $faktcis; // Ak chceme vypočítať číslo automaticky, toto pole neuvedieme
            $faktura['f_vs'] = $pages[$i]->getTextXY(525.68, 778.55)[0][1]; // Variabilný symbol
            $faktura['f_ks'] = "308"; // Konštantný symbol
			$faktura['f_style'] = "standard";
            $faktura['f_date_issue'] = $datum[1]; // Dátum vystavenia vo formáte RRRR-MM-DD
            $faktura['f_date_delivery'] = $datum[2]; // Dátum dodania vo formáte RRRR-MM-DD
            $faktura['f_date_due'] = $datum[3]; // Dátum splatnosti vo formáte RRRR-MM-DD
            $faktura['f_payment'] = "prevod";//@$prevobj[0][1];   //Druh plaby. Na výber sú "prevod", "poukazka",
            $faktura['f_language'] = "SK";   // Jazyk faktúry: SK, CZ, EN, DE, ES, IT, FR, HU, PL, NO, RU
            $faktura['f_qr'] = "1";   // Zobraziť QR kód (PAY by square)
            // $faktura['f_order'] = "";; //OBJ-2017-514";  //Číslo objednávky
            $data['d'] = $dodavatel;
            $data['o'] = $odberatel;
            $data['f'] = $faktura;
            $data['p'] = $polozky;

          //  print_r($odberatel);
          //  echo "</br>";
          //  print_r($faktura);
          //  echo "</br>";
          //  print_r($polozky);
          //  echo "</br>";


        }

// Dáta sa uložia do formátu JSON:
        $data_json = json_encode($data);

// Dáta sa odošlú:
        $url = 'https://www.faktury-online.com/api/nf?data=' . urlencode($data_json);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $output = curl_exec($ch);

// Výsledok:
        $result = @json_decode($output, true);
        if (@$result['status'] == 1) {
            echo "Uložené, nová faktúra má kód: " . $result['code'] . " <br />";
            echo "Číslo faktúry: " . $result['number'] . " <br />";
            echo "Faktúra vytvorená: " . $result['created'] . " <br />";
        } else {
            echo "Vyskytla sa chyba č." . @$result['status'];
        }
    }
}
?>