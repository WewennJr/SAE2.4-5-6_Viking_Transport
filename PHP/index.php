<?php
session_start();
$connecte = isset($_SESSION["num_client"]);
$justConnected = !empty($_SESSION["just_connected"]);
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

    <?php if ($connecte && $justConnected) { ?>
        <div class="welcome-banner" id="welcome-banner">
            Content de vous revoir, <?= htmlspecialchars($_SESSION["prenom"]) ?> !
            <button class="welcome-banner-close" onclick="document.getElementById('welcome-banner').style.display='none'"
                aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
    <?php } ?>

    <?php unset($_SESSION["just_connected"]); ?>

    <section id="hero">
        <img id="hero-img" src="../images/viking.png" alt="équipe de vikings">
        <div class="hero-contenu">
            <span class="hero-badge">Normandie</span>
            <h1 id="copyright_titre">Viking Transport</h1>
            <p>Voyagez à travers la Normandie simplement, rapidement et à prix raisonnable.</p>
            <div class="hero-actions">
                <a href="../PHP/recherche.php" class="btn">Rechercher un trajet</a>
                <a href="../PHP/lignes.php" class="btn btn-outline">Voir les lignes</a>
            </div>
        </div>
    </section>

    <!-- Raccourcis -->
    <section class="section-actions">
        <h2 class="section-titre">Que souhaitez-vous faire ?</h2>
        <p class="section-sous-titre">Accédez rapidement à nos services</p>
        <div class="cards-grid">

            <a href="#section-rechercher" class="card-action">
                <h3>Rechercher</h3>
                <p>Trouvez un trajet selon votre destination et vos horaires.</p>
            </a>

            <a href="#section-horaires" class="card-action">
                <h3>Horaires</h3>
                <p>Retrouvez les horaires de passage de chaque ligne.</p>
            </a>

            <?php if ($connecte) { ?>

                <a href="#section-acheter" class="card-action">
                    <h3>Acheter</h3>
                    <p>Achetez votre billet en quelques clics.</p>
                </a>

                <a href="#section-points" class="card-action">
                    <h3>Mes points</h3>
                    <p>Consultez votre solde de points fidélité.</p>
                </a>

                <a href="#section-historique" class="card-action">
                    <h3>Historique</h3>
                    <p>Revoyez l'ensemble de vos voyages passés.</p>
                </a>

            <?php } else { ?>

                <a href="#section-inscription" class="card-action">
                    <h3>Créer un compte</h3>
                    <p>Inscrivez-vous et cumulez des points à chaque voyage.</p>
                </a>

            <?php } ?>

        </div>
    </section>

    <!-- Section Rechercher -->
    <section class="section-detail" id="section-rechercher">
        <div class="section-detail-inner">
            <div class="section-detail-texte">
                <h2 class="section-titre">Rechercher un trajet</h2>
                <p>Entrez votre point de départ et votre destination pour trouver les meilleures options de trajet
                    disponibles sur le réseau Viking Transport. Comparez les durées, les correspondances et les horaires
                    en temps réel.</p>
                <a href="../PHP/recherche.php" class="btn">Lancer une recherche</a>
            </div>
            <div class="section-detail-visuel">
                <img src="../images/bus.png" alt="Bus Viking Transport">
            </div>
        </div>
    </section>

    <!-- Section Horaires -->
    <section class="section-detail section-detail-alt" id="section-horaires">
        <div class="section-detail-inner">
            <div class="section-detail-visuel">
                <img id="carte-image" src="../images/carte.png" alt="carte des lignes">
            </div>
            <div class="section-detail-texte">
                <h2 class="section-titre">Nos lignes &amp; horaires</h2>
                <p>Consultez l'ensemble des lignes du réseau normand ainsi que leurs horaires de passage. Planifiez vos
                    déplacements en toute sérénité grâce à nos fiches horaires détaillées.</p>
                <a href="../PHP/lignes.php" class="btn">Voir les lignes</a>
            </div>
        </div>
    </section>

    <?php if ($connecte) { ?>

        <!-- Section Acheter -->
        <section class="section-detail" id="section-acheter">
            <div class="section-detail-inner">
                <div class="section-detail-texte">
                    <h2 class="section-titre">Acheter un billet</h2>
                    <p>Réservez et achetez vos billets directement en ligne, en quelques clics. Choisissez votre ligne,
                        votre date et votre horaire, puis procédez au paiement en toute sécurité.</p>
                    <a href="../PHP/recherche.php" class="btn">Acheter un billet</a>
                </div>
                <div class="section-detail-visuel">
                    <img src="../images/ticket.jpeg" alt="ticket">
                </div>
            </div>
        </section>

        <!-- Section Mes points -->
        <section class="section-detail section-detail-alt" id="section-points">
            <div class="section-detail-inner">
                <div class="section-detail-visuel">
                    <img src="../images/fidelite.png" alt="fidelite">
                </div>
                <div class="section-detail-texte">
                    <h2 class="section-titre">Mes points fidélité</h2>
                    <p>À chaque voyage avec Viking Transport, vous cumulez des points fidélité. Consultez votre solde et
                        découvrez comment les utiliser pour obtenir des réductions sur vos prochains trajets.</p>
                    <a href="../PHP/espace_client.php" class="btn">Voir mes points</a>
                </div>
            </div>
        </section>

        <!-- Section Historique -->
        <section class="section-detail" id="section-historique">
            <div class="section-detail-inner">
                <div class="section-detail-texte">
                    <h2 class="section-titre">Historique de mes voyages</h2>
                    <p>Retrouvez l'ensemble de vos trajets passés avec Viking Transport. Dates, lignes, montants — tout
                        votre historique est disponible en un coup d'œil.</p>
                    <a href="../PHP/espace_client.php" class="btn">Voir mon historique</a>
                </div>
                <div class="section-detail-visuel">
                    <img src="../images/historique.png" alt="historique">
                </div>
            </div>
        </section>

    <?php } else { ?>

        <!-- Section Inscription -->
        <section class="section-detail" id="section-inscription">
            <div class="section-detail-inner">
                <div class="section-detail-texte">
                    <h2 class="section-titre">Créer un compte</h2>
                    <p>Rejoignez Viking Transport et profitez de l'espace client : achat de billets en ligne, cumul de
                        points fidélité, historique de vos voyages et bien plus encore.</p>
                    <a href="../PHP/formulaire_inscription.php" class="btn">S'inscrire gratuitement</a>
                </div>
                <div class="section-detail-visuel">
                    <img src="../images/phare.jpg" alt="Phare de Gatteville, Normandie">
                </div>
            </div>
        </section>

    <?php } ?>

    <?php include_once("../PHP/footer.php"); ?>

    <button id="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Retour en haut">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round">
            <polyline points="18 15 12 9 6 15" />
        </svg>
    </button>

    <script>
        const btn = document.getElementById('back-to-top');
        window.addEventListener('scroll', () => {
            btn.style.opacity = window.scrollY > 300 ? '1' : '0';
            btn.style.pointerEvents = window.scrollY > 300 ? 'auto' : 'none';
        });
    </script>

</body>

</html>