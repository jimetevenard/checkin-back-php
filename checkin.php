<?php
 header("Access-Control-Allow-Origin: *");
 header("Content-Type: application/json");

 $liste = file_get_contents('data/liste.json');

 echo $liste;