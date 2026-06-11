<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$sql_communes = "SELECT COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM ASC";
$liste_communes = [];
if ($conn) {
    $liste_communes = LireDonneesPDO3($conn, $sql_communes, $t_communes);
}

$trajets = [];
$recherche_faite = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rechercher'])) {
    $com_depart = $_POST['COM_DEPART'];
    $com_arrivee = $_POST['COM_ARRIVEE'];
    $date_voyage = $_POST['DATE_VOYAGE'];
    $recherche_faite = true;

    $sql_recherche = "SELECT n1.LIG_NUM 
                      FROM VIK_NOEUD n1
                      JOIN VIK_NOEUD n2 ON n1.LIG_NUM = n2.LIG_NUM
                      JOIN VIK_COMMUNE c1 ON n1.COM_CODE_INSEE = c1.COM_CODE_INSEE
                      JOIN VIK_COMMUNE c2 ON n2.COM_CODE_INSEE = c2.COM_CODE_INSEE
                      WHERE UPPER(c1.COM_NOM) = UPPER(:dep) 
                      AND UPPER(c2.COM_NOM) = UPPER(:arr)";
    
    $stmt = $conn->prepare($sql_recherche);
    $stmt->execute([
        ':dep' => $com_depart,
        ':arr' => $com_arrivee
    ]);
    $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Recherche de voyage – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <?php include_once("../PHP/nav.php"); ?>

    <main>
        <h1>Recherche de voyage</h1>
        
        <form action="recherche.php" method="post">
            <label for="COM_DEPART">Ville de départ :</label>
            <select id="COM_DEPART" name="COM_DEPART" required>
                <option value="">Sélectionnez une ville</option>
                <?php foreach ($liste_communes as $commune) { ?>
                    <option value="<?php echo htmlspecialchars($commune['COM_NOM']); ?>" <?php if (isset($_POST['COM_DEPART']) && $_POST['COM_DEPART'] == $commune['COM_NOM']) { echo 'selected'; } ?>>
                        <?php echo htmlspecialchars($commune['COM_NOM']); ?>
                    </option>
                <?php } ?>
            </select><br><br>

            <label for="COM_ARRIVEE">Ville d'arrivée :</label>
            <select id="COM_ARRIVEE" name="COM_ARRIVEE" required>
                <option value="">Sélectionnez une ville</option>
                <?php foreach ($liste_communes as $commune) { ?>
                    <option value="<?php echo htmlspecialchars($commune['COM_NOM']); ?>" <?php if (isset($_POST['COM_ARRIVEE']) && $_POST['COM_ARRIVEE'] == $commune['COM_NOM']) { echo 'selected'; } ?>>
                        <?php echo htmlspecialchars($commune['COM_NOM']); ?>
                    </option>
                <?php } ?>
            </select><br><br>

            <label for="DATE_VOYAGE">Date de voyage :</label>
            <input type="date" id="DATE_VOYAGE" name="DATE_VOYAGE" value="<?php if (isset($_POST['DATE_VOYAGE'])) { echo htmlspecialchars($_POST['DATE_VOYAGE']); } ?>" required><br><br>

            <input type="submit" name="rechercher" value="Rechercher">
        </form>

        <div id="resultats" style="margin-top: 2rem;">
            <?php if ($recherche_faite) { ?>
                <h2>Trajets disponibles</h2>
                <?php if (count($trajets) === 0) { ?>
                    <p>Aucun car direct disponible entre ces deux communes.</p>
                <?php } else { ?>
                    <table style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Ligne disponible</th>
                                <th>Départ</th>
                                <th>Arrivée</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trajets as $trajet) { ?>
                                <tr>
                                    <td>Ligne <?php echo htmlspecialchars($trajet['LIG_NUM']); ?></td>
                                    <td><?php echo htmlspecialchars($com_depart); ?></td>
                                    <td><?php echo htmlspecialchars($com_arrivee); ?></td>
                                    <td>
                                        <form action="traitement_reservation.php" method="post">
                                            <input type="hidden" name="LIG_NUM" value="<?php echo htmlspecialchars($trajet['LIG_NUM']); ?>">
                                            <input type="hidden" name="DATE_VOYAGE" value="<?php echo htmlspecialchars($date_voyage); ?>">
                                            <input type="submit" value="Réserver ce voyage">
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            <?php } ?>
        </div>
    </main>

    <?php include_once("../PHP/footer.php"); ?>
</body>
</html>