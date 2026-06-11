<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// ════════════════════════════════════════════════════════════
// COUCHE 1 : chargement du graphe (dédupliqué + bidirectionnel)
// ════════════════════════════════════════════════════════════

$sql = "SELECT LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT,
               NOE_DUREE_PROCHAIN, NOE_DISTANCE_PROCHAIN, NOE_HEURE_PASSAGE
        FROM VIK_NOEUD";
$stmt = $conn->query($sql);
$noeuds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// On garde la durée MINIMALE pour chaque trajet (commune -> suivante sur une ligne)
$minDuree = [];
foreach ($noeuds as $n) {
    $depart = trim($n['COM_CODE_INSEE_ARRET']);
    $vers   = trim($n['COM_CODE_INSEE_SUIVANT']);
    $ligne  = trim($n['LIG_NUM']);
    $duree  = (int)$n['NOE_DUREE_PROCHAIN'];
    $dist   = (float)$n['NOE_DISTANCE_PROCHAIN'];

    $cle = $depart . '|' . $vers . '|' . $ligne;

    if (!isset($minDuree[$cle]) || $duree < $minDuree[$cle]['duree']) {
        $minDuree[$cle] = [
            'depart'   => $depart,
            'vers'     => $vers,
            'ligne'    => $ligne,
            'duree'    => $duree,
            'distance' => $dist,
        ];
    }
}

// Construction du graphe BIDIRECTIONNEL : un bus A->B circule aussi B->A
$graphe = [];
foreach ($minDuree as $c) {
    $graphe[$c['depart']][] = [
        'vers'     => $c['vers'],
        'ligne'    => $c['ligne'],
        'duree'    => $c['duree'],
        'distance' => $c['distance'],
    ];
    $graphe[$c['vers']][] = [
        'vers'     => $c['depart'],
        'ligne'    => $c['ligne'],
        'duree'    => $c['duree'],
        'distance' => $c['distance'],
    ];
}

// ════════════════════════════════════════════════════════════
// COUCHE 2 : Dijkstra (trajet le plus rapide en durée)
// ════════════════════════════════════════════════════════════

function trouverTrajet($graphe, $depart, $arrivee)
{
    if (!isset($graphe[$depart])) {
        return null;
    }

    $durees   = [];
    $parents  = [];
    $visites  = [];

    $durees[$depart] = 0;
    $aTraiter = [$depart => 0];

    while (!empty($aTraiter)) {
        asort($aTraiter);
        $commune = array_key_first($aTraiter);
        $dureeActuelle = $aTraiter[$commune];
        unset($aTraiter[$commune]);

        if (isset($visites[$commune])) continue;
        $visites[$commune] = true;

        if ($commune === $arrivee) break;

        if (!isset($graphe[$commune])) continue;
        foreach ($graphe[$commune] as $conn) {
            $voisine = $conn['vers'];
            $nouvelleDuree = $dureeActuelle + $conn['duree'];

            if (!isset($durees[$voisine]) || $nouvelleDuree < $durees[$voisine]) {
                $durees[$voisine]  = $nouvelleDuree;
                $parents[$voisine] = [
                    'depuis'   => $commune,
                    'ligne'    => $conn['ligne'],
                    'duree'    => $conn['duree'],
                    'distance' => $conn['distance'],
                ];
                $aTraiter[$voisine] = $nouvelleDuree;
            }
        }
    }

    if (!isset($durees[$arrivee])) {
        return null;
    }

    // Reconstruction du chemin (on remonte les parents)
    $chemin = [];
    $courant = $arrivee;
    while (isset($parents[$courant])) {
        $p = $parents[$courant];
        $chemin[] = [
            'de'       => $p['depuis'],
            'vers'     => $courant,
            'ligne'    => $p['ligne'],
            'duree'    => $p['duree'],
            'distance' => $p['distance'],
        ];
        $courant = $p['depuis'];
    }
    $chemin = array_reverse($chemin);

    return [
        'chemin'      => $chemin,
        'dureeTotale' => $durees[$arrivee],
    ];
}

// ════════════════════════════════════════════════════════════
// COUCHE 3 : rendre le résultat lisible
// ════════════════════════════════════════════════════════════

// Récupère les noms de toutes les communes (code INSEE -> nom)
function chargerNomsCommunes($conn)
{
    $sql = "SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE";
    $stmt = $conn->query($sql);
    $noms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $noms[trim($c['COM_CODE_INSEE'])] = $c['COM_NOM'];
    }
    return $noms;
}

// Regroupe les étapes consécutives sur une même ligne en segments
function regrouperParLigne($chemin)
{
    $segments = [];
    $courant = null;

    foreach ($chemin as $e) {
        if ($courant === null || $courant['ligne'] !== $e['ligne']) {
            if ($courant !== null) {
                $segments[] = $courant;
            }
            $courant = [
                'ligne'    => $e['ligne'],
                'depart'   => $e['de'],
                'arrivee'  => $e['vers'],
                'duree'    => $e['duree'],
                'distance' => $e['distance'],
            ];
        } else {
            $courant['arrivee']   = $e['vers'];
            $courant['duree']    += $e['duree'];
            $courant['distance'] += $e['distance'];
        }
    }
    if ($courant !== null) {
        $segments[] = $courant;
    }

    return $segments;
}

// ════════════════════════════════════════════════════════════
// TESTS
// ════════════════════════════════════════════════════════════

$noms = chargerNomsCommunes($conn);

echo "<pre>";
echo "=== DIAGNOSTIC GRAPHE ===\n";
echo "Communes connectées : " . count($graphe) . "\n";

$depart  = '50184';   // Flamanville
$arrivee = '61006';   // Argentan

echo "Départ : " . ($noms[$depart] ?? $depart) . " ($depart)\n";
echo "Arrivée : " . ($noms[$arrivee] ?? $arrivee) . " ($arrivee)\n\n";

echo "=== TRAJET LE PLUS RAPIDE ===\n";
$resultat = trouverTrajet($graphe, $depart, $arrivee);

if ($resultat === null) {
    echo "Aucun trajet trouvé.\n";
} else {
    $h = intdiv($resultat['dureeTotale'], 60);
    $m = $resultat['dureeTotale'] % 60;
    echo "Durée totale : {$h}h{$m} ({$resultat['dureeTotale']} min)\n\n";

    $segments = regrouperParLigne($resultat['chemin']);
    $distanceTotale = 0;

    echo "Votre trajet :\n";
    foreach ($segments as $i => $s) {
        $nomDep = $noms[$s['depart']]  ?? $s['depart'];
        $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];
        $num = $i + 1;
        echo "  $num. Ligne {$s['ligne']} : $nomDep -> $nomArr ({$s['duree']} min, {$s['distance']} km)\n";
        $distanceTotale += $s['distance'];
    }

    echo "\nDistance totale : $distanceTotale km\n";
    echo "Nombre de correspondances : " . (count($segments) - 1) . "\n";

    // Points (règle du sujet : 1 pt / 10 km, arrondi inférieur, min 1)
    $points = max(1, (int)floor($distanceTotale / 10));
    echo "Points gagnés : $points\n";
}
echo "</pre>";

$conn = null;
?>