<?php
session_start();

if (!isset($_SESSION["num_client"])) {
    header("Location: formulaire_connexion.php");
    exit;
}

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// --- Informations du client ---
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
$grade    = $client['CLI_GRADE'];   // pour tester si l'utilisateur est admin

// --- Réservations du client ---
$stmtResa = $conn->prepare("
    SELECT *
    FROM VIK_RESERVATION
    WHERE CLI_NUM = :num
    ORDER BY RES_DATE DESC
");
$stmtResa->execute([':num' => $_SESSION['num_client']]);
$reservations = $stmtResa->fetchAll(PDO::FETCH_ASSOC);

$conn = null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="description" content="Viking Transport">
    <title>Mon compte</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/compte.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <div class="compte-entete">
        <h1>Mon espace client</h1>
        <p class="sous-titre">Bonjour <?= htmlspecialchars($prenom) ?>, ravis de vous revoir</p>

        <?php if ($grade === 'administrateur') { ?>
            <a href="admin.php" class="lien-admin">⚙ Accéder au tableau de bord admin</a>
        <?php } ?>
    </div>

    <div class="compte-grille">
        <!-- Carte infos personnelles -->
        <section class="carte">
            <h2>Mes informations</h2>
            <ul class="info-liste">
                <li><span class="libelle">Nom</span><span class="valeur"><?= htmlspecialchars($nom) ?></span></li>
                <li><span class="libelle">Prénom</span><span class="valeur"><?= htmlspecialchars($prenom) ?></span></li>
                <li><span class="libelle">Email</span><span class="valeur"><?= htmlspecialchars($courriel) ?></span></li>
                <li><span class="libelle">Téléphone</span><span class="valeur"><?= htmlspecialchars($tel) ?></span></li>
                <li><span class="libelle">Ville</span><span class="valeur"><?= htmlspecialchars($ville) ?></span></li>
            </ul>
            <a href="modifier_profil.php" class="lien-modifier">Modifier mes informations</a>
        </section>

        <!-- Carte points fidélité -->
        <section class="carte-points">
            <h2>Points de fidélité</h2>
            <div class="points-valeur"><?= htmlspecialchars($points) ?></div>
            <div class="points-label">points cumulés</div>
        </section>
    </div>

    <!-- Tableau réservations -->
    <section class="carte-reservations">
        <h2>Mes réservations</h2>
        <table class="table-reservations">
            <thead>
                <tr>
                    <th>N° réservation</th>
                    <th>Date</th>
                    <th>Départ</th>
                    <th>Arrivée</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reservations) > 0) { ?>
                    <?php foreach ($reservations as $resa) { ?>
                        <tr>
                            <td><?= htmlspecialchars($resa['RES_NUM'] ?? '') ?></td>
                            <td><?= htmlspecialchars($resa['RES_DATE'] ?? '') ?></td>
                            <td><?= htmlspecialchars($resa['RES_DEPART'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($resa['RES_ARRIVEE'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($resa['RES_STATUT'] ?? '-') ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="5" class="aucune-resa">Vous n'avez aucune réservation pour le moment.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
</main>

<?php include_once "footer.php"; ?>

</body>
</html>