<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

if (!isset($_SESSION['num_client'])) {
    header("Location: formulaire_connexion.php");
    exit();
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$erreur = false;

// Vérification des champs
if (!empty($_POST["DEP_NUM"])) {
    $dep = $_POST["DEP_NUM"];
} else {
    $erreur = true;
}
if (!empty($_POST["CLI_NOM"])) {
    $nom = $_POST["CLI_NOM"];
} else {
    $erreur = true;
}
if (!empty($_POST["CLI_PRENOM"])) {
    $prenom = $_POST["CLI_PRENOM"];
} else {
    $erreur = true;
}
if (!empty($_POST["CLI_VILLE"])) {
    $ville = $_POST["CLI_VILLE"];
} else {
    $erreur = true;
}
if (!empty($_POST["CLI_TELEPHONE"])) {
    $tel = $_POST["CLI_TELEPHONE"];
} else {
    $erreur = true;
}
if (!empty($_POST["CLI_COURRIEL"])) {
    $courriel = $_POST["CLI_COURRIEL"];
} else {
    $erreur = true;
}

if ($erreur) {
    $_SESSION['form_data'] = $_POST;
    $_SESSION['erreur'] = "Tous les champs sont obligatoires.";
    $conn = null;
    header("Location: modifier_profil.php");
    exit();
}

// Vérification doublon courriel (on exclut l'utilisateur actuel) — requête préparée
$num = $_SESSION['num_client'];
$sql_check = "SELECT count(cli_courriel) AS NB FROM vik_client 
              WHERE cli_courriel = :courriel AND cli_num != :num";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->execute([':courriel' => $courriel, ':num' => $num]);
$row = $stmt_check->fetch(PDO::FETCH_ASSOC);
if ($row && $row['NB'] > 0) {
    $_SESSION['form_data'] = $_POST;
    $_SESSION['erreur'] = "Un compte existe déjà avec ce courriel.";
    $conn = null;
    header("Location: modifier_profil.php");
    exit();
}

// Mise à jour — requête préparée
$sql_update = "UPDATE vik_client SET
    cli_nom       = :nom,
    cli_prenom    = :prenom,
    cli_courriel  = :courriel,
    cli_ville     = :ville,
    cli_telephone = :tel,
    dep_num       = :dep
WHERE cli_num = :num";
$stmt_update = $conn->prepare($sql_update);
$res = $stmt_update->execute([
    ':nom' => $nom,
    ':prenom' => $prenom,
    ':courriel' => $courriel,
    ':ville' => $ville,
    ':tel' => $tel,
    ':dep' => $dep,
    ':num' => $num
]);

if ($res) {
    $_SESSION['prenom'] = $prenom;
    $_SESSION['success'] = "Vos informations ont bien été mises à jour.";
} else {
    $_SESSION['erreur'] = "Erreur lors de la mise à jour. Veuillez réessayer.";
}

$conn = null;
header("Location: modifier_profil.php");
exit();
?>