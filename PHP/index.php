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
    <style>
        /* ── Hero ───────────────────────────────────────────── */
        #hero {
            position: relative;
            min-height: 88vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: var(--noir);
        }

        #hero-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.35;
            filter: grayscale(30%);
        }

        .hero-contenu {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 2rem 8%;
            max-width: 860px;
        }

        .hero-contenu h1 {
            font-size: clamp(2.6rem, 6vw, 5rem);
            color: var(--blanc);
            border: none;
            margin-bottom: 1rem;
            padding-bottom: 0;
            text-shadow: 0 2px 20px rgba(0,0,0,0.6);
        }

        .hero-contenu p {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: #ccc;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-actions .btn {
            font-size: 1rem;
            padding: 0.85rem 2rem;
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--blanc);
            color: var(--blanc);
        }

        .btn-outline:hover {
            background-color: var(--blanc);
            color: var(--noir);
            transform: translateY(-1px);
        }

        .hero-badge {
            display: inline-block;
            background-color: var(--rouge);
            color: #fff;
            font-family: var(--font-display);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            margin-bottom: 1.2rem;
        }

        /* ── Message connecté ───────────────────────────────── */
        .welcome-banner {
            background-color: var(--rouge);
            color: #fff;
            text-align: center;
            padding: 0.7rem 8%;
            font-family: var(--font-display);
            font-size: 1rem;
            letter-spacing: 0.05em;
        }

        /* ── Section actions ────────────────────────────────── */
        .section-actions {
            padding: 5rem 8%;
            background-color: var(--blanc);
        }

        .section-titre {
            font-family: var(--font-display);
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--noir);
            margin-bottom: 0.4rem;
        }

        .section-sous-titre {
            color: var(--gris);
            font-size: 1rem;
            margin-bottom: 2.5rem;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.2rem;
        }

        .card-action {
            background-color: #fff;
            border-radius: 8px;
            padding: 1.8rem 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            text-decoration: none;
            color: var(--noir);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            border-top: 3px solid var(--rouge);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-action:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            background-color: #fff;
            color: var(--noir);
        }

        .card-icon {
            font-size: 1.8rem;
            line-height: 1;
        }

        .card-action h3 {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--noir);
            margin: 0;
        }

        .card-action p {
            font-size: 0.9rem;
            color: var(--gris);
            margin: 0;
            line-height: 1.5;
        }

        /* ── Section carte ──────────────────────────────────── */
        .section-carte {
            padding: 5rem 8%;
            background-color: #fff;
        }

        .section-carte-inner {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 4rem;
            align-items: center;
        }

        .section-carte-texte h2 {
            font-size: 2rem;
            border: none;
            margin-bottom: 1rem;
        }

        .section-carte-texte p {
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .section-carte img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 900px) {
            .section-carte-inner {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 640px) {
            .section-actions,
            .section-carte {
                padding: 3rem 5%;
            }

            #hero {
                min-height: 70vh;
            }
        }
    </style>
</head>
<body>

    <?php include_once("../PHP/nav.php"); ?>

    <?php if ($connecte) { ?>
        <div class="welcome-banner">
            ⚡ Content de vous revoir, <?= htmlspecialchars($_SESSION["prenom"]) ?> !
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
                <span class="card-icon">🔍</span>
                <h3>Rechercher</h3>
                <p>Trouvez un trajet selon votre destination et vos horaires.</p>
            </a>
            <a href="../PHP/lignes.php" class="card-action">
                <span class="card-icon">🗺️</span>
                <h3>Nos lignes</h3>
                <p>Consultez toutes les lignes disponibles en Normandie.</p>
            </a>
            <a href="../PHP/horaire_ligne.php" class="card-action">
                <span class="card-icon">🕐</span>
                <h3>Horaires</h3>
                <p>Retrouvez les horaires de passage de chaque ligne.</p>
            </a>
            <?php if ($connecte) { ?>
                <a href="../PHP/recherche.php" class="card-action">
                    <span class="card-icon">🎫</span>
                    <h3>Acheter</h3>
                    <p>Achetez votre billet en quelques clics.</p>
                </a>
                <a href="../PHP/points.php" class="card-action">
                    <span class="card-icon">⭐</span>
                    <h3>Mes points</h3>
                    <p>Consultez votre solde de points fidélité.</p>
                </a>
                <a href="../PHP/historique.php" class="card-action">
                    <span class="card-icon">📋</span>
                    <h3>Historique</h3>
                    <p>Revoyez l'ensemble de vos voyages passés.</p>
                </a>
            <?php } else { ?>
                <a href="../PHP/formulaire_inscription.php" class="card-action">
                    <span class="card-icon">⭐</span>
                    <h3>Points fidélité</h3>
                    <p>Créez un compte et cumulez des points à chaque voyage.</p>
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