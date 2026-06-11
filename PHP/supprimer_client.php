<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

if (!isset($_SESSION['num_client'])) {
    header("Location: formulaire_connexion.php");
    exit();
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$num = $_SESSION['num_client'];
$sql = "SELECT cli_grade FROM vik_client WHERE cli_num = $num";
$tab = LireDonneesPDO3($conn, $sql, $table);
if (!isset($tab[0]['CLI_GRADE']) || $tab[0]['CLI_GRADE'] != 'administrateur') {
    header("Location: ../PHP/index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = (int)$_GET['id'];

// Supprimer les étapes liées aux réservations du client
$sql = "DELETE FROM vik_etape WHERE cli_num = $id";
majDonneesPDO($conn, $sql);

// Supprimer les réservations du client
$sql = "DELETE FROM vik_reservation WHERE cli_num = $id";
majDonneesPDO($conn, $sql);

// Supprimer le client (jamais un admin)
$sql = "DELETE FROM vik_client WHERE cli_num = $id AND cli_grade != 'administrateur'";
majDonneesPDO($conn, $sql);

$conn=null;

header("Location: admin.php");
exit();

?>