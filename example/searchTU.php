<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/T411.php';

$config = include __DIR__ . '/../config.php';
$torrentPath = '../torrents/';

// --- App ---
//echo "Initialisation de l'objet t411...<br>";

$t411 = new T411 ( $config );

try {
	//echo "Connexion à l'API...<br>";
	$t411->login ();
} catch ( Exception $e ) {
	die ( $e );
}

//echo "V곩fication retour<br>";
if (isset ( $_GET ['q'] )) {
	$results = file_get_contents('http://www.t411.li/torrents/' . $_GET['q']);
	$pattern = "/(\/users\/login\/\?returnto=\/t\/)\\d{7}/i";	
	preg_match($pattern, $results, $temp);
	$id = substr($temp[0], 26);
	
	$file = $t411->downloadById ( $id );
	$location = $torrentPath . 'dl.torrent';
	if (file_put_contents ( $location, $file )) {
		//echo "Torrent DL,";
	} else {
		//echo "Torrent Non-DL,";
	}
	
	//header('Location: '.$location);
	header("Content-Disposition: attachment; filename=".$id.".torrent");
	header("Content-Type: application/force-download");
	header("Content-Length: " . filesize($location));
	header('Content-Transfer-Encoding: binary');
	header("Connection: close");
	readfile($location);
}