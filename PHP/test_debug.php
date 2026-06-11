<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$graphe = chargerGraphe($conn);

echo "<pre>";
echo "=== ARÊTES SORTANTES DE FLAMANVILLE (50184) ===\n\n";

$de = '50184';
if (isset($graphe[$de])) {
    foreach ($graphe[$de] as $i => $arete) {
        $vers = $arete['vers'];
        echo "Arête $i :\n";
        echo "  vers = '" . $vers . "'\n";
        echo "  longueur de 'vers' = " . strlen($vers) . " caractères\n";
        echo "  vers === '50587' ? " . ($vers === '50587' ? 'OUI' : 'NON') . "\n";
        echo "  ligne = '{$arete['ligne']}'\n";
        // Affichage hexa pour voir les espaces cachés
        echo "  hexa de vers : ";
        for ($j = 0; $j < strlen($vers); $j++) {
            echo dechex(ord($vers[$j])) . " ";
        }
        echo "\n\n";
    }
} else {
    echo "Flamanville n'est pas une clé du graphe !\n";
}

echo "</pre>";
$conn = null;
?>
