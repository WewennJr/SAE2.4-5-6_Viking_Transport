<?php
// Si la session n'est pas déjà démarrée, on la démarre (sécurité)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$connecte = isset($_SESSION["num_client"]);
?>
<nav>
    <ul>
        <li>
            <a href="../index.php"><img id="logo" src="../images/logo-viking.png" alt="logo de Viking Transport"></a>
        </li>
        <li><a href="../HTML/recherche.html">Voyager</a></li>
        <li><a href="../HTML/VikingTransports.html">Notre entreprise</a></li>
        <li><a href="../HTML/services.html">Nos services</a></li>
        <?php if ($connecte) { ?>
            <li id="profil">
                <a href="../HTML/compte.php" class="profil-nom">Bonjour <?= htmlspecialchars($_SESSION["prenom"]) ?></a>
                <a href="../PHP/deconnexion.php" class="btn-deconnexion" title="Se déconnecter" aria-label="Se déconnecter">⏻</a>
            </li>
        <?php } else { ?>
            <li id="profil"><a href="../PHP/formulaire_connexion.php">Me connecter</a></li>
        <?php } ?>
    </ul>
</nav>