<?php
// ════════════════════════════════════════════════════════════
// moteur_trajet.php
// Fonctions de recherche de trajet (graphe + Dijkstra)
// ════════════════════════════════════════════════════════════

// Construit le graphe du réseau à partir de VIK_NOEUD.
// On garde les sens réels, ET on ajoute le sens inverse UNIQUEMENT
// quand il n'existe pas déjà (pour que les communes terminus puissent
// servir de départ, sans dupliquer les liaisons déjà bidirectionnelles).
function chargerGraphe($conn)
{
    $sql = "SELECT LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT,
                   NOE_DUREE_PROCHAIN, NOE_DISTANCE_PROCHAIN
            FROM VIK_NOEUD";
    $stmt = $conn->query($sql);
    $noeuds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Durée minimale par liaison (depart -> vers) sur une ligne donnée
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

    // 1) On note quelles liaisons (depart->vers) existent réellement
    $liaisonsReelles = [];
    foreach ($minDuree as $c) {
        $liaisonsReelles[$c['depart'] . '>' . $c['vers']] = true;
    }

    // 2) Construction du graphe : sens réels d'abord
    $graphe = [];
    foreach ($minDuree as $c) {
        $graphe[$c['depart']][] = [
            'vers'     => $c['vers'],
            'ligne'    => $c['ligne'],
            'duree'    => $c['duree'],
            'distance' => $c['distance'],
        ];
    }

    // 3) Sens inverse SEULEMENT si la liaison inverse n'existe pas déjà
    //    (évite de dupliquer les lignes déjà bidirectionnelles type 5A/5B,
    //     mais donne une sortie aux communes terminus comme Bréval)
    foreach ($minDuree as $c) {
        $cleInverse = $c['vers'] . '>' . $c['depart'];
        if (!isset($liaisonsReelles[$cleInverse])) {
            $graphe[$c['vers']][] = [
                'vers'     => $c['depart'],
                'ligne'    => $c['ligne'],   // on réutilise la même ligne (sens retour implicite)
                'duree'    => $c['duree'],
                'distance' => $c['distance'],
            ];
        }
    }

    return $graphe;
}

// Dijkstra : trajet optimal entre deux communes selon un critère.
// $critere = 'duree' (le plus rapide) ou 'distance' (le plus court)
function trouverTrajet($graphe, $depart, $arrivee, $critere = 'duree')
{
    if (!isset($graphe[$depart])) return null;

    // Le poids d'une arête dépend du critère choisi
    // (on minimise la durée OU la distance cumulée)
    $cout    = [$depart => 0];   // coût cumulé minimal selon le critère
    $parents = [];
    $visites = [];
    $aTraiter = [$depart => 0];

    while (!empty($aTraiter)) {
        asort($aTraiter);
        $commune = array_key_first($aTraiter);
        $coutActuel = $aTraiter[$commune];
        unset($aTraiter[$commune]);

        if (isset($visites[$commune])) continue;
        $visites[$commune] = true;
        if ($commune === $arrivee) break;
        if (!isset($graphe[$commune])) continue;

        foreach ($graphe[$commune] as $conn) {
            $voisine = $conn['vers'];
            // Poids de l'arête selon le critère
            $poids = ($critere === 'distance') ? $conn['distance'] : $conn['duree'];
            $nouveauCout = $coutActuel + $poids;

            if (!isset($cout[$voisine]) || $nouveauCout < $cout[$voisine]) {
                $cout[$voisine]    = $nouveauCout;
                $parents[$voisine] = ['depuis' => $commune, 'ligne' => $conn['ligne'], 'duree' => $conn['duree'], 'distance' => $conn['distance']];
                $aTraiter[$voisine] = $nouveauCout;
            }
        }
    }

    if (!isset($cout[$arrivee])) return null;

    // Reconstruction
    $chemin = [];
    $courant = $arrivee;
    while (isset($parents[$courant])) {
        $p = $parents[$courant];
        $chemin[] = ['de' => $p['depuis'], 'vers' => $courant, 'ligne' => $p['ligne'], 'duree' => $p['duree'], 'distance' => $p['distance']];
        $courant = $p['depuis'];
    }
    $chemin = array_reverse($chemin);

    // On calcule TOUJOURS la durée et la distance totales du chemin trouvé
    // (peu importe le critère, pour pouvoir afficher les deux infos)
    $dureeTot = 0;
    $distTot = 0;
    foreach ($chemin as $e) {
        $dureeTot += $e['duree'];
        $distTot  += $e['distance'];
    }

    return [
        'chemin'      => $chemin,
        'dureeTotale' => $dureeTot,
        'distanceTotale' => $distTot,
        'critere'     => $critere,
    ];
}

function chargerNomsCommunes($conn)
{
    $stmt = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE");
    $noms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $noms[trim($c['COM_CODE_INSEE'])] = $c['COM_NOM'];
    }
    return $noms;
}

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

function distanceTotale($chemin)
{
    $d = 0;
    foreach ($chemin as $e) $d += $e['distance'];
    return $d;
}

function calculerPoints($distance)
{
    return max(1, (int)floor($distance / 10));
}
?>