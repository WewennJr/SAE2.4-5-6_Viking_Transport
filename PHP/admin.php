<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

// Vérification accès admin
if (!isset($_SESSION['num_client'])) {
    header("Location: formulaire_connexion.php");
    exit();
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// Vérification grade admin
$num = $_SESSION['num_client'];
$sql = "SELECT cli_grade FROM vik_client WHERE cli_num = $num";
$tab = LireDonneesPDO3($conn, $sql, $table);
if (!isset($tab[0]['CLI_GRADE']) || $tab[0]['CLI_GRADE'] != 'admin') {
    header("Location: ../HTML/accueil.php");
    exit();
}

// ── Stat 1 : Lignes les plus fréquentées ──────────────────────────────────
$sql1 = "SELECT lig_num, count(*) AS NB FROM vik_etape GROUP BY lig_num ORDER BY count(*) DESC";
$lignes_pop = LireDonneesPDO3($conn, $sql1, $t1);

// ── Stat 2 : Top 10 heures les plus fréquentées sur le réseau ────────────
$sql2 = "SELECT * FROM (
    SELECT to_char(eta_heure, 'HH24') || 'h' AS HEURE, count(*) AS NB
    FROM vik_etape
    GROUP BY to_char(eta_heure, 'HH24')
    ORDER BY count(*) DESC
) WHERE rownum <= 10";
$heures_pop = LireDonneesPDO3($conn, $sql2, $t2);

// ── Stat 3 : Meilleurs clients (top 10 par nb réservations) ──────────────
$sql3 = "SELECT * FROM (
    SELECT cli_prenom || ' ' || cli_nom AS CLIENT, count(*) AS NB
    FROM vik_reservation r
    JOIN vik_client c USING(cli_num)
    WHERE r.cli_num != 0
    GROUP BY cli_prenom || ' ' || cli_nom
    ORDER BY count(*) DESC
) WHERE rownum <= 10";
$top_clients = LireDonneesPDO3($conn, $sql3, $t3);

// ── Stat 4 : Clients inactifs (pas connectés depuis > 1 an) ──────────────
$sql4 = "SELECT cli_prenom, cli_nom, cli_date_connec,
    ROUND(SYSDATE - cli_date_connec) AS JOURS
    FROM vik_client
    WHERE SYSDATE - cli_date_connec > 365
    AND cli_grade != 'admin'
    ORDER BY cli_date_connec ASC";
$inactifs = LireDonneesPDO3($conn, $sql4, $t4);

// ── Préparer les données JSON pour Chart.js ───────────────────────────────
$lignes_labels = [];
$lignes_data   = [];
foreach ($lignes_pop as $row) {
    $lignes_labels[] = $row['LIG_NUM'];
    $lignes_data[]   = (int)$row['NB'];
}

$heures_labels = [];
$heures_data   = [];
foreach ($heures_pop as $row) {
    $heures_labels[] = $row['HEURE'];
    $heures_data[]   = (int)$row['NB'];
}

$clients_labels = [];
$clients_data   = [];
foreach ($top_clients as $row) {
    $clients_labels[] = $row['CLIENT'];
    $clients_data[]   = (int)$row['NB'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<?php include_once "nav.php"; ?>
<main>
    <h1>Tableau de bord admin</h1>

    <div class="admin-grid">

        <!-- Lignes les plus fréquentées -->
        <div class="stat-card">
            <h2>Lignes les plus fréquentées</h2>
            <canvas id="chartLignes"></canvas>
        </div>

        <!-- Heures de pointe -->
        <div class="stat-card">
            <h2>Top 10 des heures de pointe</h2>
            <canvas id="chartHeures"></canvas>
        </div>

        <!-- Meilleurs clients -->
        <div class="stat-card stat-card-full">
            <h2>Top 10 des meilleurs clients</h2>
            <canvas id="chartClients"></canvas>
        </div>

        <!-- Clients inactifs -->
        <div class="stat-card stat-card-full">
            <h2>
                Clients inactifs
                <span class="badge-rouge"><?php echo count($inactifs); ?></span>
            </h2>
            <?php if (count($inactifs) == 0) { ?>
                <p>Aucun client inactif depuis plus d'un an.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Dernière connexion</th>
                            <th>Jours d'inactivité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inactifs as $client) { ?>
                        <tr>
                            <td><?php echo $client['CLI_PRENOM']; ?></td>
                            <td><?php echo $client['CLI_NOM']; ?></td>
                            <td><?php echo $client['CLI_DATE_CONNEC']; ?></td>
                            <td>
                                <?php
                                $jours = (int)$client['JOURS'];
                                if ($jours > 730) {
                                    echo "<span class='badge-rouge'>" . $jours . " jours (à supprimer)</span>";
                                } else {
                                    echo $jours . " jours";
                                }
                                ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

    </div>
</main>

<?php include_once "footer.php"; ?>

<script>
    const rouge      = '#C0392B';
    const rougeClair = 'rgba(192, 57, 43, 0.7)';
    const noir       = '#1a1a1a';
    const gris       = '#4a4a4a';

    // ── Chart 1 : Lignes ──────────────────────────────────────────────────
    new Chart(document.getElementById('chartLignes'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($lignes_labels); ?>,
            datasets: [{
                label: 'Nombre de voyageurs',
                data: <?php echo json_encode($lignes_data); ?>,
                backgroundColor: rougeClair,
                borderColor: rouge,
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: gris } },
                x: { ticks: { color: gris } }
            }
        }
    });

    // ── Chart 2 : Heures ─────────────────────────────────────────────────
    new Chart(document.getElementById('chartHeures'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($heures_labels); ?>,
            datasets: [{
                label: 'Passages',
                data: <?php echo json_encode($heures_data); ?>,
                backgroundColor: 'rgba(192, 57, 43, 0.15)',
                borderColor: rouge,
                borderWidth: 2,
                pointBackgroundColor: rouge,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: gris } },
                x: { ticks: { color: gris } }
            }
        }
    });

    // ── Chart 3 : Meilleurs clients ───────────────────────────────────────
    new Chart(document.getElementById('chartClients'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($clients_labels); ?>,
            datasets: [{
                label: 'Réservations',
                data: <?php echo json_encode($clients_data); ?>,
                backgroundColor: rougeClair,
                borderColor: rouge,
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { color: gris } },
                y: { ticks: { color: gris } }
            }
        }
    });
</script>
</body>
</html>