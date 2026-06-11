<?php
// ════════════════════════════════════════════════════════════
// moteur_trajet.php
// Fonctions de recherche de trajet (graphe + Dijkstra)
// Réutilisé par recherche.php ET traitement_reservation.php
// ════════════════════════════════════════════════════════════

// Construit le graphe bidirectionnel du réseau à partir de VIK_NOEUD
function chargerGraphe($conn)
{
    $sql = "SELECT LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT,
                   NOE_DUREE_PROCHAIN, NOE_DISTANCE_PROCHAIN
            FROM VIK_NOEUD";
    $stmt = $conn->query($sql);
    $noeuds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // On garde la durée minimale par trajet (commune -> suivante sur une ligne)
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
                'depart' => $depart, 'vers' => $vers, 'ligne' => $ligne,
                'duree' => $duree, 'distance' => $dist,
            ];
        }
    }

    // Graphe bidirectionnel
    $graphe = [];
    foreach ($minDuree as $c) {
        $graphe[$c['depart']][] = ['vers' => $c['vers'], 'ligne' => $c['ligne'], 'duree' => $c['duree'], 'distance' => $c['distance']];
        $graphe[$c['vers']][]   = ['vers' => $c['depart'], 'ligne' => $c['ligne'], 'duree' => $c['duree'], 'distance' => $c['distance']];
    }
    return $graphe;
}

// Dijkstra : trajet le plus rapide entre deux communes
function trouverTrajet($graphe, $depart, $arrivee)
{
    if (!isset($graphe[$depart])) return null;

    $durees   = [$depart => 0];
    $parents  = [];
    $visites  = [];
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
                $parents[$voisine] = ['depuis' => $commune, 'ligne' => $conn['ligne'], 'duree' => $conn['duree'], 'distance' => $conn['distance']];
                $aTraiter[$voisine] = $nouvelleDuree;
            }
        }
    }

    if (!isset($durees[$arrivee])) return null;

    // Reconstruction du chemin
    $chemin = [];
    $courant = $arrivee;
    while (isset($parents[$courant])) {
        $p = $parents[$courant];
        $chemin[] = ['de' => $p['depuis'], 'vers' => $courant, 'ligne' => $p['ligne'], 'duree' => $p['duree'], 'distance' => $p['distance']];
        $courant = $p['depuis'];
    }
    return ['chemin' => array_reverse($chemin), 'dureeTotale' => $durees[$arrivee]];
}

// Récupère les noms de communes (code INSEE -> nom)
function chargerNomsCommunes($conn)
{
    $stmt = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE");
    $noms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $noms[trim($c['COM_CODE_INSEE'])] = $c['COM_NOM'];
    }
    return $noms;
}

// Regroupe les étapes consécutives sur une même ligne
function regrouperParLigne($chemin)
{
    $segments = [];
    $courant = null;
    foreach ($chemin as $e) {
        if ($courant === null || $courant['ligne'] !== $e['ligne']) {
            if ($courant !== null) $segments[] = $courant;
            $courant = ['ligne' => $e['ligne'], 'depart' => $e['de'], 'arrivee' => $e['vers'], 'duree' => $e['duree'], 'distance' => $e['distance']];
        } else {
            $courant['arrivee']   = $e['vers'];
            $courant['duree']    += $e['duree'];
            $courant['distance'] += $e['distance'];
        }
    }
    if ($courant !== null) $segments[] = $courant;
    return $segments;
}

// Calcule la distance totale d'un chemin
function distanceTotale($chemin)
{
    $d = 0;
    foreach ($chemin as $e) $d += $e['distance'];
    return $d;
}

// Calcule les points gagnés (règle sujet : 1 pt / 10 km, arrondi inférieur, min 1)
function calculerPoints($distance)
{
    return max(1, (int)floor($distance / 10));
}
?>
