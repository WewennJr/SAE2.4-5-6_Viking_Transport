<?php
session_start();
$connecte = isset($_SESSION["num_client"]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Viking Transport">
    <title>Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>

    <?php include_once("../PHP/nav.php"); ?>

    <main id="accueil">
        <h1>Viking Transport</h1>

        <?php if ($connecte) { ?>
            <p>Content de vous revoir, <?= htmlspecialchars($_SESSION["prenom"]) ?> !</p>
        <?php } ?>

        <article>
            <img id="viking-image" src="../images/viking.png" alt="équipe de vikings">
            <h2>Bienvenue sur le site de Viking Transport</h2>
            <p>Si vous cherchez des lignes de transport dans la Normandie, à un prix plus que raisonnable, nous sommes ravis de vous accueillir !</p>
        </article>

        <article>
            <h2>Nos lignes</h2>
            <img id="carte-image" src="../images/carte.png" alt="carte des lignes">
        </article>

        <article>
            <h2>Que souhaitez-vous faire ?</h2>
            <ul>
                <li><a href="../PHP/recherche.html">Voir les lignes et horaires</a></li>
                <li><a href="../PHP/recherche.html">Acheter un billet</a></li>
                <?php if ($connecte) { ?>
                    <li><a href="../PHP/recherche.html">Rechercher un trajet (coût, durée, attente)</a></li>
                    <li><a href="../PHP/points.php">Voir mes points fidélité</a></li>
                    <li><a href="../PHP/historique.php">Voir l'historique de mes voyages</a></li>
                    <li><a href="../PHP/compte.php">Modifier mon compte</a></li>
                <?php } else { ?>
                    <li><a href="../PHP/inscription.php">Créer un compte pour gagner des points</a></li>
                <?php } ?>
            </ul>
        </article>
    </main>

    <?php include_once("../PHP/footer.php"); ?>
</body>
</html>