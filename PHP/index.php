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
    <link rel="stylesheet" href="../CSS/accueil.css">
</head>
<body>

    <?php include_once("../PHP/nav.php"); ?>

    <?php if ($connecte) { ?>
        <div class="welcome-banner" id="welcome-banner">
            Content de vous revoir, <?= htmlspecialchars($_SESSION["prenom"]) ?> !
            <button class="welcome-banner-close" onclick="document.getElementById('welcome-banner').style.display='none'" aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    <?php } ?>

    <section id="hero">
        <img id="hero-img" src="../images/viking.png" alt="équipe de vikings">
        <div class="hero-contenu">
            <span class="hero-badge">Normandie</span>
            <h1>Viking Transport</h1>
            <p>Voyagez à travers la Normandie simplement, rapidement et à prix raisonnable.</p>
            <div class="hero-actions">
                <a href="../PHP/recherche.php" class="btn">Rechercher un trajet</a>
                <a href="../PHP/lignes.php" class="btn btn-outline">Voir les lignes</a>
            </div>
        </div>
    </section>

    <section class="section-actions">
        <h2 class="section-titre">Que souhaitez-vous faire ?</h2>
        <p class="section-sous-titre">Accédez rapidement à nos services</p>
        <div class="cards-grid">

            <a href="../PHP/recherche.php" class="card-action">
                <h3>Rechercher</h3>
                <p>Trouvez un trajet selon votre destination et vos horaires.</p>
            </a>

            <a href="../PHP/lignes.php" class="card-action">
                <h3>Horaires</h3>
                <p>Retrouvez les horaires de passage de chaque ligne.</p>
            </a>

            <?php if ($connecte) { ?>

                <a href="../PHP/recherche.php" class="card-action">
                    <h3>Acheter</h3>
                    <p>Achetez votre billet en quelques clics.</p>
                </a>

                <a href="../PHP/points.php" class="card-action">
                    <h3>Mes points</h3>
                    <p>Consultez votre solde de points fidélité.</p>
                </a>

                <a href="../PHP/historique.php" class="card-action">
                    <h3>Historique</h3>
                    <p>Revoyez l'ensemble de vos voyages passés.</p>
                </a>

            <?php } else { ?>

                <a href="../PHP/formulaire_inscription.php" class="card-action">
                    <h3>Créer un compte</h3>
                    <p>Inscrivez-vous et cumulez des points à chaque voyage.</p>
                </a>

            <?php } ?>

        </div>
    </section>

    <section class="section-carte">
        <div class="section-carte-inner">
            <div class="section-carte-texte">
                <h2 class="section-titre">Nos lignes en Normandie</h2>
                <p>Viking Transport couvre l'ensemble de la région normande avec un réseau de lignes régulières. Trouvez facilement votre itinéraire sur la carte.</p>
                <a href="../PHP/lignes.php" class="btn">Explorer les lignes</a>
            </div>
            <img id="carte-image" src="../images/carte.png" alt="carte des lignes">
        </div>
    </section>

    <?php include_once("../PHP/footer.php"); ?>
</body>
</html>