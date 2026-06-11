<?php
session_start();

if (!isset($_SESSION['resa_confirmee'])) {
    header("Location: recherche.php");
    exit;
}
$resa = $_SESSION['resa_confirmee'];
// On ne nettoie PAS tout de suite : on garde le numéro pour le lien billet,
// mais on retire le récap après affichage pour éviter un re-affichage en cas de refresh.
unset($_SESSION['resa_confirmee']);

$estInscrit = ($resa['cli_num'] != 0);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation confirmée - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <h1>Merci de votre achat !</h1>

    <article>
        <h2>Votre voyage est réservé</h2>
        <p>Numéro de réservation : <strong>#<?= htmlspecialchars($resa['res_num']) ?></strong></p>
        <p>Distance : <strong><?= htmlspecialchars($resa['distance']) ?> km</strong></p>
        <p>Prix payé : <strong><?= htmlspecialchars($resa['prix']) ?> €</strong></p>
        <?php if ($resa['points'] > 0) { ?>
            <p>Points gagnés : <strong>+<?= htmlspecialchars($resa['points']) ?> points de fidélité</strong></p>
        <?php } else { ?>
            <p><em>Astuce : créez un compte pour gagner des points de fidélité sur vos prochains voyages !</em></p>
        <?php } ?>
    </article>

    <article>
        <p>
            <!-- Bouton billet : visible pour tous (inscrit OU non inscrit) -->
            <a href="billet.php?res=<?= htmlspecialchars($resa['res_num']) ?>" class="btn">🎫 Télécharger mon billet</a>

            <?php if ($estInscrit) { ?>
                <a href="espace_client.php" class="btn">Voir mes réservations</a>
            <?php } ?>
            <a href="recherche.php" class="btn">Réserver un autre voyage</a>
        </p>

        <?php if (!$estInscrit) { ?>
            <p class="note-invite">
                ⚠️ Pensez à télécharger votre billet maintenant : sans compte, vous ne pourrez plus y accéder après avoir quitté le site.
            </p>
        <?php } ?>
    </article>
</main>

<?php include_once "footer.php"; ?>

</body>
</html>