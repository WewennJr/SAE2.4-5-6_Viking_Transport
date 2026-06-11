<?php
session_start();

// Si on arrive ici sans avoir réservé, on renvoie à la recherche
if (!isset($_SESSION['resa_confirmee'])) {
    header("Location: recherche.php");
    exit;
}
$resa = $_SESSION['resa_confirmee'];
unset($_SESSION['resa_confirmee']);   // on l'affiche une seule fois
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
    <h1>Réservation confirmée !</h1>

    <article>
        <h2>Merci, votre voyage est réservé</h2>
        <p>Numéro de réservation : <strong>#<?= htmlspecialchars($resa['res_num']) ?></strong></p>
        <p>Distance : <strong><?= htmlspecialchars($resa['distance']) ?> km</strong></p>
        <p>Prix payé : <strong><?= htmlspecialchars($resa['prix']) ?> €</strong></p>
        <?php if ($resa['points'] > 0) { ?>
            <p>Points gagnés : <strong>+<?= htmlspecialchars($resa['points']) ?> points de fidélité</strong></p>
        <?php } else { ?>
            <p><em>Créez un compte pour gagner des points de fidélité sur vos prochains voyages !</em></p>
        <?php } ?>
    </article>

    <article>
        <p>
            <a href="recherche.php" class="btn">Réserver un autre voyage</a>
            <?php if (isset($_SESSION['num_client'])) { ?>
                <a href="espace_client.php" class="btn">Voir mes réservations</a>
            <?php } ?>
        </p>
    </article>
</main>

<?php include_once "footer.php"; ?>

</body>
</html>
