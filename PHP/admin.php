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
    $id     = (int)trim($_POST['cli_num']);
    $prenom = trim($_POST['cli_prenom']);
    $nom    = trim($_POST['cli_nom']);
    $mail   = trim($_POST['cli_courriel']);
    $points = (int)trim($_POST['cli_nb_points_ec']);
    $grade  = trim($_POST['cli_grade']);
    // Sécurité : seuls ces grades sont autorisés
    $grades_ok = ['client', 'administrateur'];
    if (!in_array($grade, $grades_ok)) $grade = 'client';
    $sql_u = "UPDATE vik_client SET cli_prenom = ?, cli_nom = ?, cli_courriel = ?, cli_nb_points_ec = ?, cli_grade = ? WHERE cli_num = ?";
    $cur   = preparerRequetePDO($conn, $sql_u);
    majDonneesPrepareesTabPDO($cur, [$prenom, $nom, $mail, $points, $grade, $id]);
    $msg_modif = "client_$id";
}

// ── Traitement modification ligne ────────────────────────────────────────────
$msg_ligne = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modif_ligne') {
    $lig        = trim($_POST['lig_num']);
    $insee_deb  = trim($_POST['com_code_insee_debu']);
    $insee_term = trim($_POST['com_code_insee_term']);
    $sql_ul = "UPDATE vik_ligne SET com_code_insee_debu = ?, com_code_insee_term = ? WHERE lig_num = ?";
    $cur    = preparerRequetePDO($conn, $sql_ul);
    majDonneesPrepareesTabPDO($cur, [$insee_deb, $insee_term, $lig]);
    $msg_ligne = $lig;
}

// ── Traitement modification étape ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modif_etape') {
    $lig   = trim($_POST['lig_num']);
    $cli   = (int)trim($_POST['cli_num']);
    $res   = (int)trim($_POST['res_num']);
    $heure = trim($_POST['eta_heure']);
    $dist  = (float)str_replace(',', '.', $_POST['eta_distance']);
    $sql_ue = "UPDATE vik_etape
               SET eta_heure    = TO_DATE(TO_CHAR(eta_heure,'DD/MM/YY') || ' ' || ?, 'DD/MM/YY HH24:MI'),
                   eta_distance = ?
               WHERE lig_num = ? AND cli_num = ? AND res_num = ?";
    $cur = preparerRequetePDO($conn, $sql_ue);
    majDonneesPrepareesTabPDO($cur, [$heure, $dist, $lig, $cli, $res]);
}

// ── Stats générales ───────────────────────────────────────────────────────────
$sql_lignes = "SELECT lig_num, count(*) AS NB FROM vik_etape GROUP BY lig_num ORDER BY lig_num ASC";
$lignes_pop = LireDonneesPDO3($conn, $sql_lignes, $t);

$sql_heures = "SELECT * FROM (
    SELECT to_char(eta_heure, 'HH24') || 'h' AS HEURE, count(*) AS NB
    FROM vik_etape GROUP BY to_char(eta_heure, 'HH24') ORDER BY count(*) DESC
) WHERE rownum <= 10";
$heures_pop = LireDonneesPDO3($conn, $sql_heures, $t);

$sql_lignes_heures = "SELECT * FROM (
    SELECT lig_num AS LIGNE, to_char(eta_heure, 'HH24') || 'h' AS HEURE, count(*) AS NB
    FROM vik_etape GROUP BY lig_num, to_char(eta_heure, 'HH24') ORDER BY count(*) DESC
) WHERE rownum <= 10";
$lignes_heures = LireDonneesPDO3($conn, $sql_lignes_heures, $t);

$sql_clients_top = "SELECT * FROM (
    SELECT c.cli_prenom || ' ' || c.cli_nom AS CLIENT, count(*) AS NB
    FROM vik_reservation r JOIN vik_client c ON r.cli_num = c.cli_num
    WHERE r.cli_num != 0 GROUP BY c.cli_prenom || ' ' || c.cli_nom ORDER BY count(*) DESC
) WHERE rownum <= 10";
$top_clients = LireDonneesPDO3($conn, $sql_clients_top, $t);

$sql_mois = "SELECT to_char(res_date, 'MM/YYYY') AS MOIS, count(*) AS NB
    FROM vik_reservation GROUP BY to_char(res_date, 'MM/YYYY')
    ORDER BY to_char(res_date, 'MM/YYYY') ASC";
$resas_mois = LireDonneesPDO3($conn, $sql_mois, $t);

// ── Stats interactives : listes de villes disponibles ────────────────────────
$sql_villes = "SELECT DISTINCT com_nom FROM vik_commune ORDER BY com_nom ASC";
$villes_list = LireDonneesPDO3($conn, $sql_villes, $t);

// Stat 1 : réservations entre 2 villes
$stat_resa_villes = null;
$stat_v1 = isset($_GET['v1']) ? trim($_GET['v1']) : '';
$stat_v2 = isset($_GET['v2']) ? trim($_GET['v2']) : '';
if ($stat_v1 !== '' && $stat_v2 !== '') {
    $sql_sv = "SELECT COUNT(*) AS NB
        FROM VIK_ETAPE e
        JOIN VIK_COMMUNE c_dep
            ON c_dep.COM_CODE_INSEE = e.COM_CODE_INSEE_DEPART
        JOIN VIK_COMMUNE c_arr
            ON c_arr.COM_CODE_INSEE = e.COM_CODE_INSEE_ARRIVEE
        WHERE c_dep.COM_NOM = ?
        AND c_arr.COM_NOM = ?";

    $cur_sv = preparerRequetePDO($conn, $sql_sv);
    $cur_sv->execute([$stat_v1, $stat_v2]);

    $r = $cur_sv->fetch(PDO::FETCH_ASSOC);

    $stat_resa_villes = $r ? (int)$r['NB'] : 0;
}

// Stat 2 : lignes passant par une ville
$stat_lignes_ville = null;
$stat_lv = isset($_GET['lv']) ? trim($_GET['lv']) : '';
if ($stat_lv !== '') {
    $sql_slv = "SELECT COUNT(DISTINCT n.LIG_NUM) AS NB
        FROM VIK_NOEUD n
        JOIN VIK_COMMUNE c
            ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
        WHERE c.COM_NOM = ?";
    $cur_slv = preparerRequetePDO($conn, $sql_slv);
    $cur_slv->execute([$stat_lv]);
    $r = $cur_slv->fetch(PDO::FETCH_ASSOC);
    $stat_lignes_ville = $r ? (int)$r['NB'] : 0;
}

// Stat 3 : départs / arrivées dans une ville
$stat_dep_arr = null;
$stat_dav = isset($_GET['dav']) ? trim($_GET['dav']) : '';
if ($stat_dav !== '') {
    $sql_sdav = "SELECT
            COUNT(DISTINCT CASE
                WHEN e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE
                THEN e.CLI_NUM
            END) AS NB_DEPARTS,

            COUNT(DISTINCT CASE
                WHEN e.COM_CODE_INSEE_ARRIVEE = c.COM_CODE_INSEE
                THEN e.CLI_NUM
            END) AS NB_ARRIVEES

        FROM VIK_COMMUNE c
        LEFT JOIN VIK_ETAPE e
            ON e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE
            OR e.COM_CODE_INSEE_ARRIVEE = c.COM_CODE_INSEE

        WHERE c.COM_NOM = ?";
    $cur_sdav = preparerRequetePDO($conn, $sql_sdav);
    $cur_sdav->execute([$stat_dav]);
    $stat_dep_arr = $cur_sdav->fetch(PDO::FETCH_ASSOC);
}

// ── Clients inactifs ──────────────────────────────────────────────────────────
$sql_inactifs = "SELECT cli_num, cli_prenom, cli_nom, cli_date_connec,
    ROUND(SYSDATE - cli_date_connec) AS JOURS
    FROM vik_client WHERE SYSDATE - cli_date_connec > 365 AND cli_grade != 'administrateur'
    ORDER BY cli_date_connec ASC";
$inactifs = LireDonneesPDO3($conn, $sql_inactifs, $t);

// ── Liste clients (avec grade pour l'édition) ─────────────────────────────────
$sql_clients = "SELECT cli_num, cli_prenom, cli_nom, cli_courriel, cli_nb_points_ec, cli_grade
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

// ── Lignes & étapes ───────────────────────────────────────────────────────────
$sql_all_lignes = "SELECT l.lig_num, l.com_code_insee_debu, l.com_code_insee_term,
    cd.com_nom AS NOM_DEBU, ct.com_nom AS NOM_TERM
    FROM vik_ligne l
    JOIN vik_commune cd ON l.com_code_insee_debu  = cd.com_code_insee
    JOIN vik_commune ct ON l.com_code_insee_term = ct.com_code_insee
    ORDER BY l.lig_num ASC";
$all_lignes = LireDonneesPDO3($conn, $sql_all_lignes, $t);

$etapes_par_ligne = [];
foreach ($all_lignes as $lig) {
    $lnum = trim($lig['LIG_NUM']);
    $sql_etapes = "SELECT e.cli_num, e.res_num, e.com_code_insee_depart, e.com_code_insee_arrivee,
        to_char(e.eta_heure, 'HH24:MI') AS ETA_HEURE_HM, e.eta_distance,
        cd.com_nom AS NOM_DEPART, ca.com_nom AS NOM_ARRIVEE
        FROM vik_etape e
        JOIN vik_commune cd ON e.com_code_insee_depart  = cd.com_code_insee
        JOIN vik_commune ca ON e.com_code_insee_arrivee = ca.com_code_insee
        WHERE e.lig_num = '" . addslashes($lnum) . "'
        ORDER BY e.eta_heure ASC";
    $etapes_par_ligne[$lnum] = LireDonneesPDO3($conn, $sql_etapes, $t);
}

// ── JSON Chart.js ─────────────────────────────────────────────────────────────
$lignes_labels = []; $lignes_data = [];
foreach ($lignes_pop as $row) { $lignes_labels[] = $row['LIG_NUM']; $lignes_data[] = (int)$row['NB']; }
$heures_labels = []; $heures_data = [];
foreach ($heures_pop as $row) { $heures_labels[] = $row['HEURE']; $heures_data[] = (int)$row['NB']; }
$lh_labels = []; $lh_data = [];
foreach ($lignes_heures as $row) { $lh_labels[] = $row['LIGNE'] . ' ' . $row['HEURE']; $lh_data[] = (int)$row['NB']; }
$top_labels = []; $top_data = [];
foreach ($top_clients as $row) { $top_labels[] = $row['CLIENT']; $top_data[] = (int)$row['NB']; }
$mois_labels = []; $mois_data = [];
foreach ($resas_mois as $row) { $mois_labels[] = $row['MOIS']; $mois_data[] = (int)$row['NB']; }
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
        /* ── Navigation rapide – même style que .btn du site ── */
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

        /* ── Layout ── */
        .admin-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start; }
        .admin-col { min-width: 0; }
        @media (max-width: 1100px) { .admin-layout { grid-template-columns: 1fr; } }

        /* ── Stat cards ── */
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
        .stat-card canvas { max-height: 220px; max-width: 100%; }

        /* ── Stats interactives ── */
        .stat-interactive {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .stat-interactive .si-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 140px; }
        .stat-interactive label { font-family: var(--font-display); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--gris); }
        .stat-interactive select {
            padding: 0.5rem 0.7rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            background: #fff;
            transition: border-color 0.2s;
        }
        .stat-interactive select:focus { outline: none; border-color: var(--rouge); }
        .stat-interactive .btn { font-size: 0.85rem; padding: 0.5rem 1rem; border: none; cursor: pointer; align-self: flex-end; }
        .stat-result {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 700;
            color: var(--rouge);
            text-align: center;
            padding: 0.5rem 0;
        }
        .stat-result-label { font-size: 0.82rem; color: var(--gris); text-align: center; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-result-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem; }
        .stat-result-box { background: var(--gris-clair); border-radius: 4px; padding: 0.75rem; text-align: center; }
        .stat-result-box .val { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; color: var(--rouge); }
        .stat-result-box .lbl { font-size: 0.78rem; color: var(--gris); text-transform: uppercase; letter-spacing: 0.04em; }

        /* ── Tables ── */
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead tr { background-color: var(--noir); color: var(--blanc); font-family: var(--font-display); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; }
        thead th { padding: 0.6rem 0.8rem; text-align: left; }
        tbody tr:nth-child(even) { background-color: var(--gris-clair); }
        tbody td { padding: 0.5rem 0.8rem; color: var(--gris); }

        /* ── Barre de recherche clients ── */
        .clients-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .clients-header h2 { margin-bottom: 0; }
        .search-clients {
            flex: 1;
            min-width: 180px;
            padding: 0.5rem 0.9rem;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--noir);
            font-family: var(--font-body);
            transition: border-color 0.2s;
        }
        .search-clients:focus { outline: none; border-color: var(--rouge); }
        .client-card.hidden { display: none; }

        /* ── Client cards ── */
        .client-card {
            background: #fff;
            border-left: 4px solid var(--rouge);
            border-radius: 0 6px 6px 0;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .client-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .client-info { flex: 1; min-width: 0; }
        .client-nom { font-family: var(--font-display); font-weight: 600; font-size: 1rem; color: var(--noir); text-transform: uppercase; }
        .client-details { font-size: 0.85rem; color: var(--gris); margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .client-actions { display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0; }

        .btn-ic {
            background: none;
            border: 2px solid var(--gris-clair);
            border-radius: 4px;
            width: 34px; height: 34px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--gris);
            transition: border-color 0.2s, color 0.2s, background-color 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-ic.btn-toggle:hover      { border-color: var(--rouge); color: var(--rouge); }
        .btn-ic.btn-edit:hover        { border-color: #2980b9; color: #2980b9; }
        .btn-ic.btn-suppr:hover       { background-color: var(--rouge); border-color: var(--rouge); color: #fff; }

        /* ── Réservations panel ── */
        .resas-panel { display: none; margin-top: 1rem; border-top: 1px solid var(--gris-clair); padding-top: 0.75rem; }
        .resas-panel.open { display: block; }
        .resa-ligne { display: grid; grid-template-columns: auto 1fr auto auto; gap: 0.5rem 1rem; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--gris-clair); font-size: 0.88rem; }
        .resa-ligne:last-child { border-bottom: none; }
        .resa-num    { font-family: var(--font-display); font-weight: 600; color: var(--rouge); font-size: 0.85rem; }
        .resa-trajet { color: var(--noir); font-weight: 500; }
        .resa-date   { font-size: 0.82rem; color: var(--gris); }
        .resa-prix   { font-family: var(--font-display); font-weight: 600; color: var(--noir); white-space: nowrap; }
        .no-resa     { font-size: 0.88rem; color: var(--gris); font-style: italic; }

        /* ── Panel édition client ── */
        .edit-client-panel { display: none; margin-top: 1rem; border-top: 2px solid #2980b9; padding-top: 0.9rem; }
        .edit-client-panel.open { display: block; }
        .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem 1rem; }
        .edit-full { grid-column: span 2; }
        .edit-client-panel label {
            font-family: var(--font-display); font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: var(--gris); display: block; margin-bottom: 0.25rem;
        }
        .edit-client-panel input,
        .edit-client-panel select {
            width: 100%; padding: 0.45rem 0.7rem; border: 2px solid var(--gris-clair);
            border-radius: 4px; font-size: 0.9rem; color: var(--noir); transition: border-color 0.2s;
            background: #fff;
        }
        .edit-client-panel input:focus,
        .edit-client-panel select:focus { outline: none; border-color: #2980b9; }
        .edit-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; }
        .btn-save-client {
            background-color: #2980b9; border: 2px solid #2980b9; border-radius: 4px;
            padding: 0.45rem 1rem; font-family: var(--font-display); font-weight: 600;
            font-size: 0.85rem; text-transform: uppercase; cursor: pointer; color: #fff; transition: background-color 0.2s;
        }
        .btn-save-client:hover { background-color: #1f6390; border-color: #1f6390; }
        .btn-cancel-edit {
            background: none; border: 2px solid var(--gris-clair); border-radius: 4px;
            padding: 0.45rem 1rem; font-family: var(--font-display); font-weight: 600;
            font-size: 0.85rem; text-transform: uppercase; cursor: pointer; color: var(--gris); transition: border-color 0.2s;
        }
        .btn-cancel-edit:hover { border-color: var(--gris); }
        .edit-success { font-size: 0.82rem; color: #27ae60; font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em; margin-right: auto; align-self: center; }

        /* ── Modal suppression ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 500; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #fff; border-top: 4px solid var(--rouge); border-radius: 0 0 6px 6px; padding: 2rem 2.5rem; max-width: 420px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.25); }
        .modal-box h3 { font-family: var(--font-display); font-size: 1.2rem; text-transform: uppercase; color: var(--noir); margin-bottom: 0.75rem; }
        .modal-box p { font-size: 0.95rem; color: var(--gris); margin-bottom: 1.5rem; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; }
        .btn-annuler { background: none; border: 2px solid var(--gris-clair); border-radius: 4px; padding: 0.6rem 1.2rem; font-family: var(--font-display); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; cursor: pointer; color: var(--gris); transition: border-color 0.2s; }
        .btn-annuler:hover { border-color: var(--gris); }
        .btn-confirmer { background-color: var(--rouge); border: 2px solid var(--rouge); border-radius: 4px; padding: 0.6rem 1.2rem; font-family: var(--font-display); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; cursor: pointer; color: #fff; text-decoration: none; transition: background-color 0.2s; }
        .btn-confirmer:hover { background-color: var(--rouge-dark); border-color: var(--rouge-dark); }

        /* ── Inactifs ── */
        .inactifs-wrap { margin-top: 2rem; }
        .btn-goto { background: none; border: none; cursor: pointer; font-size: 1rem; color: var(--rouge); padding: 0; line-height: 1; }
        .btn-goto:hover { opacity: 0.7; }

        /* ── Section lignes & horaires ── */
        .lignes-horaires-wrap { margin-top: 2rem; }
        .ligne-edit-card { background: #fff; border-left: 4px solid var(--noir); border-radius: 0 6px 6px 0; padding: 1rem 1.25rem; margin-bottom: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .ligne-edit-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; cursor: pointer; user-select: none; }
        .ligne-edit-titre { font-family: var(--font-display); font-size: 1rem; font-weight: 600; color: var(--noir); text-transform: uppercase; }
        .ligne-edit-sous { font-size: 0.83rem; color: var(--gris); margin-top: 0.15rem; }
        .ligne-chevron { font-size: 0.85rem; color: var(--gris); transition: transform 0.2s; flex-shrink: 0; }
        .ligne-chevron.open { transform: rotate(180deg); }
        .ligne-edit-body { display: none; margin-top: 1rem; border-top: 1px solid var(--gris-clair); padding-top: 0.9rem; }
        .ligne-edit-body.open { display: block; }
        .ligne-infos-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.6rem 1rem; align-items: end; margin-bottom: 1.25rem; padding-bottom: 1.25rem; border-bottom: 1px dashed var(--gris-clair); }
        .ligne-infos-form label { font-family: var(--font-display); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--gris); display: block; margin-bottom: 0.25rem; }
        .ligne-infos-form input { width: 100%; padding: 0.45rem 0.7rem; border: 2px solid var(--gris-clair); border-radius: 4px; font-size: 0.9rem; color: var(--noir); transition: border-color 0.2s; }
        .ligne-infos-form input:focus { outline: none; border-color: var(--rouge); }
        .etapes-titre { font-family: var(--font-display); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gris); margin-bottom: 0.6rem; }
        .etapes-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .etapes-table thead tr { background: var(--noir); color: #fff; }
        .etapes-table thead th { padding: 0.5rem 0.6rem; text-align: left; font-family: var(--font-display); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .etapes-table tbody tr:nth-child(even) { background: var(--gris-clair); }
        .etapes-table tbody td { padding: 0.4rem 0.6rem; color: var(--gris); vertical-align: middle; }
        .etapes-table input[type="time"], .etapes-table input[type="number"] { padding: 0.3rem 0.5rem; border: 2px solid var(--gris-clair); border-radius: 3px; font-size: 0.85rem; width: 100%; color: var(--noir); transition: border-color 0.2s; }
        .etapes-table input:focus { outline: none; border-color: var(--rouge); }
        .btn-save-etape { background: none; border: 2px solid var(--gris-clair); border-radius: 4px; width: 30px; height: 30px; cursor: pointer; font-size: 0.9rem; color: var(--gris); display: flex; align-items: center; justify-content: center; transition: border-color 0.2s, color 0.2s; }
        .btn-save-etape:hover { border-color: #27ae60; color: #27ae60; }
        .btn-save-ligne { background-color: var(--rouge); border: 2px solid var(--rouge); border-radius: 4px; padding: 0.5rem 1.1rem; font-family: var(--font-display); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; cursor: pointer; color: #fff; white-space: nowrap; transition: background-color 0.2s; align-self: end; }
        .btn-save-ligne:hover { background-color: var(--rouge-dark); border-color: var(--rouge-dark); }

        @media (max-width: 700px) {
            .ligne-infos-form { grid-template-columns: 1fr; }
            .edit-grid { grid-template-columns: 1fr; }
            .edit-full { grid-column: span 1; }
        }

        #btnTop {
            position: fixed;
            bottom: 25px;
            right: 25px;

            width: 50px;
            height: 50px;

            border: none;
            border-radius: 50%;

            background: var(--rouge);
            color: white;

            font-size: 1.4rem;
            font-weight: bold;

            cursor: pointer;

            display: none;

            z-index: 1000;

            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        #btnTop:hover {
            background: var(--rouge-dark);
        }
    </style>
</head>
<body>
<?php include_once "nav.php"; ?>
<main>
    <h1>Tableau de bord admin</h1>

    <!-- ── Navigation rapide ── -->
    <div class="admin-nav">
        <button class="btn" onclick="scrollSec('section-stats')">Statistiques</button>
        <button class="btn" onclick="scrollSec('section-stats-interactives')">Stats avancées</button>
        <button class="btn" onclick="scrollSec('section-clients')">Clients</button>
        <button class="btn" onclick="scrollSec('section-inactifs')">Clients inactifs</button>
        <button class="btn" onclick="scrollSec('section-lignes')">Lignes &amp; Horaires</button>
    </div>

    <div class="admin-layout">

        <!-- ── Colonne gauche : graphiques ── -->
        <div class="admin-col admin-col-stats" id="section-stats">

            <div class="stat-card">
                <h3>Lignes les plus fréquentées</h3>
                <canvas id="chartLignes"></canvas>
            </div>
            <div class="stat-card">
                <h3>Top 10 heures de pointe</h3>
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

        <!-- ── Colonne droite : clients ── -->
        <div class="admin-col admin-col-clients" id="section-clients">

            <div class="clients-header">
                <h2>Clients (<?php echo count($clients); ?>)</h2>
                <input
                    type="search"
                    class="search-clients"
                    id="searchClients"
                    placeholder="Rechercher un client…"
                    oninput="filtrerClients(this.value)"
                >
            </div>

            <?php foreach ($clients as $client) {
                $cnum = $client['CLI_NUM'];
                $grade_client = $client['CLI_GRADE'] ?? 'client';
            ?>
            <div class="client-card" id="client-<?php echo $cnum; ?>"
                 data-search="<?php echo strtolower(htmlspecialchars($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM'] . ' ' . $client['CLI_COURRIEL'])); ?>">
                <div class="client-header">
                    <div class="client-info">
                        <div class="client-nom"><?php echo htmlspecialchars($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']); ?></div>
                        <div class="client-details">
                            <?php echo htmlspecialchars($client['CLI_COURRIEL']); ?> &nbsp;·&nbsp;
                            <?php echo $client['CLI_NB_POINTS_EC']; ?> pts
                            &nbsp;·&nbsp; <em><?php echo htmlspecialchars($grade_client); ?></em>
                        </div>
                    </div>
                    <div class="client-actions">
                        <button class="btn-ic btn-toggle" onclick="toggleResas(<?php echo $cnum; ?>)"      title="Voir les réservations">▼</button>
                        <button class="btn-ic btn-edit"   onclick="toggleEditClient(<?php echo $cnum; ?>)" title="Modifier le client">✏️</button>
                        <button class="btn-ic btn-suppr"  onclick="demanderSuppr(<?php echo $cnum; ?>, '<?php echo addslashes($client['CLI_PRENOM'] . ' ' . $client['CLI_NOM']); ?>')" title="Supprimer">🗑</button>
                    </div>
                </div>

                <!-- Panel réservations -->
                <div class="resas-panel" id="resas-<?php echo $cnum; ?>">
                    <?php if (empty($reservations[$cnum])) { ?>
                        <p class="no-resa">Aucune réservation.</p>
                    <?php } else { foreach ($reservations[$cnum] as $resa) { ?>
                        <div class="resa-ligne">
                            <span class="resa-num">#<?php echo $resa['RES_NUM']; ?></span>
                            <span class="resa-trajet"><?php echo htmlspecialchars($resa['DEPART']); ?> → <?php echo htmlspecialchars($resa['ARRIVEE']); ?></span>
                            <span class="resa-date"><?php echo $resa['RES_DATE']; ?></span>
                            <span class="resa-prix"><?php echo $resa['RES_PRIX_TOT']; ?> €</span>
                        </div>
                    <?php } } ?>
                </div>

                <!-- Panel modification client -->
                <div class="edit-client-panel <?php echo ($msg_modif === "client_$cnum") ? 'open' : ''; ?>" id="edit-client-<?php echo $cnum; ?>">
                    <form method="post" action="#">
                        <input type="hidden" name="action"  value="modif_client">
                        <input type="hidden" name="cli_num" value="<?php echo $cnum; ?>">
                        <div class="edit-grid">
                            <div>
                                <label>Prénom</label>
                                <input type="text" name="cli_prenom" value="<?php echo htmlspecialchars($client['CLI_PRENOM']); ?>" required>
                            </div>
                            <div>
                                <label>Nom</label>
                                <input type="text" name="cli_nom" value="<?php echo htmlspecialchars($client['CLI_NOM']); ?>" required>
                            </div>
                            <div class="edit-full">
                                <label>Courriel</label>
                                <input type="email" name="cli_courriel" value="<?php echo htmlspecialchars($client['CLI_COURRIEL']); ?>" required>
                            </div>
                            <div>
                                <label>Points</label>
                                <input type="number" name="cli_nb_points_ec" value="<?php echo $client['CLI_NB_POINTS_EC']; ?>" min="0">
                            </div>
                            <div>
                                <label>Rôle</label>
                                <select name="cli_grade">
                                    <option value="client"         <?php echo ($grade_client === 'client')         ? 'selected' : ''; ?>>Client</option>
                                    <option value="administrateur" <?php echo ($grade_client === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                        </div>
                        <div class="edit-actions">
                            <?php if ($msg_modif === "client_$cnum") { ?>
                                <span class="edit-success">✔ Sauvegardé</span>
                            <?php } ?>
                            <button type="button" class="btn-cancel-edit" onclick="toggleEditClient(<?php echo $cnum; ?>)">Annuler</button>
                            <button type="submit" class="btn-save-client">Enregistrer</button>
                        </div>
                    </form>
                </div>

            </div>
            <?php } ?>
        </div>

    </div>

    <!-- ── Stats avancées (pleine largeur) ── -->
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
                    <!-- Conserver les autres GET pour ne pas les écraser -->
                    <?php if ($stat_lv !== '')  echo "<input type='hidden' name='lv'  value='" . htmlspecialchars($stat_lv)  . "'>"; ?>
                    <?php if ($stat_dav !== '') echo "<input type='hidden' name='dav' value='" . htmlspecialchars($stat_dav) . "'>"; ?>
                    <button type="submit" class="btn">Calculer</button>
                </div>
            </form>
            <?php if ($stat_resa_villes !== null) { ?>
                <div class="stat-result"><?php echo $stat_resa_villes; ?></div>
                <div class="stat-result-label">réservation(s) de <?php echo htmlspecialchars($stat_v1); ?> vers <?php echo htmlspecialchars($stat_v2); ?></div>
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
                    <?php if ($stat_v1 !== '')  echo "<input type='hidden' name='v1'  value='" . htmlspecialchars($stat_v1)  . "'>"; ?>
                    <?php if ($stat_v2 !== '')  echo "<input type='hidden' name='v2'  value='" . htmlspecialchars($stat_v2)  . "'>"; ?>
                    <?php if ($stat_dav !== '') echo "<input type='hidden' name='dav' value='" . htmlspecialchars($stat_dav) . "'>"; ?>
                    <button type="submit" class="btn">Calculer</button>
                </div>
            </form>
            <?php if ($stat_lignes_ville !== null) { ?>
                <div class="stat-result"><?php echo $stat_lignes_ville; ?></div>
                <div class="stat-result-label">ligne(s) passant par <?php echo htmlspecialchars($stat_lv); ?></div>
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
                    <?php if ($stat_v1 !== '')  echo "<input type='hidden' name='v1'  value='" . htmlspecialchars($stat_v1)  . "'>"; ?>
                    <?php if ($stat_v2 !== '')  echo "<input type='hidden' name='v2'  value='" . htmlspecialchars($stat_v2)  . "'>"; ?>
                    <?php if ($stat_lv !== '')  echo "<input type='hidden' name='lv'  value='" . htmlspecialchars($stat_lv)  . "'>"; ?>
                    <button type="submit" class="btn">Calculer</button>
                </div>
            </form>
            <?php if ($stat_dep_arr !== null && $stat_dep_arr !== false) { ?>
                <div class="stat-result-grid">
                    <div class="stat-result-box">
                        <div class="val"><?php echo (int)$stat_dep_arr['NB_DEPARTS']; ?></div>
                        <div class="lbl">voyageur(s) au départ</div>
                    </div>
                    <div class="stat-result-box">
                        <div class="val"><?php echo (int)$stat_dep_arr['NB_ARRIVEES']; ?></div>
                        <div class="lbl">voyageur(s) à l'arrivée</div>
                    </div>
                </div>
                <div class="stat-result-label" style="margin-top:0.5rem">pour la ville de <?php echo htmlspecialchars($stat_dav); ?></div>
            <?php } else if ($stat_dav !== '') { ?>
                <p class="no-resa" style="margin-top:0.5rem">Aucune donnée pour cette ville.</p>
            <?php } ?>
        </div>

    </div>

    <!-- ── Clients inactifs ── -->
    <div class="inactifs-wrap" id="section-inactifs">
        <div class="stat-card">
            <h3>Clients inactifs (<?php echo count($inactifs); ?>)</h3>
            <?php if (count($inactifs) == 0) { ?>
                <p>Aucun client inactif depuis plus d'un an.</p>
            <?php } else { ?>
                <table>
                    <thead><tr><th>Prénom</th><th>Nom</th><th>Dernière connexion</th><th>Inactivité</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($inactifs as $c) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['CLI_PRENOM']); ?></td>
                            <td><?php echo htmlspecialchars($c['CLI_NOM']); ?></td>
                            <td><?php echo $c['CLI_DATE_CONNEC']; ?></td>
                            <td><?php
                                $j = (int)$c['JOURS'];
                                if ($j > 730) echo "<strong style='color:var(--rouge)'>$j j (à supprimer)</strong>";
                                else          echo "$j j";
                            ?></td>
                            <td><button class="btn-goto" onclick="scrollToClient(<?php echo $c['CLI_NUM']; ?>)" title="Aller au client">↗</button></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </div>

    <!-- ── Lignes & Horaires ── -->
    <div class="lignes-horaires-wrap" id="section-lignes">
        <div class="stat-card">
            <h3>Lignes &amp; Horaires (<?php echo count($all_lignes); ?> lignes)</h3>

            <?php foreach ($all_lignes as $lig) {
                $lnum   = trim($lig['LIG_NUM']);
                $etapes = $etapes_par_ligne[$lnum] ?? [];
                $isOpen = ($msg_ligne === $lnum);
            ?>
            <div class="ligne-edit-card" id="ligne-<?php echo htmlspecialchars($lnum); ?>">
                <div class="ligne-edit-header" onclick="toggleLigne('<?php echo addslashes($lnum); ?>')">
                    <div>
                        <div class="ligne-edit-titre">Ligne <?php echo htmlspecialchars($lnum); ?></div>
                        <div class="ligne-edit-sous">
                            <?php echo htmlspecialchars($lig['NOM_DEBU']); ?> → <?php echo htmlspecialchars($lig['NOM_TERM']); ?>
                            &nbsp;·&nbsp; <?php echo count($etapes); ?> étape(s)
                        </div>
                    </div>
                    <span class="ligne-chevron <?php echo $isOpen ? 'open' : ''; ?>" id="chevron-<?php echo htmlspecialchars($lnum); ?>">▼</span>
                </div>

                <div class="ligne-edit-body <?php echo $isOpen ? 'open' : ''; ?>" id="body-<?php echo htmlspecialchars($lnum); ?>">
                    <form method="post" action="#ligne-<?php echo htmlspecialchars($lnum); ?>">
                        <input type="hidden" name="action"  value="modif_ligne">
                        <input type="hidden" name="lig_num" value="<?php echo htmlspecialchars($lnum); ?>">
                        <div class="ligne-infos-form">
                            <div>
                                <label>Code INSEE départ</label>
                                <input type="text" name="com_code_insee_debu" value="<?php echo htmlspecialchars($lig['COM_CODE_INSEE_DEBU']); ?>" maxlength="10" required>
                            </div>
                            <div>
                                <label>Code INSEE terminus</label>
                                <input type="text" name="com_code_insee_term" value="<?php echo htmlspecialchars($lig['COM_CODE_INSEE_TERM']); ?>" maxlength="10" required>
                            </div>
                            <div>
                                <button type="submit" class="btn-save-ligne"><?php echo $isOpen ? '✔ Sauvegardé' : 'Enregistrer'; ?></button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($etapes)) { ?>
                        <p class="no-resa">Aucune étape enregistrée pour cette ligne.</p>
                    <?php } else { ?>
                        <p class="etapes-titre">Étapes</p>
                        <table class="etapes-table">
                            <thead><tr><th>Départ</th><th>Arrivée</th><th>Heure</th><th>Distance (km)</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($etapes as $etape) { ?>
                                <tr>
                                    <form method="post" action="#ligne-<?php echo htmlspecialchars($lnum); ?>">
                                        <input type="hidden" name="action"  value="modif_etape">
                                        <input type="hidden" name="lig_num" value="<?php echo htmlspecialchars($lnum); ?>">
                                        <input type="hidden" name="cli_num" value="<?php echo $etape['CLI_NUM']; ?>">
                                        <input type="hidden" name="res_num" value="<?php echo $etape['RES_NUM']; ?>">
                                        <td><?php echo htmlspecialchars($etape['NOM_DEPART']); ?></td>
                                        <td><?php echo htmlspecialchars($etape['NOM_ARRIVEE']); ?></td>
                                        <td><input type="time"   name="eta_heure"    value="<?php echo htmlspecialchars($etape['ETA_HEURE_HM']); ?>"></td>
                                        <td><input type="number" name="eta_distance" value="<?php echo str_replace(',', '.', $etape['ETA_DISTANCE']); ?>" step="0.1" min="0"></td>
                                        <td><button type="submit" class="btn-save-etape" title="Enregistrer">✔</button></td>
                                    </form>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

</main>

<!-- Modal suppression -->
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

<button id="btnTop" onclick="retourHaut()">↑</button>

<?php include_once "footer.php"; ?>

<script>
    const rouge      = '#C0392B';
    const rougeClair = 'rgba(192, 57, 43, 0.7)';
    const gris       = '#4a4a4a';

    new Chart(document.getElementById('chartLignes'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($lignes_labels); ?>, datasets: [{ data: <?php echo json_encode($lignes_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris, font: { size: 10 } } } } }
    });
    new Chart(document.getElementById('chartHeures'), {
        type: 'line',
        data: { labels: <?php echo json_encode($heures_labels); ?>, datasets: [{ data: <?php echo json_encode($heures_data); ?>, backgroundColor: 'rgba(192,57,43,0.15)', borderColor: rouge, borderWidth: 2, pointBackgroundColor: rouge, fill: true, tension: 0.3 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
    });
    new Chart(document.getElementById('chartLignesHeures'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($lh_labels); ?>, datasets: [{ data: <?php echo json_encode($lh_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 9 } } } } }
    });
    new Chart(document.getElementById('chartClients'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($top_labels); ?>, datasets: [{ data: <?php echo json_encode($top_data); ?>, backgroundColor: rougeClair, borderColor: rouge, borderWidth: 2, borderRadius: 4 }] },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: gris } }, y: { ticks: { color: gris, font: { size: 10 } } } } }
    });
    new Chart(document.getElementById('chartMois'), {
        type: 'line',
        data: { labels: <?php echo json_encode($mois_labels); ?>, datasets: [{ data: <?php echo json_encode($mois_data); ?>, backgroundColor: 'rgba(192,57,43,0.15)', borderColor: rouge, borderWidth: 2, pointBackgroundColor: rouge, fill: true, tension: 0.3 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: gris } }, x: { ticks: { color: gris } } } }
    });

    /* ── Navigation rapide ── */
    function scrollSec(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const nav = document.querySelector('nav');
        const offset = (nav ? nav.offsetHeight : 0) + 16;
        window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
    }

    /* ── Recherche clients ── */
    function filtrerClients(val) {
        const q = val.toLowerCase().trim();
        document.querySelectorAll('.client-card').forEach(card => {
            const search = card.dataset.search || '';
            card.classList.toggle('hidden', q !== '' && !search.includes(q));
        });
    }

    /* ── Réservations ── */
    function toggleResas(num) {
        document.getElementById('resas-' + num).classList.toggle('open');
    }

    /* ── Édition client ── */
    function toggleEditClient(num) {
        document.getElementById('edit-client-' + num).classList.toggle('open');
    }

    /* ── Suppression ── */
    function demanderSuppr(num, nom) {
        document.getElementById('modal-texte').innerText = 'Voulez-vous vraiment supprimer le compte de ' + nom + ' ? Cette action est irréversible.';
        document.getElementById('modal-lien').href = 'supprimer_client.php?id=' + num;
        document.getElementById('modal').classList.add('open');
    }
    function fermerModal() {
        document.getElementById('modal').classList.remove('open');
    }

    /* ── Scroll vers client depuis inactifs ── */
    function scrollToClient(num) {
        const el = document.getElementById('client-' + num);
        if (!el) return;
        const nav = document.querySelector('nav');
        const offset = (nav ? nav.offsetHeight : 0) + 16;
        window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
    }

    /* ── Accordéon lignes ── */
    function toggleLigne(lnum) {
        document.getElementById('body-'    + lnum).classList.toggle('open');
        document.getElementById('chevron-' + lnum).classList.toggle('open');
    }

    const btnTop = document.getElementById('btnTop');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            btnTop.style.display = 'block';
        } else {
            btnTop.style.display = 'none';
        }
    });

    function retourHaut() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
</script>
</body>
</html>