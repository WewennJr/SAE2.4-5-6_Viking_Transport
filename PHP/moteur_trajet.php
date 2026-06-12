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

// ════════════════════════════════════════════════════════════
// Trouve plusieurs trajets distincts entre deux communes.
// Méthode : exploration par interdiction d'arêtes, avec une file
// de priorité pour générer des alternatives de plus en plus variées.
// ════════════════════════════════════════════════════════════
function trouverKTrajets($graphe, $depart, $arrivee, $k = 5, $critere = 'duree')
{
    $premier = trouverTrajet($graphe, $depart, $arrivee, $critere);
    if ($premier === null) return [];

    $resultats = [];                                  // trajets retenus
    $vus = [];                                        // signatures déjà retenues
    $sig0 = signatureChemin($premier['chemin']);
    $resultats[] = $premier;
    $vus[$sig0] = true;

    // File de candidats : chaque entrée = un ensemble d'arêtes à interdire
    // On part du meilleur trajet : on génère un candidat par arête interdite.
    $candidats = [];
    foreach ($premier['chemin'] as $arete) {
        $candidats[] = [
            'interdites' => [$arete['de'] . '>' . $arete['vers']],
        ];
    }

    $maxIterations = 200;   // garde-fou contre les boucles trop longues
    $iter = 0;

    while (!empty($candidats) && count($resultats) < $k && $iter < $maxIterations) {
        $iter++;
        $cand = array_shift($candidats);

        // On construit un graphe où toutes les arêtes interdites sont retirées
        $grapheMod = $graphe;
        foreach ($cand['interdites'] as $arc) {
            list($de, $vers) = explode('>', $arc);
            if (isset($grapheMod[$de])) {
                $grapheMod[$de] = array_values(array_filter(
                    $grapheMod[$de],
                    function ($a) use ($vers) { return $a['vers'] !== $vers; }
                ));
            }
        }

        // On cherche le meilleur trajet dans ce graphe restreint
        $alt = trouverTrajet($grapheMod, $depart, $arrivee, $critere);
        if ($alt === null) continue;

        $sig = signatureChemin($alt['chemin']);
        if (isset($vus[$sig])) continue;   // déjà trouvé

        // Nouveau trajet distinct : on le retient
        $resultats[] = $alt;
        $vus[$sig] = true;

        // On génère de nouveaux candidats : interdire EN PLUS chaque arête de ce trajet
        foreach ($alt['chemin'] as $arete) {
            $arc = $arete['de'] . '>' . $arete['vers'];
            if (!in_array($arc, $cand['interdites'])) {
                $candidats[] = [
                    'interdites' => array_merge($cand['interdites'], [$arc]),
                ];
            }
        }
    }

    // Tri final selon le critère
    usort($resultats, function ($a, $b) use ($critere) {
        $ca = ($critere === 'distance') ? $a['distanceTotale'] : $a['dureeTotale'];
        $cb = ($critere === 'distance') ? $b['distanceTotale'] : $b['dureeTotale'];
        return $ca <=> $cb;
    });

    return array_slice($resultats, 0, $k);
}

// Signature unique d'un chemin (pour détecter les doublons)
function signatureChemin($chemin)
{
    $sig = '';
    foreach ($chemin as $e) $sig .= $e['de'] . '>' . $e['vers'] . '|';
    return $sig;
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

// ════════════════════════════════════════════════════════════
// HORAIRES avec correspondances réelles (priorité 18)
// ════════════════════════════════════════════════════════════

// Convertit "HH:MM" en minutes depuis minuit (ex: "05:36" -> 336)
function hhmmEnMinutes($hhmm)
{
    list($h, $m) = explode(':', $hhmm);
    return (int)$h * 60 + (int)$m;
}

// Convertit des minutes depuis minuit en "HHhMM" (ex: 336 -> "05h36")
function minutesEnHhmm($minutes)
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return str_pad($h, 2, '0', STR_PAD_LEFT) . 'h' . str_pad($m, 2, '0', STR_PAD_LEFT);
}

// Calcule les horaires réels d'un trajet à partir d'une heure de départ souhaitée.
// Pour chaque segment : on prend le 1er car qui part APRÈS l'heure courante,
// on calcule l'arrivée (départ + durée), et l'attente = départ - arrivée précédente.
// $segments : résultat de regrouperParLigne (depart, arrivee, ligne, duree, distance)
// $heureSouhaitee : minutes depuis minuit (ex: 480 pour 8h00)
// Renvoie un tableau avec pour chaque segment : depart, arrivee, attente (minutes),
// et un flag 'realisable' global.
function calculerHorairesTrajet($conn, $segments, $heureSouhaitee)
{
    $stmt = $conn->prepare("
        SELECT DISTINCT TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') AS H
        FROM VIK_NOEUD
        WHERE TRIM(LIG_NUM) = :lig AND TRIM(COM_CODE_INSEE_ARRET) = :arret
        ORDER BY 1
    ");

    $etapes = [];
    $heureCourante = $heureSouhaitee;
    $realisable = true;

    foreach ($segments as $s) {
        $stmt->execute([':lig' => trim($s['ligne']), ':arret' => trim($s['depart'])]);
        $heures = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Premier départ disponible >= heure courante
        $heureDep = null;
        foreach ($heures as $h) {
            $min = hhmmEnMinutes($h);
            if ($min >= $heureCourante) { $heureDep = $min; break; }
        }

        if ($heureDep === null) {
            // Plus aucun car pour ce segment après l'heure courante
            $etapes[] = ['segment' => $s, 'realisable' => false];
            $realisable = false;
            break;
        }

        $attente  = $heureDep - $heureCourante;
        $heureArr = $heureDep + (int)$s['duree'];

        $etapes[] = [
            'segment'  => $s,
            'depart'   => $heureDep,
            'arrivee'  => $heureArr,
            'attente'  => $attente,
            'realisable' => true,
        ];

        $heureCourante = $heureArr;
    }

    return [
        'etapes'     => $etapes,
        'realisable' => $realisable,
        'arriveeFinale' => $realisable ? $heureCourante : null,
    ];
}

// ════════════════════════════════════════════════════════════
// TRAJET MANUEL : l'utilisateur choisit une suite de communes.
// Entre chaque paire consécutive, on calcule le meilleur sous-trajet
// (qui peut emprunter plusieurs lignes), puis on concatène le tout.
// $communes : liste ordonnée de codes INSEE [C0, C1, ..., Cn]
// Renvoie le chemin complet + totaux, ou ['erreur' => msg] si impossible.
// ════════════════════════════════════════════════════════════
function composerTrajetManuel($graphe, $communes, $critere = 'duree')
{
    if (count($communes) < 2) {
        return ['erreur' => "Il faut au moins un départ et une arrivée."];
    }

    $cheminComplet = [];
    $sousTrajets   = [];

    for ($i = 0; $i < count($communes) - 1; $i++) {
        $de   = trim($communes[$i]);
        $vers = trim($communes[$i + 1]);

        if ($de === $vers) {
            return ['erreur' => "Deux arrêts qui se suivent sont identiques."];
        }

        $t = trouverTrajet($graphe, $de, $vers, $critere);
        if ($t === null) {
            return ['erreur' => "Aucun trajet possible entre ces deux arrêts."];
        }

        $cheminComplet = array_merge($cheminComplet, $t['chemin']);
        $sousTrajets[] = [
            'de'       => $de,
            'vers'     => $vers,
            'chemin'   => $t['chemin'],
            'duree'    => $t['dureeTotale'],
            'distance' => $t['distanceTotale'],
        ];
    }

    $dureeTot = 0; $distTot = 0;
    foreach ($cheminComplet as $e) {
        $dureeTot += $e['duree'];
        $distTot  += $e['distance'];
    }

    return [
        'chemin'         => $cheminComplet,
        'sousTrajets'    => $sousTrajets,
        'dureeTotale'    => $dureeTot,
        'distanceTotale' => $distTot,
    ];
}

function calculerPoints($distance)
{
    return max(1, (int)floor($distance / 10));
}

// Prix de base d'un trajet selon sa distance totale (table VIK_TARIF).
// Renvoie le prix de la tranche correspondante ; si la distance dépasse
// toutes les tranches, on prend la tranche la plus haute.
// Prix de base d'un trajet selon sa distance totale (table VIK_TARIF).
// Si la distance dépasse toutes les tranches (> 500 km), on plafonne au tarif
// de la tranche la plus élevée.
function calculerTarif($conn, $distance)
{
    $stmt = $conn->prepare("
        SELECT TAR_PRIX FROM VIK_TARIF
        WHERE :dist1 >= TAR_MIN_DIST AND :dist2 <= TAR_MAX_DIST
    ");
    $stmt->execute([':dist1' => $distance, ':dist2' => $distance]);
    $tarif = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tarif !== false && $tarif !== null) {
        return (int)$tarif['TAR_PRIX'];
    }

    // Aucune tranche : on prend la tranche dont la distance max est la plus grande.
    // TO_NUMBER force un tri numérique (sinon "80" passerait avant "500" en texte).
    $stmtMax = $conn->query("
        SELECT TAR_PRIX FROM VIK_TARIF
        ORDER BY TO_NUMBER(TAR_MAX_DIST) DESC
        FETCH FIRST 1 ROWS ONLY
    ");
    $max = $stmtMax->fetch(PDO::FETCH_ASSOC);
    return ($max !== false && $max !== null) ? (int)$max['TAR_PRIX'] : 0;
}
?>