<?php
session_start();
$erreur = "";
$nom = "";
$prenom = "";
$courriel = "";
$ville = "";
$tel = "";
$dep = "";

if (isset($_SESSION['erreur'])) {
    $erreur = $_SESSION['erreur'];
    unset($_SESSION['erreur']);
}
if (isset($_SESSION['form_data'])) {
    $nom     = $_SESSION['form_data']['CLI_NOM'];
    $prenom  = $_SESSION['form_data']['CLI_PRENOM'];
    $courriel= $_SESSION['form_data']['CLI_COURRIEL'];
    $ville   = $_SESSION['form_data']['CLI_VILLE'];
    $tel     = $_SESSION['form_data']['CLI_TELEPHONE'];
    $dep     = $_SESSION['form_data']['DEP_NUM'];
    unset($_SESSION['form_data']);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <nav>
        <ul>
            <li><a href="../index.html">Accueil</a></li>
            <li><a href="recherche.html">Voyager</a></li>
            <li><a href="VikingTransports.html">Notre entreprise</a></li>
            <li><a href="services.html">Nos services</a></li>
            <li id="profil"><a href="redirection.html">Me connecter</a></li>
        </ul>
    </nav>
    <main>
        <h1>Inscription</h1>
        <article>
            <?php
            if ($erreur != "") {
                echo "<p style='color:red;'>" . $erreur . "</p>";
            }
            ?>
            <form action="traitement_inscription.php" method="post">
                <div class="form-group">
                    <label for="CLI_NOM">Nom</label>
                    <input type="text" id="CLI_NOM" name="CLI_NOM" value="<?php echo $nom; ?>" placeholder="Votre nom" required>
                </div>
                <div class="form-group">
                    <label for="CLI_PRENOM">Prénom</label>
                    <input type="text" id="CLI_PRENOM" name="CLI_PRENOM" value="<?php echo $prenom; ?>" placeholder="Votre prénom" required>
                </div>
                <div class="form-group">
                    <label for="CLI_COURRIEL">Email</label>
                    <input type="email" id="CLI_COURRIEL" name="CLI_COURRIEL" value="<?php echo $courriel; ?>" placeholder="votre@email.fr" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="CLI_MDP">Mot de passe</label>
                        <input type="password" id="CLI_MDP" name="CLI_MDP" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label for="CLI_MDP_CONF">Confirmer le mot de passe</label>
                        <input type="password" id="CLI_MDP_CONF" name="CLI_MDP_CONF" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="CLI_VILLE">Ville</label>
                        <input type="text" id="CLI_VILLE" name="CLI_VILLE" value="<?php echo $ville; ?>" placeholder="Votre ville" required>
                    </div>
                    <div class="form-group">
                        <label for="CLI_TELEPHONE">Téléphone</label>
                        <input type="tel" id="CLI_TELEPHONE" name="CLI_TELEPHONE" value="<?php echo $tel; ?>" placeholder="00 00 00 00 00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="DEP_NUM">Département</label>
                    <select id="DEP_NUM" name="DEP_NUM" required>
                        <option value="">Sélectionnez votre département</option>
                        <option value="14" <?php if ($dep == "14") echo "selected"; ?>>14 – Calvados</option>
                        <option value="50" <?php if ($dep == "50") echo "selected"; ?>>50 – Manche</option>
                        <option value="61" <?php if ($dep == "61") echo "selected"; ?>>61 – Orne</option>
                        <option value="27" <?php if ($dep == "27") echo "selected"; ?>>27 – Eure</option>
                        <option value="76" <?php if ($dep == "76") echo "selected"; ?>>76 – Seine-Maritime</option>
                    </select>
                </div>
                <button type="submit">S'inscrire</button>
            </form>
        </article>
    </main>
    <footer>
        <a href="contact.html">Nous contacter</a>
        <div class="mentions"><a href="mentionslegales.html">Mentions légales</a></div>
        <div class="credits">Site réalisé par T.UI.sto</div>
    </footer>
</body>
</html>