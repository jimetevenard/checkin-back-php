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

/**
 * Remplace la chaine $mail par le pattern suivant :
 * m@<Empreinte SHA1 du mail>
 * 
 * - La fonction ne s'applique que siun arobase est présent
 * - Le pattern de sortie contien un arobase.
 * 
 * => Ces deux points servent à Garder la distinction entre les
 *      inscrits et les invités
 * 
 */
function cacherMail($mail){
    if(strpos($mail,'@') !== false){
        return 'm@' . sha1($mail);
    } else {
        return $mail;
    }
    
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

    $timestamp = time();
    $initialFileName = $_FILES['fichier']['name'];
    $fileName = 'upload_' . $timestamp . '.xlsx';

    // Reception du fichier en POST
    // Copie du fichier dans data/files/{$fileName}.xlsx
    $uploadfile = dirname(dirname(__FILE__)) . '/data/files/' . $fileName;

    if(substr($initialFileName, -5, 5 ) !== '.xlsx'){
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
    $index = 0;
    
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
                $ok = true;

                $nom = $Row[$indexNom];
                if($nom === null || strlen($nom) == 0) { $ok = false; }

                $prenom = $Row[$indexPrenom];
                if($prenom === null || strlen($prenom) == 0) { $ok = false; }

                if($ok === false){ throw new Exception('Ligne invalide'); }

                $mail = $indexMail > -1 ? cacherMail($Row[$indexMail]) : '';
                
                array_push($liste,[
                    "nom" => $nom,
                    "prenom" => $prenom,
                    "mail" => $mail,
                    "id" => $index
                ]);
                
                $index++;

             } catch(Exception $e) {
                array_push($result['warnings'], 'Erreur dans la lecture de la ligne ' . var_export($Row,true));
            }
            
        }
        
    }

    // Ecrire dans data/liste.js
    $wrappedListe = [
      "id" => $timestamp,
      "file" => $initialFileName,
      "guests" => $liste
    ];
    $listeJson = json_encode($wrappedListe);
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
// =====================================
$result['filename'] = $initialFileName;
$resultJSon = json_encode($result);
try {
    file_put_contents($uploadfile . '.logs.json', $resultJSon);
} catch (Exception $e) {
   // do nothing
}
echo json_encode($result);
	
