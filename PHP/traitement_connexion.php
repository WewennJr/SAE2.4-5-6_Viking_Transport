<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

if ($conn) {
    if (!empty($_POST["CLI_COURRIEL"]) && !empty($_POST["CLI_MDP"])) {
        $email = $_POST["CLI_COURRIEL"];
        $motdepasse = $_POST["CLI_MDP"];

        $sql = "SELECT * FROM VIK_CLIENT WHERE CLI_COURRIEL = :email";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client && password_verify($motdepasse, $client['CLI_MDP'])) {
            $_SESSION["num_client"] = $client["CLI_NUM"];
            $_SESSION["prenom"] = $client["CLI_PRENOM"];
            $_SESSION["just_connected"] = true;

            $sqlMaj = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
            $stmtMaj = $conn->prepare($sqlMaj);
            $stmtMaj->execute([':num' => $client["CLI_NUM"]]);

            $conn = null;

            header("Location: ../PHP/index.php");   // succès → page d'arrivée
            exit();

        } else {
            $_SESSION["erreur"] = "Courriel ou mot de passe incorrect.";
        }
    } else {
        $_SESSION["erreur"] = "Les champs ne sont pas complets.";
    }
    $conn = null;
} else {
    $_SESSION["erreur"] = "Connexion à la base impossible.";
}

$conn = null;

// En cas d'échec : on renvoie vers le formulaire
header("Location: ../PHP/formulaire_connexion.php");
exit;
?>