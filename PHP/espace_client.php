<?php

session_start();

if (!isset($_SESSION["num_client"])) {
    header("Location: formulaire_connexion.php");
    exit;
}

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$stmt = $conn->prepare("SELECT * FROM VIK_CLIENT WHERE CLI_NUM = :num");
$stmt->execute([':num' => $_SESSION['num_client']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

$nom      = $client['CLI_NOM'];
$prenom   = $client['CLI_PRENOM'];
$courriel = $client['CLI_COURRIEL'];
$ville    = $client['CLI_VILLE'];
$tel      = $client['CLI_TELEPHONE'];   
$dep      = $client['DEP_NUM'];
$points   = $client['CLI_NB_POINTS_TOT'];          
?>
<!doctype html>
<html>
<head>
        <meta charset="utf-8">
        <meta name="Viking Transports">
        <meta author="T.UI.sto">
        <title>Mon compte</title>
        <link rel="stylesheet" href="../CSS/style.css">
    </head>
<body>
    <?php 
    include_once("../PHP/nav.php");
  ?>

        <main>
            <h1>Bienvenue dans votre espace client</h1>
            <section>
                <h2>Mes informations</h2>
                <p>Nom : <?php echo $nom; ?></p>
                <p>Prénom : <?php echo $prenom; ?></p>
                <p>Email : <?php echo $courriel; ?></p>
                <p>Téléphone : <?php echo $tel; ?></p>
                <p>Ville : <?php echo $ville; ?></p>
                <a href="../PHP/modifier_profil.php">Modifier mes informations</a>

            </section>
            <section>
                <h2>Mes points de fidélité</h2>
                <p>points : <?php echo $points; ?></p>
            </section>
            
            <section>
                <h2>Mes réservations</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Numéro de réservation</th>
                            <th>Date de réservation</th>
                            <th>Départ</th>
                            <th>Arrivée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                    </tbody>
                </table>
            </section>
        </main>


        
        <?php 
            include_once("../PHP/footer.php");
        ?>
    </body>
</html>