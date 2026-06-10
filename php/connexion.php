<?php 

session_start();
$erreur= "";

if($_SERVER["REQUEST_METHOD"] === "POST"){
   
    $email = $_POST["email"];
    $motdepasse = $_POST["motdepasse"];

    $sql = "SELECT * FROM VIK_CLIENT
        WHERE CLI_COURRIEL = :email
        AND CLI_MDP = :mdp";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":email", $email);
    oci_bind_by_name($stmt, ":mdp", $motdepasse);
    oci_execute($stmt);

    $client = oci_fetch_assoc($stmt);

    if($client){
        $_SESSION["num_client"] = $client["CLI_NUM"];
        $_SESSION["prenom"] = $client["CLI_PRENOM"];
        $sqlMaj = "UPDATE vik_client
                SET cli_date_connec = sysdate
                WHERE num_client = :num";

        $stmtMaj = oci_parse($conn, $sqlMaj);
        oci_bind_by_name($stmtMaj, ":num", $client["CLI_NUM"]);
        oci_execute($stmtMaj);
        
        header("Location: ../HTML/index.html");
        exit;
    } else {
        $erreur = "Courriel ou mot de passe incorrect.";
    }
}

