<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['num_client'])) {
    header("Location: formulaire_connexion.php");
    exit();
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// Récupérer les données actuelles de l'utilisateur
$sql = "SELECT * FROM VIK_CLIENT WHERE CLI_NUM = " . $_SESSION['num_client'];
$tab = LireDonneesPDO3($conn, $sql, $table);
$client = $tab[0];

$erreur = "";
$success = "";

if (isset($_SESSION['erreur'])) {
    $erreur = $_SESSION['erreur'];
    unset($_SESSION['erreur']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Pré-remplissage avec les données de la BDD (ou données re-soumises en cas d'erreur)
$nom = $client['CLI_NOM'];
$prenom = $client['CLI_PRENOM'];
$courriel = $client['CLI_COURRIEL'];
$ville = $client['CLI_VILLE'];
$tel = $client['CLI_TELEPHONE'];
$dep = $client['DEP_NUM'];

// Si retour après erreur, on écrase avec ce que l'utilisateur avait saisi
if (isset($_SESSION['form_data'])) {
    $nom = $_SESSION['form_data']['CLI_NOM'];
    $prenom = $_SESSION['form_data']['CLI_PRENOM'];
    $courriel = $_SESSION['form_data']['CLI_COURRIEL'];
    $ville = $_SESSION['form_data']['CLI_VILLE'];
    $tel = $_SESSION['form_data']['CLI_TELEPHONE'];
    $dep = $_SESSION['form_data']['DEP_NUM'];
    unset($_SESSION['form_data']);
}
$conn = null;
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>

<body>
    <?php include_once "nav.php" ?>
    <main>
        <h1>Modifier mon profil</h1>
        <article>
            <?php
            if ($erreur != "") {
                echo "<p style='color:red;'>" . $erreur . "</p>";
            }
            if ($success != "") {
                echo "<p style='color:green;'>" . $success . "</p>";
            }
            ?>

            <!-- Formulaire infos personnelles -->
            <h2>Mes informations</h2>
            <form action="traitement_modifier_profil.php" method="post">
                <div class="form-group">
                    <label for="CLI_NOM">Nom</label>
                    <input type="text" id="CLI_NOM" name="CLI_NOM" value="<?php echo $nom; ?>" required>
                </div>
                <div class="form-group">
                    <label for="CLI_PRENOM">Prénom</label>
                    <input type="text" id="CLI_PRENOM" name="CLI_PRENOM" value="<?php echo $prenom; ?>" required>
                </div>
                <div class="form-group">
                    <label for="CLI_COURRIEL">Email</label>
                    <input type="email" id="CLI_COURRIEL" name="CLI_COURRIEL" value="<?php echo $courriel; ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="CLI_VILLE">Ville</label>
                        <input type="text" id="CLI_VILLE" name="CLI_VILLE" value="<?php echo $ville; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="CLI_TELEPHONE">Téléphone</label>
                        <input type="tel" id="CLI_TELEPHONE" name="CLI_TELEPHONE" value="<?php echo $tel; ?>"
                            placeholder="00 00 00 00 00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="DEP_NUM">Département</label>
                    <select id="DEP_NUM" name="DEP_NUM" required>
                        <option value="">Sélectionnez votre département</option>
                        <option value="14" <?php if ($dep == "14")
                            echo "selected"; ?>>14 – Calvados</option>
                        <option value="50" <?php if ($dep == "50")
                            echo "selected"; ?>>50 – Manche</option>
                        <option value="61" <?php if ($dep == "61")
                            echo "selected"; ?>>61 – Orne</option>
                        <option value="27" <?php if ($dep == "27")
                            echo "selected"; ?>>27 – Eure</option>
                        <option value="76" <?php if ($dep == "76")
                            echo "selected"; ?>>76 – Seine-Maritime</option>
                    </select>
                </div>
                <button type="submit">Enregistrer les modifications</button>
            </form>

            <!-- Formulaire changement de mot de passe (séparé) -->
            <h2 style="margin-top: 3rem;">Changer mon mot de passe</h2>
            <form action="traitement_modifier_mdp.php" method="post">
                <div class="form-group">
                    <label for="CLI_MDP_NOUVEAU">Nouveau mot de passe</label>
                    <input type="password" id="CLI_MDP_NOUVEAU" name="CLI_MDP_NOUVEAU" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label for="CLI_MDP_CONF">Confirmer le nouveau mot de passe</label>
                    <input type="password" id="CLI_MDP_CONF" name="CLI_MDP_CONF" placeholder="••••••••" required>
                </div>
                <button type="submit">Changer le mot de passe</button>
            </form>

        </article>
    </main>
    <?php include_once "footer.php" ?>
</body>

</html>