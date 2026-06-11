<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

// Lignes les plus fréquentées (ordre alphabétique)
$sql_lignes = "SELECT lig_num, count(*) AS NB FROM vik_etape GROUP BY lig_num ORDER BY lig_num ASC";
$lignes_pop = LireDonneesPDO3($conn, $sql_lignes, $t);

// Top 10 heures de pointe
$sql_heures = "SELECT * FROM (
    SELECT to_char(eta_heure, 'HH24') || 'h' AS HEURE, count(*) AS NB
    FROM vik_etape
    GROUP BY to_char(eta_heure, 'HH24')
    ORDER BY count(*) DESC
) WHERE rownum <= 10";
$heures_pop = LireDonneesPDO3($conn, $sql_heures, $t);

// Top 10 lignes x heure
$sql_lignes_heures = "SELECT * FROM (
    SELECT lig_num AS LIGNE, to_char(eta_heure, 'HH24') || 'h' AS HEURE, count(*) AS NB
    FROM vik_etape
    GROUP BY lig_num, to_char(eta_heure, 'HH24')
    ORDER BY count(*) DESC
) WHERE rownum <= 10";
$lignes_heures = LireDonneesPDO3($conn, $sql_lignes_heures, $t);

// Top 10 meilleurs clients
$sql_clients_top = "SELECT * FROM (
    SELECT c.cli_prenom || ' ' || c.cli_nom AS CLIENT, count(*) AS NB
    FROM vik_reservation r
    JOIN vik_client c ON r.cli_num = c.cli_num
    WHERE r.cli_num != 0
    GROUP BY c.cli_prenom || ' ' || c.cli_nom
    ORDER BY count(*) DESC
) WHERE rownum <= 10";
$top_clients = LireDonneesPDO3($conn, $sql_clients_top, $t);

// Réservations par mois
$sql_mois = "SELECT to_char(res_date, 'MM/YYYY') AS MOIS, count(*) AS NB
    FROM vik_reservation
    GROUP BY to_char(res_date, 'MM/YYYY')
    ORDER BY to_char(res_date, 'MM/YYYY') ASC";
$resas_mois = LireDonneesPDO3($conn, $sql_mois, $t);

// Clients inactifs > 1 an
$sql_inactifs = "SELECT cli_num, cli_prenom, cli_nom, cli_date_connec,
    ROUND(SYSDATE - cli_date_connec) AS JOURS
    FROM vik_client
    WHERE SYSDATE - cli_date_connec > 365
    AND cli_grade != 'administrateur'
    ORDER BY cli_date_connec ASC";
$inactifs = LireDonneesPDO3($conn, $sql_inactifs, $t);

// Liste des clients
$sql_clients = "SELECT cli_num, cli_prenom, cli_nom, cli_courriel, cli_nb_points_ec
    FROM vik_client
    WHERE cli_grade != 'administrateur' AND cli_num != 0 AND cli_num != 1
    ORDER BY cli_nom ASC";
$clients = LireDonneesPDO3($conn, $sql_clients, $t);

// Réservations par client
$reservations = [];
foreach ($clients as $client) {
    $cnum = $client['CLI_NUM'];
    $sql_r = "SELECT r.res_num, r.res_date, r.res_prix_tot,
        MIN(cd.com_nom) AS DEPART,
        MAX(ca.com_nom) AS ARRIVEE
        FROM vik_reservation r
        JOIN vik_etape e ON r.cli_num = e.cli_num AND r.res_num = e.res_num
        JOIN vik_commune cd ON e.com_code_insee_depart = cd.com_code_insee
        JOIN vik_commune ca ON e.com_code_insee_arrivee = ca.com_code_insee
        WHERE r.cli_num = $cnum
        GROUP BY r.res_num, r.res_date, r.res_prix_tot
        ORDER BY r.res_date DESC";
    $reservations[$cnum] = LireDonneesPDO3($conn, $sql_r, $t);
}

// JSON pour Chart.js
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

$lh_labels = [];
$lh_data   = [];
foreach ($lignes_heures as $row) {
    $lh_labels[] = $row['LIGNE'] . ' ' . $row['HEURE'];
    $lh_data[]   = (int)$row['NB'];
}

$top_labels = [];
$top_data   = [];
foreach ($top_clients as $row) {
    $top_labels[] = $row['CLIENT'];
    $top_data[]   = (int)$row['NB'];
}

$mois_labels = [];
$mois_data   = [];
foreach ($resas_mois as $row) {
    $mois_labels[] = $row['MOIS'];
    $mois_data[]   = (int)$row['NB'];
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
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        /* min-width: 0 est indispensable sur les enfants directs d'un grid :
           sans ça, les colonnes refusent de rétrécir en dessous de leur contenu
           et le breakpoint ne s'applique jamais visuellement au resize */
        .admin-col {
            min-width: 0;
        }

        /* Canvas Chart.js : sans max-width les graphiques débordent de leur colonne */
        .stat-card canvas {
            max-height: 220px;
            max-width: 100%;
        }

        /* Breakpoint relevé à 1100px pour couvrir les cas tablette paysage,
           et les tableaux/cards qui débordent avant 900px */
        @media (max-width: 1100px) {
            .admin-layout { grid-template-columns: 1fr; }
        }

        .stat-card {
            background: #fff;
            border-left: 4px solid var(--rouge);
            border-radius: 0 6px 6px 0;
            padding: 1.5rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .stat-card h3 {
            font-family: var(--font-display);
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--noir);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        thead tr {
            background-color: var(--noir);
            color: var(--blanc);
            font-family: var(--font-display);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        thead th { padding: 0.6rem 0.8rem; text-align: left; }
        tbody tr:nth-child(even) { background-color: var(--gris-clair); }
        tbody td { padding: 0.5rem 0.8rem; color: var(--gris); }

        .client-card {
            background: #fff;
            border-left: 4px solid var(--rouge);
            border-radius: 0 6px 6px 0;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .client-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .client-info { flex: 1; }

        .client-nom {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1rem;
            color: var(--noir);
            text-transform: uppercase;
        }

        .client-details {
            font-size: 0.85rem;
            color: var(--gris);
            margin-top: 0.2rem;
        }

        .client-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-toggle, .btn-suppr {
            background: none;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--gris);
            transition: border-color 0.2s, color 0.2s, background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-toggle:hover { border-color: var(--rouge); color: var(--rouge); }
        .btn-suppr:hover { background-color: var(--rouge); border-color: var(--rouge); color: #fff; }

        .resas-panel {
            display: none;
            margin-top: 1rem;
            border-top: 1px solid var(--gris-clair);
            padding-top: 0.75rem;
        }

        .resas-panel.open { display: block; }

        .resa-ligne {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 0.5rem 1rem;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gris-clair);
            font-size: 0.88rem;
            color: var(--gris);
        }

        .resa-ligne:last-child { border-bottom: none; }
        .resa-num { font-family: var(--font-display); font-weight: 600; color: var(--rouge); font-size: 0.85rem; }
        .resa-trajet { color: var(--noir); font-weight: 500; }
        .resa-date { font-size: 0.82rem; color: var(--gris); }
        .resa-prix { font-family: var(--font-display); font-weight: 600; color: var(--noir); white-space: nowrap; }
        .no-resa { font-size: 0.88rem; color: var(--gris); font-style: italic; }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 500;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal-box {
            background: #fff;
            border-top: 4px solid var(--rouge);
            border-radius: 0 0 6px 6px;
            padding: 2rem 2.5rem;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }

        .modal-box h3 {
            font-family: var(--font-display);
            font-size: 1.2rem;
            text-transform: uppercase;
            color: var(--noir);
            margin-bottom: 0.75rem;
        }

        .modal-box p { font-size: 0.95rem; color: var(--gris); margin-bottom: 1.5rem; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; }

        .btn-annuler {
            background: none;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            padding: 0.6rem 1.2rem;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            cursor: pointer;
            color: var(--gris);
            transition: border-color 0.2s;
        }

        .btn-annuler:hover { border-color: var(--gris); }

        .btn-confirmer {
            background-color: var(--rouge);
            border: 2px solid var(--rouge);
            border-radius: 4px;
            padding: 0.6rem 1.2rem;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            cursor: pointer;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .btn-confirmer:hover { background-color: var(--rouge-dark); border-color: var(--rouge-dark); }

        .inactifs-wrap { margin-top: 2rem; }

        /* Bouton flèche dans la table des inactifs */
        .btn-goto {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--rouge);
            padding: 0;
            line-height: 1;
        }
        .btn-goto:hover { opacity: 0.7; }
    </style>
</head>
<body>
<?php include_once "nav.php"; ?>
<main>
    <h1>Tableau de bord admin</h1>

    <div class="admin-layout">

        <!-- Colonne gauche : graphiques -->
        <div class="admin-col admin-col-stats">

            <div class="stat-card">
                <h3>Lignes les plus fréquentées</h3>
                <canvas id="chartLignes"></canvas>
            </div>

            <div class="stat-card">
                <h3>Top 10 heures de pointe sur le réseau</h3>
                <canvas id="chartHeures"></canvas>
            </div>

            <div class="stat-card">
                <h3>Top 10 lignes × heure</h3>
                <canvas id="chartLignesHeures"></canvas>
            </div>

            <div class="stat-card">
                <h3>Top 10 meilleurs clients</h3>
                <canvas id="chartClients"></canvas>
            </div>

            <div class="stat-card">
                <h3>Réservations par mois</h3>
                <canvas id="chartMois"></canvas>
            </div>

        </div>

        <!-- Colonne droite : liste clients -->
        <div class="admin-col admin-col-clients">
            <h2>Clients (<?php echo count($clients); ?>)</h2>

            <?php foreach ($clients as $client) { ?>
            <?php $cnum = $client['CLI_NUM']; ?>
            <div class="client-card" id="client-<?php echo $cnum; ?>">
                <div class="client-header">
                    <div class="client-info">
                        <div class="client-nom"><?php echo $client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']; ?></div>
                        <div class="client-details">
                            <?php echo $client['CLI_COURRIEL']; ?> &nbsp;·&nbsp;
                            <?php echo $client['CLI_NB_POINTS_EC']; ?> pts
                        </div>
                    </div>
                    <div class="client-actions">
                        <button class="btn-toggle" onclick="toggleResas(<?php echo $cnum; ?>)" title="Voir les réservations">▼</button>
                        <button class="btn-suppr" onclick="demanderSuppr(<?php echo $cnum; ?>, '<?php echo addslashes($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']); ?>')" title="Supprimer">🗑</button>
                    </div>
                </div>

                <div class="resas-panel" id="resas-<?php echo $cnum; ?>">
                    <?php if (empty($reservations[$cnum])) { ?>
                        <p class="no-resa">Aucune réservation.</p>
                    <?php } else { ?>
                        <?php foreach ($reservations[$cnum] as $resa) { ?>
                        <div class="resa-ligne">
                            <span class="resa-num">#<?php echo $resa['RES_NUM']; ?></span>
                            <span class="resa-trajet"><?php echo $resa['DEPART']; ?> → <?php echo $resa['ARRIVEE']; ?></span>
                            <span class="resa-date"><?php echo $resa['RES_DATE']; ?></span>
                            <span class="resa-prix"><?php echo $resa['RES_PRIX_TOT']; ?> €</span>
                        </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>

    </div>

    <!-- Clients inactifs pleine largeur -->
    <div class="inactifs-wrap">
        <div class="stat-card">
            <h3>Clients inactifs (<?php echo count($inactifs); ?>)</h3>
            <?php if (count($inactifs) == 0) { ?>
                <p>Aucun client inactif depuis plus d'un an.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Dernière connexion</th>
                            <th>Inactivité</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inactifs as $c) { ?>
                        <tr>
                            <td><?php echo $c['CLI_PRENOM']; ?></td>
                            <td><?php echo $c['CLI_NOM']; ?></td>
                            <td><?php echo $c['CLI_DATE_CONNEC']; ?></td>
                            <td>
                                <?php
                                $j = (int)$c['JOURS'];
                                if ($j > 730) {
                                    echo "<strong style='color:var(--rouge)'>" . $j . " j (à supprimer)</strong>";
                                } else {
                                    echo $j . " j";
                                }
                                ?>
                            </td>
                            <!-- FIX SCROLL : bouton JS au lieu du lien ancre natif,
                                 pour compenser la hauteur de la navbar -->
                            <td>
                                <button class="btn-goto" onclick="scrollToClient(<?php echo $c['CLI_NUM']; ?>)" title="Aller au client">↗</button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </div>

</main>

<!-- Modal confirmation suppression -->
<div class="modal-overlay" id="modal">
    <div class="modal-box">
        <h3>Confirmer la suppression</h3>
        <p id="modal-texte"></p>
        <div class="modal-actions">
            <button class="btn-annuler" onclick="fermerModal()">Annuler</button>
            <a class="btn-confirmer" id="modal-lien" href="#">Supprimer</a>
        </div>
    </div>
</div>

<?php include_once "footer.php"; ?>

<script>
    const rouge      = '#C0392B';
    const rougeClair = 'rgba(192, 57, 43, 0.7)';
    const gris       = '#4a4a4a';

    new Chart(document.getElementById('chartLignes'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($lignes_labels); ?>,
            datasets: [{ data: <?php echo json_encode($lignes_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris, font: { size: 10 } } } } }
    });

    new Chart(document.getElementById('chartHeures'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($heures_labels); ?>,
            datasets: [{ data: <?php echo json_encode($heures_data); ?>, backgroundColor: 'rgba(192,57,43,0.15)', borderColor: rouge, borderWidth: 2, pointBackgroundColor: rouge, fill: true, tension: 0.3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
    });

    new Chart(document.getElementById('chartLignesHeures'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($lh_labels); ?>,
            datasets: [{ data: <?php echo json_encode($lh_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 9 } } } } }
    });

    new Chart(document.getElementById('chartClients'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($top_labels); ?>,
            datasets: [{ data: <?php echo json_encode($top_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 10 } } } } }
    });

    new Chart(document.getElementById('chartMois'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($mois_labels); ?>,
            datasets: [{ data: <?php echo json_encode($mois_data); ?>, backgroundColor: 'rgba(192,57,43,0.15)', borderColor: rouge, borderWidth: 2, pointBackgroundColor: rouge, fill: true, tension: 0.3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
    });

    function toggleResas(num) {
        var panel = document.getElementById('resas-' + num);
        panel.classList.toggle('open');
    }

    function demanderSuppr(num, nom) {
        document.getElementById('modal-texte').innerText = 'Voulez-vous vraiment supprimer le compte de ' + nom + ' ? Cette action est irréversible.';
        document.getElementById('modal-lien').href = 'supprimer_client.php?id=' + num;
        document.getElementById('modal').classList.add('open');
    }

    function fermerModal() {
        document.getElementById('modal').classList.remove('open');
    }

    /**
     * FIX SCROLL : scroll vers la carte client en compensant la hauteur de la navbar.
     * On récupère la hauteur réelle de la <nav> dynamiquement, donc ça s'adapte
     * même si la navbar change de taille selon la résolution.
     */
    function scrollToClient(num) {
        const el = document.getElementById('client-' + num);
        if (!el) return;
        const navbar = document.querySelector('nav');
        const navbarHeight = navbar ? navbar.offsetHeight : 80;
        const top = el.getBoundingClientRect().top + window.scrollY - navbarHeight - 16;
        window.scrollTo({ top: top, behavior: 'smooth' });
    }
</script>
</body>
</html>