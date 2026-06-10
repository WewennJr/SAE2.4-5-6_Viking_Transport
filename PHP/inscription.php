<?php
    session_start();
    $form = $_SESSION['form_data'] ?? [];
    $erreur = $_SESSION['erreur'] ?? null;
?>
<!doctype html>
<html lang="fr">
	<head>
		<meta charset="utf-8">
        <meta name="Viking Transports">
        <meta author="T.UI.sto">
		<title>inscription Viking Transport</title>
        <link rel="stylesheet" href="../CSS/style.css">
	</head>
	<body>
        <nav>
            <ul>
                <li>
                    <a href="../index.html">Accueil</a>
                </li>
                <li>
                    <a href="recherche.html">Voyager</a>
                </li>
                <li>
                    <a href="VikingTransports.html">Notre entreprise</a>                    
                </li>
                <li>
                    <a href="services.html">Nos services</a>
                    
                </li>
                <li id="profil">
                    <a href="redirection.html">Me connecter</a>
                    
                </li>
            </ul>
        </nav>

        <h1>Inscription</h1>

        <?php
            if (isset($erreur)) {
                echo "<p style='color:red;'>$erreur</p>";
                unset($_SESSION['erreur']);
            }
        ?>
        <form action="traitement_inscription.php" method="post">
            <label for="CLI_NOM">Nom :</label>
            <input type="text" id="CLI_NOM" name="CLI_NOM" required
                value="<?php echo $form['CLI_NOM'] ?? ''; ?>"><br><br>

            <label for="CLI_PRENOM">Prénom :</label>
            <input type="text" id="CLI_PRENOM" name="CLI_PRENOM" required
                value="<?php echo $form['CLI_PRENOM'] ?? ''; ?>"><br><br>

            <label for="CLI_COURRIEL">Email :</label>
            <input type="email" id="CLI_COURRIEL" name="CLI_COURRIEL" required
                value="<?php echo $form['CLI_COURRIEL'] ?? ''; ?>"><br><br>

            <label for="CLI_MDP">Mot de passe :</label>
            <input type="password" id="CLI_MDP" name="CLI_MDP" required
                value="<?php echo $form['CLI_MDP'] ?? ''; ?>"><br><br>

            <label for="CLI_MDP_CONF">Confirmer le mot de passe :</label>
            <input type="password" id="CLI_MDP_CONF" name="CLI_MDP_CONF" required
                value="<?php echo $form['CLI_MDP_CONF'] ?? ''; ?>"><br><br>

            <label for="CLI_VILLE">Ville :</label>
            <input type="text" id="CLI_VILLE" name="CLI_VILLE" required
                value="<?php echo $form['CLI_VILLE'] ?? ''; ?>"><br><br>

            <label for="CLI_TELEPHONE">Téléphone :</label>
            <input type="tel" id="CLI_TELEPHONE" name="CLI_TELEPHONE" required
                value="<?php echo $form['CLI_TELEPHONE'] ?? ''; ?>"><br><br>

            <label for="DEP_NUM">Département :</label>
            <select id="DEP_NUM" name="DEP_NUM" required>
                <option value="">Sélectionnez votre département </option>
                <option value="14" <?php if(($form['DEP_NUM'] ?? '') == "14") echo "selected"; ?>>14 Calvados</option>
                <option value="50" <?php if(($form['DEP_NUM'] ?? '') == "50") echo "selected"; ?>>50 Manche</option>
                <option value="61" <?php if(($form['DEP_NUM'] ?? '') == "61") echo "selected"; ?>>61 Orne</option>
                <option value="27" <?php if(($form['DEP_NUM'] ?? '') == "27") echo "selected"; ?>>27 Eure</option>
                <option value="76" <?php if(($form['DEP_NUM'] ?? '') == "76") echo "selected"; ?>>76 Seine-Maritime</option>
            </select><br><br>

            <input type="submit" value="S'inscrire">
        </form>

        <footer>
            <a href="contact.html">Nous contacter</a>
            <div class="credits">Site réalisé par T.UI.sto</div>
        </footer>

	</body>
</html>
