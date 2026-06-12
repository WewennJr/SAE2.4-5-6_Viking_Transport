<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

if (!isset($_SESSION['trajet_a_reserver'])) {
    header("Location: recherche.php");
    exit;
}
$trajet = $_SESSION['trajet_a_reserver'];

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// ── Déterminer le client ──
if (isset($_SESSION['num_client'])) {
    $cliNum = $_SESSION['num_client'];
    $stmtCli = $conn->prepare("SELECT TYP_NUM FROM VIK_CLIENT WHERE CLI_NUM = :num");
    $stmtCli->execute([':num' => $cliNum]);
    $infoCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
    $typNum = $infoCli['TYP_NUM'];
    $nomVoyageur = null;   // inscrit : on prendra son nom depuis la base
} else {
    $cliNum = 0;           // client invité unique
    $typNum = null;
    // Nom saisi sur la page de paiement (pour le billet)
    $prenom = trim($_POST['v_prenom'] ?? 'Voyageur');
    $nom    = trim($_POST['v_nom'] ?? 'invité');
    $email  = trim($_POST['v_email'] ?? '');
    $nomVoyageur = $prenom . ' ' . $nom;
}

// ── Tarif de base selon la distance ──
$distanceTot = $trajet['distance'];
$stmtTar = $conn->prepare("SELECT TAR_NUM_TRANCHE, TAR_PRIX FROM VIK_TARIF WHERE :dist1 >= TAR_MIN_DIST AND :dist2 <= TAR_MAX_DIST");
$stmtTar->execute([':dist1' => $distanceTot, ':dist2' => $distanceTot]);
$tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);
if ($tarif === false || $tarif === null) {
    // Au-delà de toutes les tranches : on prend la tranche la plus haute.
    // TO_NUMBER force un tri numérique (sinon "80" passerait avant "500" en texte).
    $stmtMax = $conn->query("SELECT TAR_NUM_TRANCHE, TAR_PRIX FROM VIK_TARIF ORDER BY TO_NUMBER(TAR_MAX_DIST) DESC FETCH FIRST 1 ROWS ONLY");
    $tarif = $stmtMax->fetch(PDO::FETCH_ASSOC);
}
$numTranche = $tarif['TAR_NUM_TRANCHE'];
$tarifBase  = (float)$tarif['TAR_PRIX'];

// ── Réduction selon le niveau ──
$pourcentagePaye = 100;
if ($typNum !== null) {
    $stmtRed = $conn->prepare("SELECT TYP_REDUC FROM VIK_TYPE_CLIENT WHERE TYP_NUM = :typ");
    $stmtRed->execute([':typ' => $typNum]);
    $niveau = $stmtRed->fetch(PDO::FETCH_ASSOC);
    if ($niveau) $pourcentagePaye = (float)$niveau['TYP_REDUC'];
}
$prixFinal = (int)round($tarifBase * $pourcentagePaye / 100);

// ── Réduction par points (priorité 12) ──
// Le client a choisi un palier (RED_NB_POINTS) parmi les 3 de VIK_REDUCTION, ou 0.
// SÉCURITÉ : on ne fait JAMAIS confiance au formulaire. On revérifie en base
// que la réduction existe ET que le client a assez de points utilisables.
$pointsUtilises = 0;
$valeurReduc    = 0;
$choixReduc = (int)($_POST['reduction_points'] ?? 0);

if ($cliNum != 0 && $choixReduc > 0) {
    // La réduction demandée existe-t-elle dans la table ?
    $stmtVerifRed = $conn->prepare("SELECT RED_VALEUR FROM VIK_REDUCTION WHERE RED_NB_POINTS = :nb");
    $stmtVerifRed->execute([':nb' => $choixReduc]);
    $red = $stmtVerifRed->fetch(PDO::FETCH_ASSOC);

    // Le client a-t-il assez de points utilisables ?
    $stmtPts = $conn->prepare("SELECT CLI_NB_POINTS_EC FROM VIK_CLIENT WHERE CLI_NUM = :num");
    $stmtPts->execute([':num' => $cliNum]);
    $soldePts = (int)$stmtPts->fetch(PDO::FETCH_ASSOC)['CLI_NB_POINTS_EC'];

    if ($red && $soldePts >= $choixReduc) {
        $pointsUtilises = $choixReduc;
        $valeurReduc    = (int)$red['RED_VALEUR'];
        $prixFinal     -= $valeurReduc;
        if ($prixFinal < 0) $prixFinal = 0;   // jamais négatif
    }
    // Sinon : choix ignoré (client triche ou points insuffisants), prix inchangé
}

// ── Points (non inscrit = 0) ──
$points = ($cliNum == 0) ? 0 : calculerPoints($distanceTot);

// ── Insertion en TRANSACTION ──
try {
    $conn->beginTransaction();

    $stmtMaxRes = $conn->prepare("SELECT NVL(MAX(RES_NUM), 0) + 1 AS PROCHAIN FROM VIK_RESERVATION WHERE CLI_NUM = :num");
    $stmtMaxRes->execute([':num' => $cliNum]);
    $resNum = $stmtMaxRes->fetch(PDO::FETCH_ASSOC)['PROCHAIN'];

    $stmtInsRes = $conn->prepare("
        INSERT INTO VIK_RESERVATION (CLI_NUM, RES_NUM, TAR_NUM_TRANCHE, RES_DATE, RES_NB_POINTS, RES_PRIX_TOT)
        VALUES (:cli, :res, :tranche, TO_DATE(:dateres, 'YYYY-MM-DD'), :points, :prix)
    ");
    $stmtInsRes->execute([
        ':cli' => $cliNum, ':res' => $resNum, ':tranche' => $numTranche,
        ':dateres' => $trajet['date'], ':points' => $points, ':prix' => $prixFinal,
    ]);

    $segments = regrouperParLigne($trajet['chemin']);

    // Horaires calculés (heure de départ réelle de chaque segment), s'ils existent
    $horaires = $trajet['horaires']['etapes'] ?? [];

    $stmtInsEtape = $conn->prepare("
        INSERT INTO VIK_ETAPE (LIG_NUM, CLI_NUM, RES_NUM, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE, ETA_DISTANCE, ETA_HEURE)
        VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:dateheure, 'YYYY-MM-DD HH24:MI'))
    ");
    foreach ($segments as $idx => $s) {
        // Heure de départ du segment (HH:MM) si disponible, sinon 00:00
        $heureSeg = '00:00';
        if (isset($horaires[$idx]) && !empty($horaires[$idx]['realisable']) && isset($horaires[$idx]['depart'])) {
            $heureSeg = minutesEnHhmm($horaires[$idx]['depart']);   // ex "08h32"
            $heureSeg = str_replace('h', ':', $heureSeg);           // -> "08:32" pour TO_DATE
        }
        $dateHeure = $trajet['date'] . ' ' . $heureSeg;   // ex "2026-06-15 08:32"

        $stmtInsEtape->execute([
            ':lig' => $s['ligne'], ':cli' => $cliNum, ':res' => $resNum,
            ':dep' => $s['depart'], ':arr' => $s['arrivee'],
            ':dist' => $s['distance'], ':dateheure' => $dateHeure,
        ]);
    }

    if ($cliNum != 0) {
        // CLI_NB_POINTS_TOT : on ajoute seulement les points gagnés (cumul pour le niveau, jamais débité)
        // CLI_NB_POINTS_EC  : on ajoute les gagnés ET on retire ceux utilisés pour la réduction
        $stmtMajPts = $conn->prepare("
            UPDATE VIK_CLIENT
            SET CLI_NB_POINTS_TOT = CLI_NB_POINTS_TOT + :ptsTot,
                CLI_NB_POINTS_EC  = CLI_NB_POINTS_EC + :ptsGagnes - :ptsUtilises
            WHERE CLI_NUM = :num
        ");
        $stmtMajPts->execute([
            ':ptsTot'      => $points,
            ':ptsGagnes'   => $points,
            ':ptsUtilises' => $pointsUtilises,
            ':num'         => $cliNum,
        ]);
    }

    $conn->commit();

    unset($_SESSION['trajet_a_reserver']);
    $_SESSION['resa_confirmee'] = [
        'cli_num' => $cliNum, 'res_num' => $resNum,
        'prix' => $prixFinal, 'points' => $points, 'distance' => $distanceTot,
    ];

    // Non-inscrit : on autorise le billet ET on garde le nom saisi pour l'afficher
    if ($cliNum == 0) {
        if (!isset($_SESSION['billets_invite'])) $_SESSION['billets_invite'] = [];
        $_SESSION['billets_invite'][] = $resNum;
        // On stocke le nom du voyageur indexé par numéro de réservation
        if (!isset($_SESSION['noms_invite'])) $_SESSION['noms_invite'] = [];
        $_SESSION['noms_invite'][$resNum] = $nomVoyageur;
    }

    $conn = null;
    header("Location: confirmation_reservation.php");
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    $conn = null;
    $_SESSION['erreur'] = "La réservation a échoué : " . $e->getMessage();
    header("Location: recherche.php");
    exit;
}
?>