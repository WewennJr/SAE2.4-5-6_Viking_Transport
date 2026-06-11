<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$connecte = isset($_SESSION["num_client"]);

?>

<nav>

    <ul>

        <li>

            <a href="../PHP/index.php"><img id="logo" src="../images/logo-viking.png" alt="logo de Viking Transport" title="Accueil"></a>

        </li>


        <li class="dropdown">

            <a href="#" class="dropdown-toggle">Voyager ▾</a>

            <ul class="dropdown-menu">

                <li><a href="../PHP/recherche.php">Recherche</a></li>

                <li><a href="../PHP/lignes.php">Lignes</a></li>

                <li><a href="../PHP/horaire_ligne.php">Horaires</a></li>

            </ul>

        </li>


        <li class="dropdown">

            <a href="#" class="dropdown-toggle">À propos ▾</a>

            <ul class="dropdown-menu">

                <li><a href="../PHP/VikingTransports.php">Notre entreprise</a></li>

                <li><a href="../PHP/services.php">Nos services</a></li>

            </ul>

        </li>


        <?php if ($connecte) { ?>

            <li id="profil">

                <a href="../PHP/espace_client.php" class="profil-nom" title="Mon espace">Bonjour <?= htmlspecialchars($_SESSION["prenom"]) ?></a>

                <a href="../PHP/deconnexion.php" class="btn-deconnexion" title="Se déconnecter" aria-label="Se déconnecter">⏻</a>

            </li>

        <?php } else { ?>

            <li id="profil"><a href="../PHP/formulaire_connexion.php">Me connecter</a></li>

        <?php } ?>

    </ul>

</nav>