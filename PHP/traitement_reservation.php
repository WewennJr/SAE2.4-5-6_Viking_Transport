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
$stmtTar = $conn->prepare("SELECT TAR_NUM_TRANCHE, TAR_PRIX FROM VIK_TARIF WHERE :dist >= TAR_MIN_DIST AND :dist <= TAR_MAX_DIST");
$stmtTar->execute([':dist' => $distanceTot]);
$tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);
if (!$tarif) {
    $stmtMax = $conn->query("SELECT TAR_NUM_TRANCHE, TAR_PRIX FROM VIK_TARIF ORDER BY TAR_MAX_DIST DESC FETCH FIRST 1 ROWS ONLY");
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
    $stmtInsEtape = $conn->prepare("
        INSERT INTO VIK_ETAPE (LIG_NUM, CLI_NUM, RES_NUM, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE, ETA_DISTANCE, ETA_HEURE)
        VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:dateres, 'YYYY-MM-DD'))
    ");
    foreach ($segments as $s) {
        $stmtInsEtape->execute([
            ':lig' => $s['ligne'], ':cli' => $cliNum, ':res' => $resNum,
            ':dep' => $s['depart'], ':arr' => $s['arrivee'],
            ':dist' => $s['distance'], ':dateres' => $trajet['date'],
        ]);
    }

    if ($cliNum != 0) {
        $stmtMajPts = $conn->prepare("
            UPDATE VIK_CLIENT
            SET CLI_NB_POINTS_TOT = CLI_NB_POINTS_TOT + :pts,
                CLI_NB_POINTS_EC  = CLI_NB_POINTS_EC + :pts2
            WHERE CLI_NUM = :num
        ");
        $stmtMajPts->execute([':pts' => $points, ':pts2' => $points, ':num' => $cliNum]);
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