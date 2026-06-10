<?php session_start(); ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="description" content="Viking Transports">
    <title>connexion Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="../index.html">Accueil</a></li>
            <li><a href="../HTML/recherche.html">Voyager</a></li>
            <li><a href="../HTML/VikingTransports.html">Notre entreprise</a></li>
            <li><a href="../HTML/services.html">Nos services</a></li>
            <li id="profil"><a href="formulaire_connexion.php">Me connecter</a></li>
        </ul>
    </nav>

    <h1>Connexion</h1>

    <p id="erreur">
    <?php
        if (isset($_SESSION["erreur"])) {
            echo htmlspecialchars($_SESSION["erreur"]);
            unset($_SESSION["erreur"]);
        }
    ?>
    </p>

    <form action="../PHP/traitementConnexion.php" method="post">
        <label for="CLI_COURRIEL">Email :</label>
        <input type="email" id="CLI_COURRIEL" name="CLI_COURRIEL" required><br><br>
        <label for="CLI_MDP">Mot de passe :</label>
        <input type="password" id="CLI_MDP" name="CLI_MDP" required><br><br>
        <input type="submit" value="Se connecter">
    </form>

    <p>Pas encore de compte ? <a href="../PHP/inscription.php">Inscrivez-vous</a></p>

    <footer>
        <a href="../HTML/contact.html">Nous contacter</a>
        <div class="credits">Site réalisé par T.UI.sto</div>
    </footer>
</body>
</html>