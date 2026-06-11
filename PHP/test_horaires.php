<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$graphe = chargerGraphe($conn);
$noms   = chargerNomsCommunes($conn);

$depart  = '50184';   // Flamanville
$arrivee = '61006';   // Argentan

echo "<pre>";
echo "=== TEST HORAIRES : Flamanville -> Argentan ===\n\n";

$trajet = trouverTrajet($graphe, $depart, $arrivee, 'duree');
$segments = regrouperParLigne($trajet['chemin']);

foreach ([8*60, 10*60, 14*60] as $heureTest) {
    echo "─────────────────────────────────────────────\n";
    echo "DÉPART SOUHAITÉ : " . minutesEnHhmm($heureTest) . "\n\n";

    $horaires = calculerHorairesTrajet($conn, $segments, $heureTest);
    echo "Réalisable : " . ($horaires['realisable'] ? "OUI" : "NON") . "\n\n";

    foreach ($horaires['etapes'] as $e) {
        $s = $e['segment'];
        $nomDep = $noms[$s['depart']] ?? $s['depart'];
        $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];

        if (!$e['realisable']) {
            echo "  X Ligne {$s['ligne']} : $nomDep -> $nomArr : PLUS DE CAR\n";
            continue;
        }
        $att = $e['attente'] > 0 ? "  [attente " . $e['attente'] . " min]" : "";
        echo "  Ligne " . str_pad($s['ligne'], 4) . " : " . str_pad($nomDep, 14) . " " . minutesEnHhmm($e['depart']) .
             "  ->  " . str_pad($nomArr, 14) . " " . minutesEnHhmm($e['arrivee']) . $att . "\n";
    }
    if ($horaires['realisable']) {
        $dureeTotale = $horaires['arriveeFinale'] - $heureTest;
        echo "\n  Arrivée finale : " . minutesEnHhmm($horaires['arriveeFinale']);
        echo "  (trajet total porte-à-porte : " . intdiv($dureeTotale,60) . "h" . str_pad($dureeTotale%60,2,'0',STR_PAD_LEFT) . ")\n";
    }
    echo "\n";
}

echo "</pre>";
$conn = null;
?>