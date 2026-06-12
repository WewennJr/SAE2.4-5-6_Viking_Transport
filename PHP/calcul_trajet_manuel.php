<?php
// ════════════════════════════════════════════════════════════
// Endpoint AJAX : reçoit une liste de communes, renvoie le détail
// du trajet composé (sous-trajets, durée, distance, prix) en JSON.
// Appelé par le JavaScript de reservation_manuelle.php.
// ════════════════════════════════════════════════════════════
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

header('Content-Type: application/json; charset=utf-8');

// On lit le corps de la requête (envoyé en JSON par fetch)
$input = json_decode(file_get_contents('php://input'), true);
$communes = $input['communes'] ?? [];
$heureDep = $input['heure'] ?? '08:00';   // heure de départ souhaitée

// On retire les valeurs vides et on garde l'ordre
$communes = array_values(array_filter($communes, function ($c) {
    return $c !== '' && $c !== null;
}));

if (count($communes) < 2) {
    echo json_encode(['ok' => false, 'message' => 'Choisissez au moins un départ et une arrivée.']);
    exit;
}

$conn   = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$graphe = chargerGraphe($conn);
$noms   = chargerNomsCommunes($conn);

$resultat = composerTrajetManuel($graphe, $communes, 'duree');

if (isset($resultat['erreur'])) {
    echo json_encode(['ok' => false, 'message' => $resultat['erreur']]);
    $conn = null;
    exit;
}

// On construit la liste des segments dans l'ordre exact où ils seront affichés
// (segments de chaque sous-trajet, sans refusionner entre sous-trajets).
// Puis on calcule les horaires sur CETTE liste précise : l'ordre correspond pile.
$tousSegments = [];
$decoupage = [];   // mémorise combien de segments par sous-trajet
foreach ($resultat['sousTrajets'] as $st) {
    $segs = regrouperParLigne($st['chemin']);
    $decoupage[] = count($segs);
    foreach ($segs as $s) {
        $tousSegments[] = $s;
    }
}

$horaires = calculerHorairesTrajet($conn, $tousSegments, hhmmEnMinutes($heureDep));
$etapesHoraires = $horaires['etapes'];
$idxHoraire = 0;

// On détaille chaque sous-trajet (avec ses lignes regroupées) pour l'affichage
$detail = [];
foreach ($resultat['sousTrajets'] as $st) {
    $segs = regrouperParLigne($st['chemin']);
    $lignes = [];
    foreach ($segs as $s) {
        // On prend l'horaire à la position courante (même ordre garanti)
        $h = $etapesHoraires[$idxHoraire] ?? null;
        $idxHoraire++;

        $ligneInfo = [
            'ligne'    => trim($s['ligne']),
            'depart'   => $noms[$s['depart']]  ?? $s['depart'],
            'arrivee'  => $noms[$s['arrivee']] ?? $s['arrivee'],
            'duree'    => $s['duree'],
            'distance' => $s['distance'],
            'heureDep' => null,
            'heureArr' => null,
            'attente'  => 0,
            'realisable' => true,
        ];
        if ($h !== null && !empty($h['realisable'])) {
            $ligneInfo['heureDep'] = minutesEnHhmm($h['depart']);
            $ligneInfo['heureArr'] = minutesEnHhmm($h['arrivee']);
            $ligneInfo['attente']  = $h['attente'];
        } elseif ($h !== null) {
            $ligneInfo['realisable'] = false;
        }
        $lignes[] = $ligneInfo;
    }
    $detail[] = [
        'depart'   => $noms[$st['de']]   ?? $st['de'],
        'arrivee'  => $noms[$st['vers']] ?? $st['vers'],
        'duree'    => $st['duree'],
        'distance' => $st['distance'],
        'lignes'   => $lignes,
    ];
}

$dist = $resultat['distanceTotale'];

echo json_encode([
    'ok'             => true,
    'realisable'     => $horaires['realisable'],
    'sousTrajets'    => $detail,
    'dureeTotale'    => $resultat['dureeTotale'],
    'distanceTotale' => $dist,
    'prix'           => calculerTarif($conn, $dist),
    'points'         => calculerPoints($dist),
]);

$conn = null;