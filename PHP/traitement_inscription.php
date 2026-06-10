<?php
    session_start();

	include_once "pdo_agile.php";
	include_once "param_connexion_etu.php";

	$conn = OuvrirConnexionPDO($dbOracle,$db_usernameOracle,$db_passwordOracle);

    $erreur=false;

    // Vérification des champs du formulaire
    $type_num = 1;
	if (!empty($_POST["DEP_NUM"])) {$dep =$_POST["DEP_NUM"];} else {$erreur = true;}
    if (!empty($_POST["CLI_NOM"])) {$nom = $_POST["CLI_NOM"];} else {$erreur = true;}
	if (!empty($_POST["CLI_PRENOM"])) {$prenom = $_POST["CLI_PRENOM"];} else {$erreur = true;}
	if (!empty($_POST["CLI_VILLE"])) {$ville = $_POST["CLI_VILLE"];} else {$erreur = true;}
	if (!empty($_POST["CLI_TELEPHONE"])) {$tel =$_POST["CLI_TELEPHONE"];} else {$erreur = true;}
	if (!empty($_POST["CLI_COURRIEL"])) {$courriel = $_POST["CLI_COURRIEL"];} else {$erreur = true;}
	if (!empty($_POST["CLI_MDP"])) {$mdp = $_POST["CLI_MDP"];} else {$erreur = true;}
	if (!empty($_POST["CLI_MDP_CONF"])) {$confirm_mdp = $_POST["CLI_MDP_CONF"];} else {$erreur = true;}
    $nb_points_ec = 0;
    $nb_points_tot = 0;
    $date_connec = date("Y-m-d H:i:s");
    $grade = "client";

    if ($erreur) {
        // Sauvegarde des données (sauf mdp) pour reremplir automatiquement en cas d'erreur
        $form = $_POST;
        unset($form['CLI_MDP'], $form['CLI_MDP_CONF']);
        $_SESSION['form_data'] = $form;

        $_SESSION['erreur'] = "Tous les champs sont obligatoires.";
        header("Location: inscription.php");
        exit();
    }

    // Vérification des doublons
    $sql = "SELECT count(cli_courriel) AS nb FROM vik_client WHERE cli_courriel = '$courriel'";
    $tab = LireDonneesPDO3($conn,$sql,$table);
    if (isset($tab[0]['NB'])) {
        if ($tab[0]['NB'] > 0) {
            $_SESSION['erreur'] = "Un compte existe déjà avec ce courriel.";
            header("Location: inscription.php");
            exit();
        }
    }

    // Vérification de la correspondance des mots de passe
    if ($mdp !== $confirm_mdp) {
        $_SESSION['erreur'] = "Les mots de passe ne correspondent pas.";
        header("Location: inscription.php");
        exit();
    }

    // Hash mdp
    $mdp = password_hash($mdp, PASSWORD_BCRYPT);

    // Si aucune erreur, insertion du nouvel utilisateur
    $sql = "INSERT INTO vik_client (cli_num, typ_num, dep_num, cli_nom, cli_prenom, cli_ville, cli_telephone, cli_courriel, cli_mdp, cli_nb_points_ec, cli_nb_points_tot, cli_date_connec, cli_grade) 
            VALUES (cli_num_seq.nextval, '$type_num', '$dep', '$nom', '$prenom', '$ville', '$tel', '$courriel', '$mdp', $nb_points_ec, $nb_points_tot, TO_DATE('$date_connec', 'YYYY-MM-DD HH24:MI:SS'), '$grade')";
    $res = majDonneesPDO($conn,$sql);
    if ($res) {
        unset($_SESSION['form_data']);

        $_SESSION['success'] = "Inscription réussie. Vous pouvez maintenant vous connecter.";
        header("Location: connexion.php");
        exit();
    } else {
        $_SESSION['erreur'] = "Erreur lors de l'inscription. Veuillez réessayer.";
        header("Location: inscription.php");
        exit();
    }
?>