<?php
// ════════════════════════════════════════════════════════════
// moteur_trajet.php
// Fonctions de recherche de trajet (graphe + Dijkstra et K-Trajets)
// ════════════════════════════════════════════════════════════

function chargerGraphe($conn)
{
    $sql = "SELECT LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT,
                   NOE_DUREE_PROCHAIN, NOE_DISTANCE_PROCHAIN
            FROM VIK_NOEUD";
    $stmt = $conn->query($sql);
    $noeuds = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $liaisonsReelles = [];
    foreach ($minDuree as $c) {
        $liaisonsReelles[$c['depart'] . '>' . $c['vers']] = true;
    }

    $graphe = [];
    foreach ($minDuree as $c) {
        $graphe[$c['depart']][] = [
            'vers'     => $c['vers'],
            'ligne'    => $c['ligne'],
            'duree'    => $c['duree'],
            'distance' => $c['distance'],
        ];
    }

    foreach ($minDuree as $c) {
        $cleInverse = $c['vers'] . '>' . $c['depart'];
        if (!isset($liaisonsReelles[$cleInverse])) {
            $graphe[$c['vers']][] = [
                'vers'     => $c['depart'],
                'ligne'    => $c['ligne'],
                'duree'    => $c['duree'],
                'distance' => $c['distance'],
            ];
        }
    }

    return $graphe;
}

function trouverTrajet($graphe, $depart, $arrivee, $critere = 'duree')
{
    if (!isset($graphe[$depart])) return null;

    $cout    = [$depart => 0];
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

    $chemin = [];
    $courant = $arrivee;
    while (isset($parents[$courant])) {
        $p = $parents[$courant];
        $chemin[] = ['de' => $p['depuis'], 'vers' => $courant, 'ligne' => $p['ligne'], 'duree' => $p['duree'], 'distance' => $p['distance']];
        $courant = $p['depuis'];
    }
    $chemin = array_reverse($chemin);

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

function trouverKTrajets($graphe, $depart, $arrivee, $critere = 'duree', $k_max = 5) {
    if (!isset($graphe[$depart])) return [];

    $aTraiter = [[
        'cout'    => 0,
        'noeud'   => $depart,
        'chemin'  => [],
        'visites' => [$depart => true]
    ]];

    $cheminsTrouves = [];
    $compteNoeuds = [];

    while (!empty($aTraiter)) {
        $minIndex = 0;
        $minCout = $aTraiter[0]['cout'];
        foreach ($aTraiter as $i => $item) {
            if ($item['cout'] < $minCout) {
                $minCout = $item['cout'];
                $minIndex = $i;
            }
        }
        $courant = $aTraiter[$minIndex];
        unset($aTraiter[$minIndex]);
        $aTraiter = array_values($aTraiter);

        $noeudCourant = $courant['noeud'];

        if (!isset($compteNoeuds[$noeudCourant])) $compteNoeuds[$noeudCourant] = 0;
        $compteNoeuds[$noeudCourant]++;
        if ($compteNoeuds[$noeudCourant] > 80) continue;

        if ($noeudCourant === $arrivee) {
            $dureeTot = 0;
            $distTot = 0;
            foreach ($courant['chemin'] as $e) {
                $dureeTot += $e['duree'];
                $distTot += $e['distance'];
            }

            $cheminsTrouves[] = [
                'chemin'         => $courant['chemin'],
                'dureeTotale'    => $dureeTot,
                'distanceTotale' => $distTot,
                'critere'        => $critere,
            ];

            if (count($cheminsTrouves) >= $k_max) break;
            continue;
        }

        if (!isset($graphe[$noeudCourant])) continue;

        foreach ($graphe[$noeudCourant] as $conn) {
            $voisine = $conn['vers'];

            if (isset($courant['visites'][$voisine])) continue;

            $poids = ($critere === 'distance') ? $conn['distance'] : $conn['duree'];

            $nouveauChemin = $courant['chemin'];
            $nouveauChemin[] = [
                'de'       => $noeudCourant,
                'vers'     => $voisine,
                'ligne'    => $conn['ligne'],
                'duree'    => $conn['duree'],
                'distance' => $conn['distance']
            ];

            $nouvellesVisites = $courant['visites'];
            $nouvellesVisites[$voisine] = true;

            $aTraiter[] = [
                'cout'    => $courant['cout'] + $poids,
                'noeud'   => $voisine,
                'chemin'  => $nouveauChemin,
                'visites' => $nouvellesVisites
            ];
        }
    }
    return $cheminsTrouves;
}
?>