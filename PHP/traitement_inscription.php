<?php
session_start();

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$erreur = false;

// 1. Sauvegarde systématique des données pour réaffichage (sauf mots de passe)
$form = $_POST;
unset($form['CLI_MDP'], $form['CLI_MDP_CONF']);
$_SESSION['form_data'] = $form;

// 2. Vérification des champs obligatoires
$champs_obligatoires = ["DEP_NUM", "CLI_NOM", "CLI_PRENOM", "CLI_VILLE", "CLI_TELEPHONE", "CLI_COURRIEL", "CLI_MDP", "CLI_MDP_CONF"];
foreach ($champs_obligatoires as $champ) {
    if (empty($_POST[$champ])) {
        $erreur = true;
    }
}

if ($erreur) {
    $_SESSION['erreur'] = "Tous les champs sont obligatoires.";
    $conn = null;
    header("Location: formulaire_inscription.php");
    exit();
}

// Assignation des variables après validation de leur existence
$dep = $_POST["DEP_NUM"];
$nom = $_POST["CLI_NOM"];
$prenom = $_POST["CLI_PRENOM"];
$ville = $_POST["CLI_VILLE"];
$tel = $_POST["CLI_TELEPHONE"];
$courriel = $_POST["CLI_COURRIEL"];
$mdp = $_POST["CLI_MDP"];
$confirm_mdp = $_POST["CLI_MDP_CONF"];

// 3. Vérification de l'acceptation des conditions générales
if (empty($_POST["accept_conditions"])) {
    $_SESSION['erreur'] = "Vous devez accepter les conditions générales.";
    $conn = null;
    header("Location: formulaire_inscription.php");
    exit();
}

// 4. Vérification de la correspondance des mots de passe
if ($mdp !== $confirm_mdp) {
    $_SESSION['erreur'] = "Les mots de passe ne correspondent pas.";
    $conn = null;
    header("Location: formulaire_inscription.php");
    exit();
}

// 5. Vérification du format du numéro de téléphone
if (!ctype_digit($tel)) {
    $_SESSION['erreur'] = "Le numéro de téléphone doit être composé uniquement de chiffres.";
    $conn = null;
    header("Location: formulaire_inscription.php");
    exit();
}

// 6. Vérification des doublons avec requête préparée (Anti-injection SQL)
$sql_check = "SELECT count(cli_courriel) AS nb FROM vik_client WHERE cli_courriel = :courriel";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->execute([':courriel' => $courriel]);
$row = $stmt_check->fetch(PDO::FETCH_ASSOC);

if ($row && $row['NB'] > 0) {
    $_SESSION['erreur'] = "Un compte existe déjà avec ce courriel.";
    $conn = null;
    header("Location: formulaire_inscription.php");
    exit();
}

// 7. Sécurisation du mot de passe
$mdp_hash = password_hash($mdp, PASSWORD_BCRYPT);

// Valeurs par défaut
$type_num = 1;
$nb_points_ec = 10;
$nb_points_tot = 10;
$grade = "client";

// 8. Insertion sécurisée via requête préparée
$sql_insert = "INSERT INTO vik_client (
                cli_num, typ_num, dep_num, cli_nom, cli_prenom, cli_ville, 
                cli_telephone, cli_courriel, cli_mdp, cli_nb_points_ec, 
                cli_nb_points_tot, cli_date_connec, cli_grade
              ) VALUES (
                cli_num_seq.nextval, :type_num, :dep, :nom, :prenom, :ville, 
                :tel, :courriel, :mdp, :points_ec, :points_tot, SYSDATE, :grade
              )";

$stmt_insert = $conn->prepare($sql_insert);
$res = $stmt_insert->execute([
    ':type_num' => $type_num,
    ':dep' => $dep,
    ':nom' => $nom,
    ':prenom' => $prenom,
    ':ville' => $ville,
    ':tel' => $tel,
    ':courriel' => $courriel,
    ':mdp' => $mdp_hash,
    ':points_ec' => $nb_points_ec,
    ':points_tot' => $nb_points_tot,
    ':grade' => $grade
]);

$conn = null;

if ($res) {
    // Nettoyage des données temporaires en cas de succès
    unset($_SESSION['form_data']);
    $_SESSION['success'] = "Inscription réussie. Vous pouvez maintenant vous connecter.";
    header("Location: formulaire_connexion.php");
    exit();
} else {
    $_SESSION['erreur'] = "Erreur lors de l'inscription. Veuillez réessayer.";
    header("Location: formulaire_inscription.php");
    exit();
}
?>