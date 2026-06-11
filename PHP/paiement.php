<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

// On doit avoir un trajet en attente, sinon retour à la recherche
if (!isset($_SESSION['trajet_a_reserver'])) {
    header("Location: recherche.php");
    exit;
}
$trajet = $_SESSION['trajet_a_reserver'];

// On recalcule le prix pour l'afficher (sans encore réserver)
$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$noms = chargerNomsCommunes($conn);

// Tranche tarifaire selon la distance
$stmtTar = $conn->prepare("SELECT TAR_PRIX FROM VIK_TARIF WHERE :dist >= TAR_MIN_DIST AND :dist <= TAR_MAX_DIST");
$stmtTar->execute([':dist' => $trajet['distance']]);
$tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);
$tarifBase = $tarif ? (float)$tarif['TAR_PRIX'] : 0;

// Réduction selon le niveau (si connecté)
$pourcentagePaye = 100;
$nomNiveau = "Non inscrit";
if (isset($_SESSION['num_client'])) {
    $stmtCli = $conn->prepare("
        SELECT t.TYP_REDUC, t.TYP_NOM
        FROM VIK_CLIENT c JOIN VIK_TYPE_CLIENT t ON c.TYP_NUM = t.TYP_NUM
        WHERE c.CLI_NUM = :num
    ");
    $stmtCli->execute([':num' => $_SESSION['num_client']]);
    $niv = $stmtCli->fetch(PDO::FETCH_ASSOC);
    if ($niv) {
        $pourcentagePaye = (float)$niv['TYP_REDUC'];
        $nomNiveau = $niv['TYP_NOM'];
    }
}
$prixFinal = (int)round($tarifBase * $pourcentagePaye / 100);

$conn = null;

$nomDep = $noms[$trajet['depart']]  ?? $trajet['depart'];
$nomArr = $noms[$trajet['arrivee']] ?? $trajet['arrivee'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/paiement.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <h1>Paiement</h1>

    <!-- Bandeau de simulation OBLIGATOIRE -->
    <div class="bandeau-simulation">
        ⚠️ Paiement fictif — Ceci est une démonstration. N'entrez pas de vraies coordonnées bancaires.
    </div>

    <div class="paiement-grille">
        <!-- Récapitulatif du trajet -->
        <section class="carte recap">
            <h2>Récapitulatif</h2>
            <p class="recap-trajet"><strong><?= htmlspecialchars($nomDep) ?></strong> → <strong><?= htmlspecialchars($nomArr) ?></strong></p>
            <p>Date : <?= htmlspecialchars($trajet['date']) ?></p>
            <p>Distance : <?= htmlspecialchars($trajet['distance']) ?> km</p>
            <p>Tarif de base : <?= $tarifBase ?> €</p>
            <p>Votre statut : <?= htmlspecialchars($nomNiveau) ?> (<?= $pourcentagePaye ?> %)</p>
            <hr>
            <p class="recap-total">Total à payer : <strong><?= $prixFinal ?> €</strong></p>
        </section>

        <!-- Faux formulaire de carte -->
        <section class="carte paiement-form">
            <h2>Coordonnées de paiement</h2>
            <form action="traitement_reservation.php" method="post">
                <div class="form-group">
                    <label for="titulaire">Titulaire de la carte</label>
                    <input type="text" id="titulaire" name="titulaire" placeholder="Jean Dupont" required>
                </div>
                <div class="form-group">
                    <label for="carte">Numéro de carte</label>
                    <input type="text" id="carte" name="carte" placeholder="4242 4242 4242 4242" maxlength="19" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiration">Expiration</label>
                        <input type="text" id="expiration" name="expiration" placeholder="MM/AA" maxlength="5" required>
                    </div>
                    <div class="form-group">
                        <label for="cvc">CVC</label>
                        <input type="text" id="cvc" name="cvc" placeholder="123" maxlength="3" required>
                    </div>
                </div>
                <input type="submit" value="Payer <?= $prixFinal ?> € et réserver">
            </form>
        </section>
    </div>
</main>

<?php include_once "footer.php"; ?>

</body>
</html>