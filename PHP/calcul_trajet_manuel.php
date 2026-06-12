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

// On détaille chaque sous-trajet (avec ses lignes regroupées) pour l'affichage
$detail = [];
foreach ($resultat['sousTrajets'] as $st) {
    $segs = regrouperParLigne($st['chemin']);
    $lignes = [];
    foreach ($segs as $s) {
        $lignes[] = [
            'ligne'    => trim($s['ligne']),
            'depart'   => $noms[$s['depart']]  ?? $s['depart'],
            'arrivee'  => $noms[$s['arrivee']] ?? $s['arrivee'],
            'duree'    => $s['duree'],
            'distance' => $s['distance'],
        ];
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
    'sousTrajets'    => $detail,
    'dureeTotale'    => $resultat['dureeTotale'],
    'distanceTotale' => $dist,
    'prix'           => calculerTarif($conn, $dist),
    'points'         => calculerPoints($dist),
]);

$conn = null;