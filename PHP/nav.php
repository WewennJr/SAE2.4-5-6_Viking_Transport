<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$connecte = isset($_SESSION["num_client"]);
?>
<nav>

    <!-- Barre mobile : logo + hamburger (cachée sur desktop via CSS) -->
    <div class="nav-topbar">
        <a href="../PHP/index.php"><img id="logo-mobile" src="../images/logo-viking.png" alt="logo de Viking Transport" title="Accueil"></a>
        <button class="nav-burger" id="nav-burger" aria-label="Ouvrir le menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <!-- Structure originale inchangée -->
    <ul id="nav-menu">
        <li>
            <a href="../PHP/index.php"><img id="logo" src="../images/logo-viking.png" alt="logo de Viking Transport" title="Accueil"></a>
        </li>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle">Voyager ▾</a>
            <ul class="dropdown-menu">
                <li><a href="../PHP/recherche.php">Recherche</a></li>
                <li><a href="../PHP/lignes.php">Horaires</a></li>
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

<script>
(function () {
    const burger = document.getElementById('nav-burger');
    const menu   = document.getElementById('nav-menu');

    burger.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = menu.classList.toggle('nav-open');
        burger.classList.toggle('nav-burger--open', open);
        burger.setAttribute('aria-expanded', open);
    });

    document.querySelectorAll('nav .dropdown-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const li = toggle.closest('.dropdown');
            li.classList.toggle('dropdown--open');
            document.querySelectorAll('nav .dropdown').forEach(function (other) {
                if (other !== li) other.classList.remove('dropdown--open');
            });
        });
    });

    document.addEventListener('click', function () {
        menu.classList.remove('nav-open');
        burger.classList.remove('nav-burger--open');
        burger.setAttribute('aria-expanded', false);
        document.querySelectorAll('nav .dropdown').forEach(function (li) {
            li.classList.remove('dropdown--open');
        });
    });

    menu.addEventListener('click', function (e) { e.stopPropagation(); });
})();
</script>