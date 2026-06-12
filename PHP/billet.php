<?php
session_start();

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$resNum = $_GET['res'] ?? null;
if ($resNum === null) {
    header("Location: index.php");
    exit;
}

// ── Détermination du client et contrôle d'accès ──
// Cas 1 : client connecté -> il peut voir SES réservations
// Cas 2 : non inscrit -> il peut voir un billet (client 0) SEULEMENT si ce
//         numéro de réservation est marqué dans sa session (il vient de l'acheter)
if (isset($_SESSION['num_client'])) {
    $cliNum = $_SESSION['num_client'];
} elseif (isset($_SESSION['billets_invite']) && in_array($resNum, $_SESSION['billets_invite'])) {
    $cliNum = 0;   // billet d'un non-inscrit, autorisé pour cette session
} else {
    // Ni connecté, ni billet invité autorisé -> accès refusé
    header("Location: index.php");
    exit;
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// ── Infos de la réservation ──
// Pour un inscrit on a son nom ; pour le client 0, pas de nom en base
if ($cliNum != 0) {
    $stmtResa = $conn->prepare("
        SELECT r.RES_NUM, r.RES_DATE, r.RES_PRIX_TOT,
               c.CLI_NOM, c.CLI_PRENOM
        FROM VIK_RESERVATION r
        JOIN VIK_CLIENT c ON r.CLI_NUM = c.CLI_NUM
        WHERE r.CLI_NUM = :cli AND r.RES_NUM = :res
    ");
} else {
    $stmtResa = $conn->prepare("
        SELECT RES_NUM, RES_DATE, RES_PRIX_TOT
        FROM VIK_RESERVATION
        WHERE CLI_NUM = :cli AND RES_NUM = :res
    ");
}
$stmtResa->execute([':cli' => $cliNum, ':res' => $resNum]);
$resa = $stmtResa->fetch(PDO::FETCH_ASSOC);

// Pour un invité, on récupère le nom saisi (stocké en session). Sinon valeur par défaut.
if ($cliNum == 0) {
    $nomComplet = $_SESSION['noms_invite'][$resNum] ?? 'Voyageur invité';
    $resa['CLI_PRENOM'] = $nomComplet;
    $resa['CLI_NOM'] = '';
}

if (!$resa) {
    $conn = null;
    header("Location: index.php");
    exit;
}

// ── Étapes détaillées ──
$stmtEtapes = $conn->prepare("
    SELECT e.LIG_NUM, e.ETA_DISTANCE,
           cd.COM_NOM AS DEPART,
           ca.COM_NOM AS ARRIVEE,
           TO_CHAR(e.ETA_HEURE, 'HH24:MI') AS HEURE_DEP
    FROM VIK_ETAPE e
    JOIN VIK_COMMUNE cd ON e.COM_CODE_INSEE_DEPART  = cd.COM_CODE_INSEE
    JOIN VIK_COMMUNE ca ON e.COM_CODE_INSEE_ARRIVEE = ca.COM_CODE_INSEE
    WHERE e.CLI_NUM = :cli AND e.RES_NUM = :res
    ORDER BY e.ETA_HEURE
");
$stmtEtapes->execute([':cli' => $cliNum, ':res' => $resNum]);
$etapes = $stmtEtapes->fetchAll(PDO::FETCH_ASSOC);

// ── Départ et arrivée globaux du voyage ──
$stmtDA = $conn->prepare("
    SELECT
        (SELECT c.COM_NOM FROM VIK_ETAPE e
         JOIN VIK_COMMUNE c ON e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE
         WHERE e.CLI_NUM = :cli1 AND e.RES_NUM = :res1
           AND e.COM_CODE_INSEE_DEPART NOT IN (
               SELECT e2.COM_CODE_INSEE_ARRIVEE FROM VIK_ETAPE e2
               WHERE e2.CLI_NUM = :cli2 AND e2.RES_NUM = :res2)
           AND ROWNUM = 1) AS DEPART,
        (SELECT c.COM_NOM FROM VIK_ETAPE e
         JOIN VIK_COMMUNE c ON e.COM_CODE_INSEE_ARRIVEE = c.COM_CODE_INSEE
         WHERE e.CLI_NUM = :cli3 AND e.RES_NUM = :res3
           AND e.COM_CODE_INSEE_ARRIVEE NOT IN (
               SELECT e2.COM_CODE_INSEE_DEPART FROM VIK_ETAPE e2
               WHERE e2.CLI_NUM = :cli4 AND e2.RES_NUM = :res4)
           AND ROWNUM = 1) AS ARRIVEE
    FROM DUAL
");
$stmtDA->execute([
    ':cli1' => $cliNum, ':res1' => $resNum,
    ':cli2' => $cliNum, ':res2' => $resNum,
    ':cli3' => $cliNum, ':res3' => $resNum,
    ':cli4' => $cliNum, ':res4' => $resNum,
]);
$da = $stmtDA->fetch(PDO::FETCH_ASSOC);
$villeDepart  = $da['DEPART']  ?? '-';
$villeArrivee = $da['ARRIVEE'] ?? '-';

$distanceTotale = 0;
foreach ($etapes as $e) $distanceTotale += $e['ETA_DISTANCE'];

$conn = null;

$contenuQR = "https://wewennjr.github.io/test-api-publique-resume-billet/";
$urlQR = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($contenuQR);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billet #<?= htmlspecialchars($resa['RES_NUM']) ?> - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/billet.css">
</head>
<body>

<div class="billet-actions">
    <?php if (isset($_SESSION['num_client'])) { ?>
        <a href="espace_client.php" class="btn-retour">← Retour à mon espace</a>
    <?php } else { ?>
        <a href="index.php" class="btn-retour">← Accueil</a>
    <?php } ?>
    <button onclick="window.print()" class="btn-imprimer">🖶 Télécharger / Imprimer le billet</button>
</div>

<div class="billet">
    <div class="billet-entete">
        <div class="billet-logo">VIKING TRANSPORT</div>
        <div class="billet-numero">Billet n°<?= htmlspecialchars($resa['RES_NUM']) ?></div>
    </div>

    <div class="billet-corps">
        <div class="billet-infos">
            <div class="billet-trajet-principal">
                <div class="ville">
                    <span class="ville-label">Départ</span>
                    <span class="ville-nom"><?= htmlspecialchars($villeDepart) ?></span>
                </div>
                <div class="fleche">→</div>
                <div class="ville">
                    <span class="ville-label">Arrivée</span>
                    <span class="ville-nom"><?= htmlspecialchars($villeArrivee) ?></span>
                </div>
            </div>

            <div class="billet-details">
                <div class="detail">
                    <span class="detail-label">Passager</span>
                    <span class="detail-valeur"><?= htmlspecialchars($resa['CLI_PRENOM'] . ' ' . $resa['CLI_NOM']) ?></span>
                </div>
                <div class="detail">
                    <span class="detail-label">Date du voyage</span>
                    <span class="detail-valeur"><?= htmlspecialchars($resa['RES_DATE']) ?></span>
                </div>
                <div class="detail">
                    <span class="detail-label">Distance</span>
                    <span class="detail-valeur"><?= htmlspecialchars($distanceTotale) ?> km</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Prix payé</span>
                    <span class="detail-valeur"><?= htmlspecialchars($resa['RES_PRIX_TOT']) ?> €</span>
                </div>
            </div>
        </div>

        <div class="billet-qr">
            <img src="<?= htmlspecialchars($urlQR) ?>" alt="QR code du billet">
            <span class="qr-legende">Présentez ce code<br>au contrôleur</span>
        </div>
    </div>

    <div class="billet-etapes">
        <h3>Détail de votre trajet</h3>
        <ol>
            <?php foreach ($etapes as $e) {
                $heureDep = $e['HEURE_DEP'] ?? '00:00';
                $afficheHeure = ($heureDep !== '00:00');
            ?>
                <li>
                    <?php if ($afficheHeure) { ?>
                        <span class="etape-heure"><?= htmlspecialchars(str_replace(':', 'h', $heureDep)) ?></span>
                    <?php } ?>
                    <span class="etape-ligne">Ligne <?= htmlspecialchars(trim($e['LIG_NUM'])) ?></span>
                    <span class="etape-villes"><?= htmlspecialchars($e['DEPART']) ?> → <?= htmlspecialchars($e['ARRIVEE']) ?></span>
                    <span class="etape-dist"><?= htmlspecialchars($e['ETA_DISTANCE']) ?> km</span>
                </li>
            <?php } ?>
        </ol>
    </div>

    <div class="billet-pied">
        Merci de voyager avec Viking Transport · Billet valable pour la date indiquée
    </div>
</div>

</body>
</html>