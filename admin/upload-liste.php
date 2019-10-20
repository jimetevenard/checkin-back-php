<?php
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// ================
// LIB SPREADSHEET
// ================

// Excel reader from http://code.google.com/p/php-excel-reader/
require('php-excel-reader/excel_reader2.php');
require('SpreadsheetReader.php');

// ================
// FONCTIONS UTILS
// ================

function trouverColomneNom(array $firstRow){
    for ($i = 0; $i < sizeof($firstRow); $i++) {
        if(trim(strtolower($firstRow[$i])) == 'nom') { 
            return $i;
        }
    }
    return -1;
}
function trouverColomnePrenom(array $firstRow){
    for ($i = 0; $i < sizeof($firstRow); $i++) {
        $colName = trim(strtolower($firstRow[$i]));
        // On a parfois des problèmes avec l'accent.
        // Or, la colonne prénom est indispensable
        if(substr($colName, 0, 2 ) === "pr"  && substr($colName, -3, 3 ) == 'nom'){
            return $i;
        }      
    }
    return -1;
}

function trouverColomneMail(array $firstRow){
    for ($i = 0; $i < sizeof($firstRow); $i++) {
        $colName = strtolower($firstRow[$i]);
        if( strpos( $colName, 'mail' )  !== FALSE) { 
            return $i;
        }
    }
    return -1;
}

// ================
// GO !!!!!!!!!!!!!
// ================

$result = [
    "errors" => [],
    "warnings" => [],
    "logs" => []
];

try {

    // TODO : Définir un nom $fileName pour le ficheir
    $fileName = 'upload_' . time() . '.xlsx';

    // TODO : Recevoir le fichier en POST
    // TODO : copier le fichier dans data/files/{$fileName}.xlsx
    $uploadfile = dirname(dirname(__FILE__)) . '/data/files/' . $fileName;

    if(substr($_FILES['fichier']['name'], -5, 5 ) !== '.xlsx'){
        throw new Exception('Format de fichier incorrect : doit être un fichier Excel (.xlsx)');
    }

    if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadfile)) {
        throw new Exception('Impossible de copier le fichier');
    }

    // TRAITER le fichier (construire un array)

    // initialisation de la lib.
    try {
        $Reader = new SpreadsheetReader($uploadfile);
        $Sheets = $Reader -> Sheets();
        // Première feuille du fichier
        $Reader -> ChangeSheet(0);
    } catch(Exception $e) {
        throw new Exception('Impossible de lire le fichier. Son format n\'est pas reconnu.');
    }

    $liste = [];

    $firstPassed = false;
    foreach ($Reader as $Row) {
        if(!$firstPassed){
            $indexNom = trouverColomneNom($Row);
            $indexPrenom =  trouverColomnePrenom($Row);
            $indexMail = trouverColomneMail($Row);

            if($indexNom === -1 || $indexPrenom === -1){
                throw new Exception('Les colomnes NOM et/ou PRENOM, obligatoires, n\'on pas pu être identifiées.');
            }

            array_push( $result['logs'], ['first-Row' => $Row] );

            $firstPassed = true;
        } else {
            try {
                $nom = $Row[$indexNom];
                $prenom = $Row[$indexPrenom];
                $mail = $indexMail > -1 ? $Row[$indexMail] : '';

                array_push($liste,[
                    "nom" => $nom,
                    "prenom" => $prenom,
                    "mail" => $mail
                ]);
            } catch(Exception $e) {
                array_push($result['warnings'], 'Erreur dans la lecture de la ligne ' . var_dump($Row));
            }
            
        }
        
    }

    // Ecrire dans data/liste.js
    $listeJson = json_encode($liste);
    $listeFilePath = dirname(dirname(__FILE__)) . '/data/liste.json';
    $listeWrittenOk = file_put_contents($listeFilePath,$listeJson);
    if($listeWrittenOk === false){
        throw new Exception('Erreur lors de l\'écriture du fichier JSON');
    }

    if($listeWrittenOk === false){
        throw new Exception('Erreur lors de l\'écriture du fichier JSON');
    }

    $result['status'] = 'OK';

} catch (Exception $e) {
    array_push($result['errors'],$e->getMessage());
    $result['status'] = 'KO';
}


// Output du tableau de résultat (+ log)
$resultJSon = json_encode($result);
try {
    file_put_contents($uploadfile . '.logs.json', $resultJSon);
} catch (Exception $e) {
   // do nothing
}
echo json_encode($result);
	
