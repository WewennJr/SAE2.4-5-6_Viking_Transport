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

// ── Traitement modification client ───────────────────────────────────────────
$msg_modif = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modif_client') {
    $id = (int) trim($_POST['cli_num']);
    $prenom = trim($_POST['cli_prenom']);
    $nom = trim($_POST['cli_nom']);
    $mail = trim($_POST['cli_courriel']);
    $points = (int) trim($_POST['cli_nb_points_ec']);
    $grade = trim($_POST['cli_grade']);
    $typ_num = (int) trim($_POST['typ_num']);
    $grades_ok = ['client', 'administrateur'];
    if (!in_array($grade, $grades_ok))
        $grade = 'client';
    $sql_u = "UPDATE vik_client SET cli_prenom = :prenom, cli_nom = :nom, cli_courriel = :mail, cli_nb_points_ec = :points, cli_nb_points_tot = :points, cli_grade = :grade, typ_num = :typ_num WHERE cli_num = :id";
    $cur = preparerRequetePDO($conn, $sql_u);
    majDonneesPrepareesTabPDO($cur, [':prenom' => $prenom, ':nom' => $nom, ':mail' => $mail, ':points' => $points, ':grade' => $grade, ':typ_num' => $typ_num, ':id' => $id]);
    $conn->exec("COMMIT");
    $msg_modif = "client_$id";
}

// ── Traitement modification ligne ────────────────────────────────────────────
$msg_ligne = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modif_ligne') {
    $lig = trim($_POST['lig_num']);
    $insee_deb = trim($_POST['com_code_insee_debu']);
    $insee_term = trim($_POST['com_code_insee_term']);
    $sql_ul = "UPDATE vik_ligne SET com_code_insee_debu = :debu, com_code_insee_term = :term WHERE TRIM(lig_num) = :lig";
    $cur = preparerRequetePDO($conn, $sql_ul);
    majDonneesPrepareesTabPDO($cur, [':debu' => $insee_deb, ':term' => $insee_term, ':lig' => $lig]);
    $conn->exec("COMMIT");
    $msg_ligne = $lig;
}

// ── Traitement modification noeud (horaire / distance) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modif_noeud') {
    $lig       = trim($_POST['lig_num']);
    $arret     = trim($_POST['com_code_insee_arret']);
    $heure_old = trim($_POST['noe_heure_passage_old']);   // ex "08:32"
    $heure_new = trim($_POST['noe_heure_passage']);       // ex "08:45"
    // Valeurs en CHAÎNE avec un point comme séparateur
    $dist_prochain  = str_replace(',', '.', trim($_POST['noe_distance_prochain']));
    $duree_prochain = str_replace(',', '.', trim($_POST['noe_duree_prochain']));

    $sql_dd = "UPDATE vik_noeud
               SET noe_distance_prochain = TO_NUMBER(:dist, '99999999D999999', 'NLS_NUMERIC_CHARACTERS=''.,'''),
                   noe_duree_prochain    = TO_NUMBER(:duree, '99999999D999999', 'NLS_NUMERIC_CHARACTERS=''.,''')
               WHERE TRIM(lig_num) = :lig
                 AND TRIM(com_code_insee_arret) = :arret";
    $cur1 = $conn->prepare($sql_dd);
    $cur1->execute([
        ':dist'  => (string)$dist_prochain,
        ':duree' => (string)$duree_prochain,
        ':lig'   => $lig,
        ':arret' => $arret,
    ]);

    // 2) Heure : on ne change QUE le passage concerné, en gardant sa date d'origine
    $sql_h = "UPDATE vik_noeud
              SET noe_heure_passage = TO_DATE(TO_CHAR(noe_heure_passage,'YYYY-MM-DD') || ' ' || :heure_new, 'YYYY-MM-DD HH24:MI')
              WHERE TRIM(lig_num) = :lig
                AND TRIM(com_code_insee_arret) = :arret
                AND TO_CHAR(noe_heure_passage, 'HH24:MI') = :heure_old";
    $cur2 = $conn->prepare($sql_h);
    $cur2->execute([
        ':heure_new' => $heure_new,
        ':lig'       => $lig,
        ':arret'     => $arret,
        ':heure_old' => $heure_old,
    ]);

    $conn->exec("COMMIT");
    if ($msg_ligne === '') $msg_ligne = $lig;
}

// ── Stats générales ───────────────────────────────────────────────────────────
$sql_lignes = "SELECT lig_num AS LIG_NUM, count(*) AS NB FROM vik_etape GROUP BY lig_num ORDER BY
    TO_NUMBER(REGEXP_SUBSTR(lig_num, '^[0-9]+')),
    REGEXP_SUBSTR(lig_num, '[A-Za-z]+$')";
$lignes_pop = LireDonneesPDO3($conn, $sql_lignes, $t);

$sql_clients_top = "SELECT * FROM (
    SELECT c.cli_prenom || ' ' || c.cli_nom AS CLIENT, count(*) AS NB
    FROM vik_reservation r JOIN vik_client c ON r.cli_num = c.cli_num
    WHERE r.cli_num != 0 GROUP BY c.cli_prenom || ' ' || c.cli_nom ORDER BY count(*) DESC
) WHERE rownum <= 10";
$top_clients = LireDonneesPDO3($conn, $sql_clients_top, $t);

$sql_mois = "SELECT to_char(res_date, 'MM/YYYY') AS MOIS, count(*) AS NB
    FROM vik_reservation GROUP BY to_char(res_date, 'MM/YYYY')
    ORDER BY MIN(res_date) ASC";
$resas_mois = LireDonneesPDO3($conn, $sql_mois, $t);

$sql_semaine = "SELECT TO_CHAR(res_date, 'D') AS JOUR_NUM, TO_CHAR(res_date, 'DY', 'NLS_DATE_LANGUAGE=FRENCH') AS JOUR, count(*) AS NB
    FROM vik_reservation
    GROUP BY TO_CHAR(res_date, 'D'), TO_CHAR(res_date, 'DY', 'NLS_DATE_LANGUAGE=FRENCH')
    ORDER BY TO_CHAR(res_date, 'D')";
$resas_semaine = LireDonneesPDO3($conn, $sql_semaine, $t);

$sql_dist = "SELECT * FROM (
    SELECT c.cli_prenom || ' ' || c.cli_nom AS CLIENT,
        ROUND(SUM(e.eta_distance), 1) AS DIST_TOT
    FROM vik_etape e
    JOIN vik_client c ON c.cli_num = e.cli_num
    WHERE e.cli_num != 0
    GROUP BY c.cli_prenom || ' ' || c.cli_nom
    ORDER BY DIST_TOT DESC
) WHERE rownum <= 10";
$dist_clients = LireDonneesPDO3($conn, $sql_dist, $t);

$sql_dep = "SELECT d.dep_nom AS DEP, count(*) AS NB
    FROM vik_reservation r
    JOIN vik_client c ON c.cli_num = r.cli_num
    JOIN vik_departement d ON d.dep_num = c.dep_num
    WHERE r.cli_num != 0
    GROUP BY d.dep_nom
    ORDER BY count(*) DESC";
$resas_dep = LireDonneesPDO3($conn, $sql_dep, $t);

// ── Stats interactives ────────────────────────────────────────────────────────
$sql_villes = "SELECT DISTINCT com_nom AS COM_NOM FROM vik_commune ORDER BY com_nom ASC";
$villes_list = LireDonneesPDO3($conn, $sql_villes, $t);

$stat_resa_villes = null;
$stat_v1 = isset($_GET['v1']) ? trim($_GET['v1']) : '';
$stat_v2 = isset($_GET['v2']) ? trim($_GET['v2']) : '';
if ($stat_v1 !== '' && $stat_v2 !== '') {
    $sql_sv = "SELECT r.res_num, r.res_date, r.res_prix_tot,
        c_cli.cli_prenom || ' ' || c_cli.cli_nom AS CLIENT
        FROM VIK_ETAPE e
        JOIN VIK_COMMUNE c_dep ON c_dep.COM_CODE_INSEE = e.COM_CODE_INSEE_DEPART
        JOIN VIK_COMMUNE c_arr ON c_arr.COM_CODE_INSEE = e.COM_CODE_INSEE_ARRIVEE
        JOIN VIK_RESERVATION r ON r.cli_num = e.cli_num AND r.res_num = e.res_num
        JOIN VIK_CLIENT c_cli ON c_cli.cli_num = r.cli_num
        WHERE c_dep.COM_NOM = :v1 AND c_arr.COM_NOM = :v2
        ORDER BY r.res_date DESC";
    $cur_sv = preparerRequetePDO($conn, $sql_sv);
    $cur_sv->execute([':v1' => $stat_v1, ':v2' => $stat_v2]);
    $stat_resa_villes = $cur_sv->fetchAll(PDO::FETCH_ASSOC);
}

$stat_lignes_ville = null;
$stat_lv = isset($_GET['lv']) ? trim($_GET['lv']) : '';
if ($stat_lv !== '') {
    $sql_slv = "SELECT DISTINCT n.LIG_NUM,
        cd.com_nom AS NOM_DEBU, ct.com_nom AS NOM_TERM
        FROM VIK_NOEUD n
        JOIN VIK_COMMUNE c ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
        JOIN VIK_LIGNE l ON l.lig_num = n.lig_num
        JOIN VIK_COMMUNE cd ON cd.com_code_insee = l.com_code_insee_debu
        JOIN VIK_COMMUNE ct ON ct.com_code_insee = l.com_code_insee_term
        WHERE c.COM_NOM = :lv
        ORDER BY n.LIG_NUM";
    $cur_slv = preparerRequetePDO($conn, $sql_slv);
    $cur_slv->execute([':lv' => $stat_lv]);
    $stat_lignes_ville = $cur_slv->fetchAll(PDO::FETCH_ASSOC);
}

$stat_dep_arr = null;
$stat_dav = isset($_GET['dav']) ? trim($_GET['dav']) : '';
if ($stat_dav !== '') {
    $sql_sdav = "SELECT e.cli_num, r.res_date, r.res_prix_tot,
        c_cli.cli_prenom || ' ' || c_cli.cli_nom AS CLIENT,
        CASE WHEN e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE THEN 'Départ' ELSE 'Arrivée' END AS SENS
        FROM VIK_COMMUNE c
        JOIN VIK_ETAPE e ON e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE
            OR e.COM_CODE_INSEE_ARRIVEE = c.COM_CODE_INSEE
        JOIN VIK_RESERVATION r ON r.cli_num = e.cli_num AND r.res_num = e.res_num
        JOIN VIK_CLIENT c_cli ON c_cli.cli_num = r.cli_num
        WHERE c.COM_NOM = :dav
        ORDER BY r.res_date DESC";
    $cur_sdav = preparerRequetePDO($conn, $sql_sdav);
    $cur_sdav->execute([':dav' => $stat_dav]);
    $stat_dep_arr = $cur_sdav->fetchAll(PDO::FETCH_ASSOC);
}

// ── Clients inactifs ──────────────────────────────────────────────────────────
$sql_inactifs = "SELECT cli_num, cli_prenom, cli_nom, cli_date_connec,
    ROUND(SYSDATE - cli_date_connec) AS JOURS
    FROM vik_client WHERE SYSDATE - cli_date_connec > 365 AND cli_grade != 'administrateur'
    ORDER BY cli_date_connec ASC";
$inactifs = LireDonneesPDO3($conn, $sql_inactifs, $t);

// ── Types de clients ──────────────────────────────────────────────────────────
$sql_types = "SELECT typ_num, typ_nom FROM vik_type_client ORDER BY typ_num";
$types_client = LireDonneesPDO3($conn, $sql_types, $t);

// ── Liste clients ─────────────────────────────────────────────────────────────
$sql_clients = "SELECT cli_num, cli_prenom, cli_nom, cli_courriel, cli_nb_points_ec, cli_grade, typ_num
    FROM vik_client
    WHERE cli_grade != 'administrateur' AND cli_num != 0 AND cli_num != 1
    ORDER BY cli_nom ASC";
$clients = LireDonneesPDO3($conn, $sql_clients, $t);

$reservations = [];
foreach ($clients as $client) {
    $cnum = $client['CLI_NUM'];
    $sql_r = "SELECT r.res_num, r.res_date, r.res_prix_tot,
        MIN(cd.com_nom) AS DEPART, MAX(ca.com_nom) AS ARRIVEE
        FROM vik_reservation r
        JOIN vik_etape e ON r.cli_num = e.cli_num AND r.res_num = e.res_num
        JOIN vik_commune cd ON e.com_code_insee_depart = cd.com_code_insee
        JOIN vik_commune ca ON e.com_code_insee_arrivee = ca.com_code_insee
        WHERE r.cli_num = $cnum
        GROUP BY r.res_num, r.res_date, r.res_prix_tot
        ORDER BY r.res_date DESC";
    $reservations[$cnum] = LireDonneesPDO3($conn, $sql_r, $t);
}

// ── Lignes & noeuds (horaires) ────────────────────────────────────────────────
$sql_all_lignes = "SELECT l.lig_num, l.com_code_insee_debu, l.com_code_insee_term,
    cd.com_nom AS NOM_DEBU, ct.com_nom AS NOM_TERM
    FROM vik_ligne l
    JOIN vik_commune cd ON l.com_code_insee_debu  = cd.com_code_insee
    JOIN vik_commune ct ON l.com_code_insee_term = ct.com_code_insee
    ORDER BY TO_NUMBER(REGEXP_SUBSTR(l.lig_num, '^[0-9]+')), REGEXP_SUBSTR(l.lig_num, '[A-Za-z]+$')";
$all_lignes = LireDonneesPDO3($conn, $sql_all_lignes, $t);

$noeuds_par_ligne = [];
foreach ($all_lignes as $lig) {
    $lnum = trim($lig['LIG_NUM']);
    $insee_deb = trim($lig['COM_CODE_INSEE_DEBU']);

    $sql_noeuds = "SELECT n.com_code_insee_arret,
                          n.com_code_insee_suivant,
                          TO_CHAR(n.noe_heure_passage, 'HH24:MI') AS NOE_HEURE_PASSAGE,
                          n.noe_distance_prochain,
                          n.noe_duree_prochain,
                          ca.com_nom AS NOM_ARRET,
                          cs.com_nom AS NOM_SUIVANT
                   FROM vik_noeud n
                   JOIN vik_commune ca ON ca.com_code_insee = n.com_code_insee_arret
                   LEFT JOIN vik_commune cs ON cs.com_code_insee = n.com_code_insee_suivant
                   WHERE n.lig_num = '" . addslashes($lnum) . "'";
    $rows = LireDonneesPDO3($conn, $sql_noeuds, $t);

    if (empty($rows)) {
        $noeuds_par_ligne[$lnum] = [];
        continue;
    }

    $by_arret = [];
    foreach ($rows as $r) {
        $by_arret[trim($r['COM_CODE_INSEE_ARRET'])] = $r;
    }

    $ordered = [];
    $current = $insee_deb;
    $visited = [];
    while ($current !== null && isset($by_arret[$current]) && !isset($visited[$current])) {
        $visited[$current] = true;
        $node = $by_arret[$current];
        $ordered[] = $node;
        $suivant = isset($node['COM_CODE_INSEE_SUIVANT']) ? trim($node['COM_CODE_INSEE_SUIVANT']) : null;
        $current = ($suivant === '' || $suivant === null) ? null : $suivant;
    }

    if (count($ordered) < count($rows)) {
        foreach ($rows as $r) {
            if (!isset($visited[trim($r['COM_CODE_INSEE_ARRET'])])) {
                $ordered[] = $r;
            }
        }
    }

    $noeuds_par_ligne[$lnum] = $ordered;
}

// ── JSON Chart.js ─────────────────────────────────────────────────────────────
$lignes_labels = [];
$lignes_data = [];
foreach ($lignes_pop as $row) {
    $lignes_labels[] = $row['LIG_NUM'];
    $lignes_data[] = (int) $row['NB'];
}

$top_labels = [];
$top_data = [];
foreach ($top_clients as $row) {
    $top_labels[] = $row['CLIENT'];
    $top_data[] = (int) $row['NB'];
}

$mois_labels = [];
$mois_data = [];
foreach ($resas_mois as $row) {
    $mois_labels[] = $row['MOIS'];
    $mois_data[] = (int) $row['NB'];
}

$semaine_labels = [];
$semaine_data = [];
foreach ($resas_semaine as $row) {
    $semaine_labels[] = $row['JOUR'];
    $semaine_data[] = (int) $row['NB'];
}

$dist_labels = [];
$dist_data = [];
foreach ($dist_clients as $row) {
    $dist_labels[] = $row['CLIENT'];
    $dist_data[] = (float) $row['DIST_TOT'];
}

$dep_labels = [];
$dep_data = [];
foreach ($resas_dep as $row) {
    $dep_labels[] = $row['DEP'];
    $dep_data[] = (int) $row['NB'];
}

$conn = null;
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
        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 2rem;
        }

        .admin-nav .btn {
            font-size: 0.88rem;
            padding: 0.6rem 1.2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            border: none;
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .admin-col {
            min-width: 0;
        }

        .stat-card {
            background: #fff;
            border-left: 4px solid var(--rouge);
            border-radius: 0 6px 6px 0;
            padding: 1.5rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
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

        .stat-card canvas {
            max-height: 220px;
            max-width: 100%;
        }

        .stat-interactive {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .stat-interactive .si-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
            min-width: 140px;
        }

        .stat-interactive label {
            font-family: var(--font-display);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gris);
        }

        .stat-interactive select {
            padding: 0.5rem 0.7rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            background: #fff;
            transition: border-color 0.2s;
        }

        .stat-interactive select:focus {
            outline: none;
            border-color: var(--rouge);
        }

        .stat-interactive .btn {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
            align-self: flex-end;
        }

        .stat-result {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 700;
            color: var(--rouge);
            text-align: center;
            padding: 0.5rem 0;
        }

        .stat-result-label {
            font-size: 0.82rem;
            color: var(--gris);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-result-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .stat-result-box {
            background: var(--gris-clair);
            border-radius: 4px;
            padding: 0.75rem;
            text-align: center;
        }

        .stat-result-box .val {
            font-family: var(--font-display);
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--rouge);
        }

        .stat-result-box .lbl {
            font-size: 0.78rem;
            color: var(--gris);
            text-transform: uppercase;
            letter-spacing: 0.04em;
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

        thead th {
            padding: 0.6rem 0.8rem;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: var(--gris-clair);
        }

        tbody td {
            padding: 0.5rem 0.8rem;
            color: var(--gris);
        }

        .clients-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .clients-header h2 {
            margin-bottom: 0;
        }

        .search-clients {
            flex: 1;
            min-width: 180px;
            padding: 0.5rem 0.9rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            transition: border-color 0.2s;
        }

        .search-clients:focus {
            outline: none;
            border-color: var(--rouge);
        }

        .client-card.hidden {
            display: none;
        }

        .client-extra {
            display: none;
        }

        .inactif-extra {
            display: none;
        }

        .client-card {
            background: #fff;
            border-left: 4px solid var(--rouge);
            border-radius: 0 6px 6px 0;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .client-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .client-info {
            flex: 1;
            min-width: 0;
        }

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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .client-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        .btn-ic {
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

        .btn-ic.btn-toggle:hover {
            border-color: var(--rouge);
            color: var(--rouge);
        }

        .btn-ic.btn-edit:hover {
            border-color: #2980b9;
            color: #2980b9;
        }

        .btn-ic.btn-suppr:hover {
            background-color: var(--rouge);
            border-color: var(--rouge);
            color: #fff;
        }

        .resas-panel {
            display: none;
            margin-top: 1rem;
            border-top: 1px solid var(--gris-clair);
            padding-top: 0.75rem;
        }

        .resas-panel.open {
            display: block;
        }

        .resa-ligne {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 0.5rem 1rem;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gris-clair);
            font-size: 0.88rem;
        }

        .resa-ligne:last-child {
            border-bottom: none;
        }

        .resa-num {
            font-family: var(--font-display);
            font-weight: 600;
            color: var(--rouge);
            font-size: 0.85rem;
        }

        .resa-trajet {
            color: var(--noir);
            font-weight: 500;
        }

        .resa-date {
            font-size: 0.82rem;
            color: var(--gris);
        }

        .resa-prix {
            font-family: var(--font-display);
            font-weight: 600;
            color: var(--noir);
            white-space: nowrap;
        }

        .no-resa {
            font-size: 0.88rem;
            color: var(--gris);
            font-style: italic;
        }

        .edit-client-panel {
            display: none;
            margin-top: 1rem;
            border-top: 2px solid #2980b9;
            padding-top: 0.9rem;
        }

        .edit-client-panel.open {
            display: block;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem 1rem;
        }

        .edit-full {
            grid-column: span 2;
        }

        .edit-client-panel label {
            font-family: var(--font-display);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gris);
            display: block;
            margin-bottom: 0.25rem;
        }

        .edit-client-panel input,
        .edit-client-panel select {
            width: 100%;
            padding: 0.45rem 0.7rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            transition: border-color 0.2s;
            background: #fff;
        }

        .edit-client-panel input:focus,
        .edit-client-panel select:focus {
            outline: none;
            border-color: #2980b9;
        }

        .edit-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 0.75rem;
        }

        .btn-save-client {
            background-color: #2980b9;
            border: 2px solid #2980b9;
            border-radius: 4px;
            padding: 0.45rem 1rem;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            cursor: pointer;
            color: #fff;
            transition: background-color 0.2s;
        }

        .btn-save-client:hover {
            background-color: #1f6390;
            border-color: #1f6390;
        }

        .btn-cancel-edit {
            background: none;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            padding: 0.45rem 1rem;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            cursor: pointer;
            color: var(--gris);
            transition: border-color 0.2s;
        }

        .btn-cancel-edit:hover {
            border-color: var(--gris);
        }

        .edit-success {
            font-size: 0.82rem;
            color: #27ae60;
            font-family: var(--font-display);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-right: auto;
            align-self: center;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 500;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-top: 4px solid var(--rouge);
            border-radius: 0 0 6px 6px;
            padding: 2rem 2.5rem;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .modal-box h3 {
            font-family: var(--font-display);
            font-size: 1.2rem;
            text-transform: uppercase;
            color: var(--noir);
            margin-bottom: 0.75rem;
        }

        .modal-box p {
            font-size: 0.95rem;
            color: var(--gris);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

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

        .btn-annuler:hover {
            border-color: var(--gris);
        }

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

        .btn-confirmer:hover {
            background-color: var(--rouge-dark);
            border-color: var(--rouge-dark);
        }

        .inactifs-wrap {
            margin-top: 2rem;
        }

        .btn-goto {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--gris);
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }

        .btn-goto:hover {
            color: var(--rouge);
        }

        .lignes-horaires-wrap {
            margin-top: 2rem;
        }

        .ligne-infos-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.6rem 1rem;
            align-items: end;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px dashed var(--gris-clair);
        }

        .ligne-infos-form label {
            font-family: var(--font-display);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gris);
            display: block;
            margin-bottom: 0.25rem;
        }

        .ligne-infos-form input {
            width: 100%;
            padding: 0.45rem 0.7rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            transition: border-color 0.2s;
        }

        .ligne-infos-form input:focus {
            outline: none;
            border-color: var(--rouge);
        }

        .etapes-titre {
            font-family: var(--font-display);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gris);
            margin-bottom: 0.6rem;
        }

        .etapes-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .etapes-table thead tr {
            background: var(--noir);
            color: #fff;
        }

        .etapes-table thead th {
            padding: 0.5rem 0.6rem;
            text-align: left;
            font-family: var(--font-display);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .etapes-table tbody tr:nth-child(even) {
            background: var(--gris-clair);
        }

        .etapes-table tbody td {
            padding: 0.4rem 0.6rem;
            color: var(--gris);
            vertical-align: middle;
        }

        .etapes-table input[type="time"],
        .etapes-table input[type="number"] {
            padding: 0.3rem 0.5rem;
            border: 2px solid var(--gris-clair);
            border-radius: 3px;
            font-size: 0.85rem;
            width: 100%;
            color: var(--noir);
            transition: border-color 0.2s;
        }

        .etapes-table input:focus {
            outline: none;
            border-color: var(--rouge);
        }

        .noeud-dernier td {
            opacity: 0.65;
        }

        .badge-premier {
            font-size: 0.7rem;
            background: var(--rouge);
            color: #fff;
            border-radius: 3px;
            padding: 0 4px;
            margin-left: 4px;
            vertical-align: middle;
            font-family: var(--font-display);
            text-transform: uppercase;
        }

        .badge-dernier {
            font-size: 0.7rem;
            background: var(--gris);
            color: #fff;
            border-radius: 3px;
            padding: 0 4px;
            margin-left: 4px;
            vertical-align: middle;
            font-family: var(--font-display);
            text-transform: uppercase;
        }

        .badge-type {
            font-size: 0.72rem;
            background: #eaf3de;
            color: #3b6d11;
            border-radius: 3px;
            padding: 1px 6px;
            margin-left: 4px;
            vertical-align: middle;
            font-family: var(--font-display);
            text-transform: uppercase;
            font-weight: 600;
        }

        .btn-save-noeud {
            background: none;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--gris);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-save-noeud:hover {
            border-color: #27ae60;
            color: #27ae60;
        }

        .btn-save-ligne {
            background-color: var(--rouge);
            border: 2px solid var(--rouge);
            border-radius: 4px;
            padding: 0.5rem 1.1rem;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            cursor: pointer;
            color: #fff;
            white-space: nowrap;
            transition: background-color 0.2s;
            align-self: end;
        }

        .btn-save-ligne:hover {
            background-color: var(--rouge-dark);
            border-color: var(--rouge-dark);
        }

        .ligne-detail-panel {
            margin-top: 1rem;
        }

        #back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
            width: 2.8rem;
            height: 2.8rem;
            background-color: var(--rouge);
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s, background-color 0.2s, transform 0.1s;
        }

        #back-to-top:hover {
            background-color: var(--rouge-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 1100px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .admin-nav .btn {
                font-size: 0.78rem;
                padding: 0.5rem 0.8rem;
            }

            .clients-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-clients {
                width: 100%;
            }

            .client-details {
                white-space: normal;
            }

            .btn-ic {
                width: 30px;
                height: 30px;
                font-size: 0.85rem;
            }

            .resa-ligne {
                grid-template-columns: auto 1fr;
            }

            .resa-date,
            .resa-prix {
                grid-column: 2;
            }

            .edit-grid {
                grid-template-columns: 1fr;
            }

            .edit-full {
                grid-column: span 1;
            }

            .stat-interactive {
                flex-direction: column;
                align-items: stretch;
            }

            .stat-interactive .si-group {
                min-width: unset;
            }

            .stat-interactive .btn {
                width: 100%;
                justify-content: center;
            }

            .stat-result-grid {
                grid-template-columns: 1fr;
            }

            .ligne-infos-form {
                grid-template-columns: 1fr;
            }

            .btn-save-ligne {
                width: 100%;
            }

            .etapes-table {
                font-size: 0.75rem;
            }

            .etapes-table thead th,
            .etapes-table tbody td {
                padding: 0.3rem 0.3rem;
            }

            .modal-box {
                padding: 1.5rem;
            }

            .stat-card {
                padding: 1rem 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .admin-nav {
                flex-direction: column;
            }

            .admin-nav .btn {
                width: 100%;
                justify-content: center;
            }

            .client-header {
                flex-wrap: wrap;
            }

            .client-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 0.5rem;
            }

            .resa-ligne {
                grid-template-columns: 1fr;
            }

            .resa-num,
            .resa-trajet,
            .resa-date,
            .resa-prix {
                grid-column: 1;
            }

            table {
                font-size: 0.75rem;
            }

            thead th,
            tbody td {
                padding: 0.35rem 0.4rem;
            }

            .stat-result {
                font-size: 1.6rem;
            }
        }
    </style>
</head>

<body>
    <?php include_once "nav.php"; ?>
    <main>
        <h1>Tableau de bord admin</h1>

        <div class="admin-nav">
            <button class="btn" onclick="scrollSec('section-stats')">Statistiques</button>
            <button class="btn" onclick="scrollSec('section-stats-interactives')">Stats avancées</button>
            <button class="btn" onclick="scrollSec('section-clients')">Clients</button>
            <button class="btn" onclick="scrollSec('section-inactifs')">Clients inactifs</button>
            <button class="btn" onclick="scrollSec('section-lignes')">Lignes &amp; Horaires</button>
        </div>

        <div class="admin-layout">

            <!-- Colonne gauche : graphiques -->
            <div class="admin-col" id="section-stats">
                <div class="stat-card">
                    <h3>Voyages par ligne</h3>
                    <canvas id="chartLignes"></canvas>
                </div>
                <div class="stat-card">
                    <h3>Meilleurs clients</h3>
                    <canvas id="chartClients"></canvas>
                </div>
                <div class="stat-card">
                    <h3>Réservations par mois</h3>
                    <canvas id="chartMois"></canvas>
                </div>
                <div class="stat-card">
                    <h3>Réservations par jour de la semaine</h3>
                    <canvas id="chartSemaine"></canvas>
                </div>
                <div class="stat-card">
                    <h3>Distance totale parcourue par client</h3>
                    <canvas id="chartDist"></canvas>
                </div>
                <div class="stat-card">
                    <h3>Réservations par département</h3>
                    <canvas id="chartDep"></canvas>
                </div>
            </div>

            <!-- Colonne droite : clients -->
            <div class="admin-col" id="section-clients">
                <div class="clients-header">
                    <h2>Clients (<?php echo count($clients); ?>)</h2>
                    <input type="search" class="search-clients" id="searchClients" placeholder="Rechercher un client…"
                        oninput="filtrerClients(this.value)">
                </div>

                <?php
                // Construire un index des types pour affichage rapide
                $types_index = [];
                foreach ($types_client as $tr) {
                    $types_index[$tr['TYP_NUM']] = $tr['TYP_NOM'];
                }

                foreach ($clients as $i => $client) {
                    $cnum = $client['CLI_NUM'];
                    $grade_client = $client['CLI_GRADE'] ?? 'client';
                    $typ_num_client = $client['TYP_NUM'] ?? null;
                    $typ_nom_client = isset($typ_num_client) ? ($types_index[$typ_num_client] ?? '—') : '—';
                    $extra = $i >= 10 ? 'client-extra' : '';
                    ?>
                    <div class="client-card <?php echo $extra; ?>" id="client-<?php echo $cnum; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM'] . ' ' . $client['CLI_COURRIEL'])); ?>">
                        <div class="client-header">
                            <div class="client-info">
                                <div class="client-nom">
                                    <?php echo htmlspecialchars($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']); ?>
                                    <span class="badge-type"><?php echo htmlspecialchars($typ_nom_client); ?></span>
                                </div>
                                <div class="client-details">
                                    <?php echo htmlspecialchars($client['CLI_COURRIEL']); ?> &nbsp;·&nbsp;
                                    <?php echo $client['CLI_NB_POINTS_EC']; ?> pts
                                    &nbsp;·&nbsp; <em><?php echo htmlspecialchars($grade_client); ?></em>
                                </div>
                            </div>
                            <div class="client-actions">
                                <button class="btn-ic btn-toggle" onclick="toggleResas(<?php echo $cnum; ?>)"
                                    title="Réservations">▼</button>
                                <button class="btn-ic btn-edit" onclick="toggleEditClient(<?php echo $cnum; ?>)"
                                    title="Modifier">✏️</button>
                                <button class="btn-ic btn-suppr"
                                    onclick="demanderSuppr(<?php echo $cnum; ?>, '<?php echo addslashes($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']); ?>')"
                                    title="Supprimer">🗑</button>
                            </div>
                        </div>

                        <div class="resas-panel" id="resas-<?php echo $cnum; ?>">
                            <?php if (empty($reservations[$cnum])) { ?>
                                <p class="no-resa">Aucune réservation.</p>
                            <?php } else {
                                foreach ($reservations[$cnum] as $resa) { ?>
                                    <div class="resa-ligne">
                                        <span class="resa-num">#<?php echo $resa['RES_NUM']; ?></span>
                                        <span class="resa-trajet"><?php echo htmlspecialchars($resa['DEPART']); ?> →
                                            <?php echo htmlspecialchars($resa['ARRIVEE']); ?></span>
                                        <span class="resa-date"><?php echo $resa['RES_DATE']; ?></span>
                                        <span class="resa-prix"><?php echo $resa['RES_PRIX_TOT']; ?> €</span>
                                    </div>
                                <?php }
                            } ?>
                        </div>

                        <div class="edit-client-panel <?php echo ($msg_modif === "client_$cnum") ? 'open' : ''; ?>"
                            id="edit-client-<?php echo $cnum; ?>">
                            <form method="post" action="#">
                                <input type="hidden" name="action" value="modif_client">
                                <input type="hidden" name="cli_num" value="<?php echo $cnum; ?>">
                                <div class="edit-grid">
                                    <div>
                                        <label>Prénom</label>
                                        <input type="text" name="cli_prenom"
                                            value="<?php echo htmlspecialchars($client['CLI_PRENOM']); ?>" required>
                                    </div>
                                    <div>
                                        <label>Nom</label>
                                        <input type="text" name="cli_nom"
                                            value="<?php echo htmlspecialchars($client['CLI_NOM']); ?>" required>
                                    </div>
                                    <div class="edit-full">
                                        <label>Courriel</label>
                                        <input type="email" name="cli_courriel"
                                            value="<?php echo htmlspecialchars($client['CLI_COURRIEL']); ?>" required>
                                    </div>
                                    <div>
                                        <label>Points</label>
                                        <input type="number" name="cli_nb_points_ec"
                                            value="<?php echo $client['CLI_NB_POINTS_EC']; ?>" min="0" max="9999">
                                    </div>
                                    <div>
                                        <label>Rôle</label>
                                        <select name="cli_grade">
                                            <option value="client" <?php echo ($grade_client === 'client') ? 'selected' : ''; ?>>Client</option>
                                            <option value="administrateur" <?php echo ($grade_client === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Type de client</label>
                                        <select name="typ_num">
                                            <?php foreach ($types_client as $tr) {
                                                $sel = ($typ_num_client == $tr['TYP_NUM']) ? 'selected' : '';
                                                echo "<option value=\"" . (int)$tr['TYP_NUM'] . "\" $sel>"
                                                   . htmlspecialchars($tr['TYP_NOM']) . "</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="edit-actions">
                                    <?php if ($msg_modif === "client_$cnum") { ?>
                                        <span class="edit-success">✔ Sauvegardé</span>
                                    <?php } ?>
                                    <button type="button" class="btn-cancel-edit"
                                        onclick="toggleEditClient(<?php echo $cnum; ?>)">Annuler</button>
                                    <button type="submit" class="btn-save-client">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } ?>

                <?php if (count($clients) > 10) { ?>
                    <button class="btn" id="btn-voir-plus" onclick="voirPlusClients()" style="width:100%;margin-top:0.5rem">
                        Voir plus (<?php echo count($clients) - 10; ?> clients restants)
                    </button>
                <?php } ?>
            </div>

        </div>

        <!-- Stats avancées pleine largeur -->
        <div id="section-stats-interactives" style="margin-top:2rem;">

            <div class="stat-card">
                <h3>Réservations entre deux villes</h3>
                <form method="get" action="#section-stats-interactives">
                    <div class="stat-interactive">
                        <div class="si-group">
                            <label>Ville de départ</label>
                            <select name="v1">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($villes_list as $v) {
                                    $sel = ($stat_v1 === $v['COM_NOM']) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($v['COM_NOM']) . "\" $sel>" . htmlspecialchars($v['COM_NOM']) . "</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="si-group">
                            <label>Ville d'arrivée</label>
                            <select name="v2">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($villes_list as $v) {
                                    $sel = ($stat_v2 === $v['COM_NOM']) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($v['COM_NOM']) . "\" $sel>" . htmlspecialchars($v['COM_NOM']) . "</option>";
                                } ?>
                            </select>
                        </div>
                        <?php if ($stat_lv !== '')
                            echo "<input type='hidden' name='lv'  value='" . htmlspecialchars($stat_lv) . "'>"; ?>
                        <?php if ($stat_dav !== '')
                            echo "<input type='hidden' name='dav' value='" . htmlspecialchars($stat_dav) . "'>"; ?>
                        <button type="submit" class="btn">Calculer</button>
                    </div>
                </form>
                <?php if ($stat_resa_villes !== null) { ?>
                    <div class="stat-result"><?php echo count($stat_resa_villes); ?></div>
                    <div class="stat-result-label">réservation(s) de <?php echo htmlspecialchars($stat_v1); ?> vers
                        <?php echo htmlspecialchars($stat_v2); ?></div>
                    <?php if (count($stat_resa_villes) > 0) { ?>
                        <table style="margin-top:1rem">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Prix</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stat_resa_villes as $r) { ?>
                                    <tr>
                                        <td>#<?php echo $r['RES_NUM']; ?></td>
                                        <td><?php echo htmlspecialchars($r['CLIENT']); ?></td>
                                        <td><?php echo $r['RES_DATE']; ?></td>
                                        <td><?php echo $r['RES_PRIX_TOT']; ?> €</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="stat-card">
                <h3>Lignes passant par une ville</h3>
                <form method="get" action="#section-stats-interactives">
                    <div class="stat-interactive">
                        <div class="si-group">
                            <label>Ville</label>
                            <select name="lv">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($villes_list as $v) {
                                    $sel = ($stat_lv === $v['COM_NOM']) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($v['COM_NOM']) . "\" $sel>" . htmlspecialchars($v['COM_NOM']) . "</option>";
                                } ?>
                            </select>
                        </div>
                        <?php if ($stat_v1 !== '')
                            echo "<input type='hidden' name='v1'  value='" . htmlspecialchars($stat_v1) . "'>"; ?>
                        <?php if ($stat_v2 !== '')
                            echo "<input type='hidden' name='v2'  value='" . htmlspecialchars($stat_v2) . "'>"; ?>
                        <?php if ($stat_dav !== '')
                            echo "<input type='hidden' name='dav' value='" . htmlspecialchars($stat_dav) . "'>"; ?>
                        <button type="submit" class="btn">Calculer</button>
                    </div>
                </form>
                <?php if ($stat_lignes_ville !== null) { ?>
                    <div class="stat-result"><?php echo count($stat_lignes_ville); ?></div>
                    <div class="stat-result-label">ligne(s) passant par <?php echo htmlspecialchars($stat_lv); ?></div>
                    <?php if (count($stat_lignes_ville) > 0) { ?>
                        <table style="margin-top:1rem">
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Départ</th>
                                    <th>Terminus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stat_lignes_ville as $r) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['LIG_NUM']); ?></td>
                                        <td><?php echo htmlspecialchars($r['NOM_DEBU']); ?></td>
                                        <td><?php echo htmlspecialchars($r['NOM_TERM']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="stat-card">
                <h3>Voyageurs au départ / à l'arrivée d'une ville</h3>
                <form method="get" action="#section-stats-interactives">
                    <div class="stat-interactive">
                        <div class="si-group">
                            <label>Ville</label>
                            <select name="dav">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($villes_list as $v) {
                                    $sel = ($stat_dav === $v['COM_NOM']) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($v['COM_NOM']) . "\" $sel>" . htmlspecialchars($v['COM_NOM']) . "</option>";
                                } ?>
                            </select>
                        </div>
                        <?php if ($stat_v1 !== '')
                            echo "<input type='hidden' name='v1'  value='" . htmlspecialchars($stat_v1) . "'>"; ?>
                        <?php if ($stat_v2 !== '')
                            echo "<input type='hidden' name='v2'  value='" . htmlspecialchars($stat_v2) . "'>"; ?>
                        <?php if ($stat_lv !== '')
                            echo "<input type='hidden' name='lv'  value='" . htmlspecialchars($stat_lv) . "'>"; ?>
                        <button type="submit" class="btn">Calculer</button>
                    </div>
                </form>
                <?php if ($stat_dep_arr !== null) {
                    $nb_dep = count(array_filter($stat_dep_arr, function ($r) {
                        return $r['SENS'] === 'Départ';
                    }));
                    $nb_arr = count(array_filter($stat_dep_arr, function ($r) {
                        return $r['SENS'] === 'Arrivée';
                    }));
                    ?>
                    <div class="stat-result-grid">
                        <div class="stat-result-box">
                            <div class="val"><?php echo $nb_dep; ?></div>
                            <div class="lbl">au départ</div>
                        </div>
                        <div class="stat-result-box">
                            <div class="val"><?php echo $nb_arr; ?></div>
                            <div class="lbl">à l'arrivée</div>
                        </div>
                    </div>
                    <?php if (count($stat_dep_arr) > 0) { ?>
                        <table style="margin-top:1rem">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Sens</th>
                                    <th>Date</th>
                                    <th>Prix</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stat_dep_arr as $r) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['CLIENT']); ?></td>
                                        <td><?php echo $r['SENS']; ?></td>
                                        <td><?php echo $r['RES_DATE']; ?></td>
                                        <td><?php echo $r['RES_PRIX_TOT']; ?> €</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                    <div class="stat-result-label" style="margin-top:0.5rem">pour <?php echo htmlspecialchars($stat_dav); ?>
                    </div>
                <?php } else if ($stat_dav !== '') { ?>
                    <p class="no-resa" style="margin-top:0.5rem">Aucune donnée pour cette ville.</p>
                <?php } ?>
            </div>

        </div>

        <!-- Clients inactifs -->
        <div class="inactifs-wrap" id="section-inactifs">
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
                            <?php foreach ($inactifs as $i => $c) { ?>
                                <tr class="<?php echo $i >= 10 ? 'inactif-extra' : ''; ?>">
                                    <td><?php echo htmlspecialchars($c['CLI_PRENOM']); ?></td>
                                    <td><?php echo htmlspecialchars($c['CLI_NOM']); ?></td>
                                    <td><?php echo $c['CLI_DATE_CONNEC']; ?></td>
                                    <td><?php
                                        $j = (int) $c['JOURS'];
                                        if ($j > 730)
                                            echo "<strong style='color:var(--rouge)'>$j j (à supprimer)</strong>";
                                        else
                                            echo "$j j";
                                    ?></td>
                                    <td>
                                        <button class="btn-goto"
                                            onclick="demanderSuppr(<?php echo $c['CLI_NUM']; ?>, '<?php echo addslashes($c['CLI_PRENOM'] . ' ' . $c['CLI_NOM']); ?>')"
                                            title="Supprimer">🗑</button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <?php if (count($inactifs) > 10) { ?>
                        <button class="btn" id="btn-voir-plus-inactifs" onclick="voirPlusInactifs()"
                            style="width:100%;margin-top:0.75rem">
                            Voir plus (<?php echo count($inactifs) - 10; ?> clients restants)
                        </button>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <!-- Lignes & Horaires -->
        <div class="lignes-horaires-wrap" id="section-lignes">
            <div class="stat-card">
                <h3>Lignes &amp; Horaires (<?php echo count($all_lignes); ?> lignes)</h3>

                <div class="stat-interactive" style="margin-bottom:1.5rem;">
                    <div class="si-group">
                        <label>Choisir une ligne</label>
                        <select id="select-ligne" onchange="afficherLigne(this.value)">
                            <option value="">-- Sélectionner une ligne --</option>
                            <?php foreach ($all_lignes as $lig) {
                                $lnum = trim($lig['LIG_NUM']);
                                $selected = ($msg_ligne === $lnum) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($lnum) . "\" $selected>"
                                    . "Ligne " . htmlspecialchars($lnum)
                                    . " – " . htmlspecialchars($lig['NOM_DEBU'])
                                    . " → " . htmlspecialchars($lig['NOM_TERM'])
                                    . "</option>";
                            } ?>
                        </select>
                    </div>
                </div>

                <div id="ligne-detail-wrap">
                    <?php foreach ($all_lignes as $lig) {
                        $lnum = trim($lig['LIG_NUM']);
                        $noeuds = $noeuds_par_ligne[$lnum] ?? [];
                        $nb_noeuds = count($noeuds);
                        $isVisible = ($msg_ligne === $lnum);
                        ?>
                        <div class="ligne-detail-panel" id="detail-<?php echo htmlspecialchars($lnum); ?>"
                             style="<?php echo $isVisible ? '' : 'display:none'; ?>">

                            <!-- Modification des codes INSEE de la ligne -->
                            <form method="post" action="">
                                <input type="hidden" name="action" value="modif_ligne">
                                <input type="hidden" name="lig_num" value="<?php echo htmlspecialchars($lnum); ?>">
                                <div class="ligne-infos-form">
                                    <div>
                                        <label>Code INSEE départ</label>
                                        <input type="text" name="com_code_insee_debu"
                                            value="<?php echo htmlspecialchars($lig['COM_CODE_INSEE_DEBU']); ?>"
                                            maxlength="10" required>
                                    </div>
                                    <div>
                                        <label>Code INSEE terminus</label>
                                        <input type="text" name="com_code_insee_term"
                                            value="<?php echo htmlspecialchars($lig['COM_CODE_INSEE_TERM']); ?>"
                                            maxlength="10" required>
                                    </div>
                                    <div><button type="submit" class="btn-save-ligne">Enregistrer</button></div>
                                </div>
                            </form>

                            <!-- Tableau des noeuds -->
                            <?php if (empty($noeuds)) { ?>
                                <p class="no-resa">Aucun arrêt enregistré pour cette ligne.</p>
                            <?php } else { ?>
                                <p class="etapes-titre">Arrêts &amp; horaires</p>
                                <table class="etapes-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Arrêt</th>
                                            <th>Heure de passage</th>
                                            <th>Dist. suivant (km)</th>
                                            <th>Durée suivant (min)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($noeuds as $idx => $noeud) {
                                            $is_premier = ($idx === 0);
                                            $is_dernier = ($idx === $nb_noeuds - 1);
                                            $arret = $noeud['COM_CODE_INSEE_ARRET'];
                                            $suivant = isset($noeud['COM_CODE_INSEE_SUIVANT']) ? trim($noeud['COM_CODE_INSEE_SUIVANT']) : '';
                                            $form_id = 'fnoeud-' . htmlspecialchars($lnum) . '-' . $idx;
                                            $heure_raw = $noeud['NOE_HEURE_PASSAGE'] ?? '';
                                            $heure_time = substr($heure_raw, 0, 5);
                                            ?>
                                            <tr class="<?php echo $is_dernier ? 'noeud-dernier' : ''; ?>">
                                                <td style="font-weight:600;color:var(--rouge);font-size:0.8rem;">
                                                    <?php echo $idx + 1; ?>
                                                    <?php if ($is_premier) echo '<span class="badge-premier">départ</span>'; ?>
                                                    <?php if ($is_dernier) echo '<span class="badge-dernier">terminus</span>'; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($noeud['NOM_ARRET']); ?></td>
                                                <td>
                                                    <input form="<?php echo $form_id; ?>" type="time" name="noe_heure_passage"
                                                        value="<?php echo htmlspecialchars($heure_time); ?>" required>
                                                </td>
                                                <td>
                                                    <?php if (!$is_dernier) { ?>
                                                        <input form="<?php echo $form_id; ?>" type="number" name="noe_distance_prochain"
                                                            value="<?php echo str_replace(',', '.', $noeud['NOE_DISTANCE_PROCHAIN'] ?? ''); ?>"
                                                            step="0.1" min="0">
                                                    <?php } else { ?>
                                                        <span style="color:var(--gris);font-style:italic;font-size:0.8rem;">—</span>
                                                        <input form="<?php echo $form_id; ?>" type="hidden" name="noe_distance_prochain" value="0">
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if (!$is_dernier) { ?>
                                                        <input form="<?php echo $form_id; ?>" type="number" name="noe_duree_prochain"
                                                            value="<?php echo str_replace(',', '.', $noeud['NOE_DUREE_PROCHAIN'] ?? ''); ?>"
                                                            step="1" min="0">
                                                    <?php } else { ?>
                                                        <span style="color:var(--gris);font-style:italic;font-size:0.8rem;">—</span>
                                                        <input form="<?php echo $form_id; ?>" type="hidden" name="noe_duree_prochain" value="0">
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <button form="<?php echo $form_id; ?>" type="submit" class="btn-save-noeud" title="Enregistrer">✔</button>
                                                </td>
                                            </tr>
                                            <form id="<?php echo $form_id; ?>" method="post" action="">
                                                <input type="hidden" name="action" value="modif_noeud">
                                                <input type="hidden" name="lig_num" value="<?php echo htmlspecialchars($lnum); ?>">
                                                <input type="hidden" name="com_code_insee_arret" value="<?php echo htmlspecialchars($arret); ?>">
                                                <input type="hidden" name="com_code_insee_suivant" value="<?php echo htmlspecialchars($suivant); ?>">
                                                <input type="hidden" name="noe_heure_passage_old" value="<?php echo htmlspecialchars($heure_raw); ?>">
                                            </form>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>

    </main>

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

    <button id="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Retour en haut">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round">
            <polyline points="18 15 12 9 6 15" />
        </svg>
    </button>

    <?php include_once "footer.php"; ?>

    <script>
        const rouge = '#C0392B';
        const rougeClair = 'rgba(192, 57, 43, 0.7)';
        const gris = '#4a4a4a';
        const bleu = 'rgba(41, 128, 185, 0.7)';
        const vert = 'rgba(39, 174, 96, 0.7)';

        new Chart(document.getElementById('chartLignes'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($lignes_labels); ?>, datasets: [{ data: <?php echo json_encode($lignes_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris, font: { size: 10 } } } } }
        });
        new Chart(document.getElementById('chartClients'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($top_labels); ?>, datasets: [{ data: <?php echo json_encode($top_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
            options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 10 } } } } }
        });
        new Chart(document.getElementById('chartMois'), {
            type: 'line',
            data: { labels: <?php echo json_encode($mois_labels); ?>, datasets: [{ data: <?php echo json_encode($mois_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, pointBackgroundColor: rouge, fill: true, tension: 0.3 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
        });
        new Chart(document.getElementById('chartSemaine'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($semaine_labels); ?>, datasets: [{ data: <?php echo json_encode($semaine_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
        });
        new Chart(document.getElementById('chartDist'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($dist_labels); ?>, datasets: [{ data: <?php echo json_encode($dist_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
            options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.raw + ' km' } } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 10 } } } } }
        });
        new Chart(document.getElementById('chartDep'), {
            type: 'doughnut',
            data: { labels: <?php echo json_encode($dep_labels); ?>, datasets: [{ data: <?php echo json_encode($dep_data); ?>, backgroundColor: ['rgba(192,57,43,0.8)', 'rgba(41,128,185,0.8)', 'rgba(39,174,96,0.8)', 'rgba(243,156,18,0.8)', 'rgba(142,68,173,0.8)', 'rgba(26,188,156,0.8)', 'rgba(231,76,60,0.8)', 'rgba(52,152,219,0.8)', 'rgba(46,204,113,0.8)'], borderWidth: 2 }] },
            options: { responsive: true, plugins: { legend: { position: 'right', labels: { color: gris, font: { size: 11 } } } } }
        });

        function scrollSec(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const nav = document.querySelector('nav');
            const offset = (nav ? nav.offsetHeight : 0) + 16;
            window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
        }

        function filtrerClients(val) {
            const q = val.toLowerCase().trim();
            const btn = document.getElementById('btn-voir-plus');
            document.querySelectorAll('.client-card').forEach(card => {
                const search = card.dataset.search || '';
                if (q !== '' && search.includes(q)) {
                    card.classList.remove('hidden');
                    card.classList.remove('client-extra');
                } else if (q !== '' && !search.includes(q)) {
                    card.classList.add('hidden');
                    card.classList.remove('client-extra');
                } else {
                    card.classList.remove('hidden');
                }
            });
            if (btn) btn.style.display = q !== '' ? 'none' : '';
        }

        function voirPlusClients() {
            document.querySelectorAll('.client-extra').forEach(c => c.classList.remove('client-extra'));
            const btn = document.getElementById('btn-voir-plus');
            if (btn) btn.style.display = 'none';
        }

        function voirPlusInactifs() {
            document.querySelectorAll('.inactif-extra').forEach(c => c.classList.remove('inactif-extra'));
            const btn = document.getElementById('btn-voir-plus-inactifs');
            if (btn) btn.style.display = 'none';
        }

        function toggleResas(num) {
            document.getElementById('resas-' + num).classList.toggle('open');
        }

        function toggleEditClient(num) {
            document.getElementById('edit-client-' + num).classList.toggle('open');
        }

        function demanderSuppr(num, nom) {
            document.getElementById('modal-texte').innerText = 'Voulez-vous vraiment supprimer le compte de ' + nom + ' ? Cette action est irréversible.';
            document.getElementById('modal-lien').href = 'supprimer_client.php?id=' + num;
            document.getElementById('modal').classList.add('open');
        }

        function fermerModal() {
            document.getElementById('modal').classList.remove('open');
        }

        function afficherLigne(lnum) {
            document.querySelectorAll('.ligne-detail-panel').forEach(el => el.style.display = 'none');
            if (lnum !== '') {
                const panel = document.getElementById('detail-' + lnum);
                if (panel) panel.style.display = 'block';
            }
        }

        (function () {
            const sel = document.getElementById('select-ligne');
            if (sel && sel.value !== '') afficherLigne(sel.value);
        })();

        const btnTop = document.getElementById('back-to-top');
        window.addEventListener('scroll', () => {
            btnTop.style.opacity = window.scrollY > 300 ? '1' : '0';
            btnTop.style.pointerEvents = window.scrollY > 300 ? 'auto' : 'none';
        });
    </script>
</body>

</html>