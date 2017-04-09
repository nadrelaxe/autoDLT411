<?php
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

//echo "Récupération des torrents 'Top today'...<br>";
$torrents = $t411->getTopToday ();

foreach ($torrents as $torrent) {
	echo $torrent;
}
?>
