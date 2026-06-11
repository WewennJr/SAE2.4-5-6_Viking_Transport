<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

// ── 1. Vérifier qu'un trajet a bien été recherché ──
if (!isset($_SESSION['trajet_a_reserver'])) {
    header("Location: recherche.php");
    exit;
}
$trajet = $_SESSION['trajet_a_reserver'];

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// ── 2. Déterminer le client ──
// Connecté -> son num + son type ; non connecté -> client 0, sans réduction
if (isset($_SESSION['num_client'])) {
    $cliNum = $_SESSION['num_client'];
    $stmtCli = $conn->prepare("SELECT TYP_NUM, CLI_NB_POINTS_TOT, CLI_NB_POINTS_EC FROM VIK_CLIENT WHERE CLI_NUM = :num");
    $stmtCli->execute([':num' => $cliNum]);
    $infoCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
    $typNum = $infoCli['TYP_NUM'];
} else {
    $cliNum = 0;          // client non inscrit
    $typNum = null;
}

// ── 3. Distance totale + tranche tarifaire + tarif de base ──
$distanceTot = $trajet['distance'];

// Trouver la tranche correspondant à la distance
$stmtTar = $conn->prepare("
    SELECT TAR_NUM_TRANCHE, TAR_PRIX
    FROM VIK_TARIF
    WHERE :dist >= TAR_MIN_DIST AND :dist <= TAR_MAX_DIST
");
$stmtTar->execute([':dist' => $distanceTot]);
$tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);

if (!$tarif) {
    // distance hors barème : on prend la tranche la plus haute
    $stmtMax = $conn->query("SELECT TAR_NUM_TRANCHE, TAR_PRIX FROM VIK_TARIF ORDER BY TAR_MAX_DIST DESC FETCH FIRST 1 ROWS ONLY");
    $tarif = $stmtMax->fetch(PDO::FETCH_ASSOC);
}
$numTranche = $tarif['TAR_NUM_TRANCHE'];
$tarifBase  = (float)$tarif['TAR_PRIX'];

// ── 4. Réduction selon le niveau du client ──
$pourcentagePaye = 100;   // par défaut : on paie 100% (non inscrit)
if ($typNum !== null) {
    $stmtRed = $conn->prepare("SELECT TYP_REDUC FROM VIK_TYPE_CLIENT WHERE TYP_NUM = :typ");
    $stmtRed->execute([':typ' => $typNum]);
    $niveau = $stmtRed->fetch(PDO::FETCH_ASSOC);
    if ($niveau) {
        $pourcentagePaye = (float)$niveau['TYP_REDUC'];   // ex. junior = 80 (paie 80%)
    }
}
$prixFinal = (int)round($tarifBase * $pourcentagePaye / 100);

// ── 5. Points gagnés (1 pt / 10 km, arrondi inférieur, min 1) ──
// Un client non inscrit ne gagne pas de points
$points = ($cliNum == 0) ? 0 : calculerPoints($distanceTot);

// ── 6 à 8. Insertion en TRANSACTION (tout ou rien) ──
try {
    $conn->beginTransaction();

    // Prochain RES_NUM pour CE client (clé composite)
    $stmtMaxRes = $conn->prepare("SELECT NVL(MAX(RES_NUM), 0) + 1 AS PROCHAIN FROM VIK_RESERVATION WHERE CLI_NUM = :num");
    $stmtMaxRes->execute([':num' => $cliNum]);
    $resNum = $stmtMaxRes->fetch(PDO::FETCH_ASSOC)['PROCHAIN'];

    // Insertion de la réservation
    $stmtInsRes = $conn->prepare("
        INSERT INTO VIK_RESERVATION (CLI_NUM, RES_NUM, TAR_NUM_TRANCHE, RES_DATE, RES_NB_POINTS, RES_PRIX_TOT)
        VALUES (:cli, :res, :tranche, TO_DATE(:dateres, 'YYYY-MM-DD'), :points, :prix)
    ");
    $stmtInsRes->execute([
        ':cli'     => $cliNum,
        ':res'     => $resNum,
        ':tranche' => $numTranche,
        ':dateres' => $trajet['date'],
        ':points'  => $points,
        ':prix'    => $prixFinal,
    ]);

    // Insertion des étapes (segments regroupés par ligne)
    $segments = regrouperParLigne($trajet['chemin']);
    $stmtInsEtape = $conn->prepare("
        INSERT INTO VIK_ETAPE (LIG_NUM, CLI_NUM, RES_NUM, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE, ETA_DISTANCE, ETA_HEURE)
        VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:dateres, 'YYYY-MM-DD'))
    ");
    foreach ($segments as $s) {
        $stmtInsEtape->execute([
            ':lig'     => $s['ligne'],
            ':cli'     => $cliNum,
            ':res'     => $resNum,
            ':dep'     => $s['depart'],
            ':arr'     => $s['arrivee'],
            ':dist'    => $s['distance'],
            ':dateres' => $trajet['date'],
        ]);
    }

    // Créditer les points au client (sauf non inscrit)
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

    // On nettoie la session et on garde un récap pour la page de confirmation
    unset($_SESSION['trajet_a_reserver']);
    $_SESSION['resa_confirmee'] = [
        'res_num'  => $resNum,
        'prix'     => $prixFinal,
        'points'   => $points,
        'distance' => $distanceTot,
    ];

    $conn = null;
    header("Location: confirmation_reservation.php");
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    $conn = null;
    // On stocke l'erreur pour l'afficher sur la page de recherche
    $_SESSION['erreur'] = "La réservation a échoué : " . $e->getMessage();
    header("Location: recherche.php");
    exit;
}
?>
