<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

if (!isset($_SESSION['num_client'])) {
    header("Location: formulaire_connexion.php");
    exit();
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// Vérification des champs
if (empty($_POST["CLI_MDP_NOUVEAU"]) || empty($_POST["CLI_MDP_CONF"])) {
    $_SESSION['erreur'] = "Tous les champs sont obligatoires.";
    $conn=null;
    header("Location: modifier_profil.php");
    exit();
}

$nouveau_mdp  = $_POST["CLI_MDP_NOUVEAU"];
$confirm_mdp  = $_POST["CLI_MDP_CONF"];

// Vérification correspondance
if ($nouveau_mdp !== $confirm_mdp) {
    $_SESSION['erreur'] = "Les mots de passe ne correspondent pas.";
    $conn=null;
    header("Location: modifier_profil.php");
    exit();
}

// Hash et mise à jour
$mdp_hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
$num = $_SESSION['num_client'];

$sql = "UPDATE vik_client SET cli_mdp = '$mdp_hash' WHERE cli_num = $num";
$res = majDonneesPDO($conn, $sql);

$conn=null;

if ($res !== false) {
    $_SESSION['success'] = "Mot de passe modifié avec succès.";
} else {
    $_SESSION['erreur'] = "Erreur lors du changement de mot de passe. Veuillez réessayer.";
}

header("Location: modifier_profil.php");
exit();
?>