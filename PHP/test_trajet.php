<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);  // on autorise jusqu'à 2 min (Yen peut être lent)

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$graphe = chargerGraphe($conn);
$noms   = chargerNomsCommunes($conn);

$depart  = '50184';   // Flamanville
$arrivee = '61006';   // Argentan
$critere = 'duree';

echo "<pre>";
echo "=== TEST AVEC TRACES ===\n\n";

// On vérifie d'abord que le premier trajet existe
$premier = trouverTrajet($graphe, $depart, $arrivee, $critere);
echo "Premier trajet trouvé : " . ($premier ? "OUI" : "NON") . "\n";
if ($premier) {
    echo "  Durée : {$premier['dureeTotale']} min, Distance : {$premier['distanceTotale']} km\n";
    echo "  Nombre d'étapes (nœuds élémentaires) : " . count($premier['chemin']) . "\n\n";
}

// Test manuel d'une déviation : on bloque la première arête et on cherche un autre chemin
echo "=== Test de déviation manuelle ===\n";
$premiereArete = $premier['chemin'][0];
echo "Première arête du meilleur trajet : {$premiereArete['de']} -> {$premiereArete['vers']} (ligne {$premiereArete['ligne']})\n";

// On copie le graphe et on supprime cette arête
$grapheMod = $graphe;
$de = $premiereArete['de'];
$versBloque = $premiereArete['vers'];
echo "Arêtes sortantes de $de AVANT suppression : " . count($grapheMod[$de]) . "\n";
$grapheMod[$de] = array_values(array_filter($grapheMod[$de], function($a) use ($versBloque) {
    return $a['vers'] !== $versBloque;
}));
echo "Arêtes sortantes de $de APRÈS suppression : " . count($grapheMod[$de]) . "\n";

// On cherche un nouveau trajet sans cette arête
$alternatif = trouverTrajet($grapheMod, $depart, $arrivee, $critere);
echo "Trajet alternatif (sans la 1ère arête) : " . ($alternatif ? "OUI" : "NON") . "\n";
if ($alternatif) {
    echo "  Durée : {$alternatif['dureeTotale']} min, Distance : {$alternatif['distanceTotale']} km\n";
    $seg = regrouperParLigne($alternatif['chemin']);
    foreach ($seg as $s) {
        echo "    Ligne {$s['ligne']} : " . ($noms[$s['depart']] ?? $s['depart']) . " -> " . ($noms[$s['arrivee']] ?? $s['arrivee']) . "\n";
    }
}

echo "\n=== APPEL trouverKTrajets ===\n";
$debut = microtime(true);
$trajets = trouverKTrajets($graphe, $depart, $arrivee, 5, $critere);
$temps = round(microtime(true) - $debut, 2);
echo "Temps de calcul : {$temps}s\n";
echo "Nombre de trajets trouvés : " . count($trajets) . "\n\n";

foreach ($trajets as $i => $t) {
    $num = $i + 1;
    $h = intdiv($t['dureeTotale'], 60);
    $m = $t['dureeTotale'] % 60;
    echo "TRAJET $num : {$h}h" . str_pad($m, 2, '0', STR_PAD_LEFT) . " · {$t['distanceTotale']} km\n";
    $segments = regrouperParLigne($t['chemin']);
    foreach ($segments as $s) {
        $nomDep = $noms[$s['depart']]  ?? $s['depart'];
        $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];
        echo "   Ligne {$s['ligne']} : $nomDep -> $nomArr\n";
    }
    echo "\n";
}

echo "</pre>";
$conn = null;
?>