<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horaires – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .ligne-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .ligne-nav h1 {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
            flex: 1;
        }

        .ligne-nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 4px;
            border: 2px solid var(--rouge);
            color: var(--rouge);
            font-size: 1.2rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background-color 0.2s, color 0.2s;
        }

        .ligne-nav-btn:hover {
            background-color: var(--rouge);
            color: #fff;
        }

        .ligne-nav-btn.disabled {
            border-color: var(--gris-clair);
            color: var(--gris-clair);
            pointer-events: none;
        }

        /* Séparateur sous le bloc titre+nav */
        .ligne-nav + p.horaires-meta {
            margin-top: 0.5rem;
            padding-top: 0.6rem;
            border-top: 4px solid var(--rouge);
        }

        .horaires-meta {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gris);
            margin-bottom: 2.5rem;
        }

        .horaires-table-wrap {
            overflow-x: auto;
            max-width: 100%;
        }

        table {
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        thead tr {
            border-bottom: 3px solid var(--rouge);
        }

        thead th {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--noir);
            padding: 0.65rem 1rem;
            text-align: center;
            white-space: nowrap;
        }

        thead th.th-arret {
            text-align: left;
            min-width: 200px;
        }

        thead th.th-horaire {
            color: var(--rouge);
            min-width: 80px;
        }

        tbody tr {
            border-bottom: 1px solid var(--gris-clair);
            transition: background-color 0.15s;
        }

        tbody tr:last-child {
            border-bottom: 3px solid var(--rouge);
        }

        tbody tr:hover {
            background-color: #f0f0f0;
        }

        tbody td {
            padding: 0.7rem 1rem;
            vertical-align: middle;
        }

        td.arret-nom {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--noir);
            white-space: nowrap;
        }

        td.arret-nom::before {
            content: '⬤ ';
            font-size: 0.45rem;
            color: var(--rouge);
            vertical-align: middle;
            margin-right: 4px;
        }

        tbody tr:last-child td.arret-nom {
            color: var(--rouge);
        }

        td.heure {
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 600;
            color: var(--noir);
            letter-spacing: 0.04em;
            text-align: center;
            white-space: nowrap;
        }

        .retour-lien {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 2.5rem;
            font-family: var(--font-display);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gris);
            transition: color 0.2s;
        }

        .retour-lien:hover { color: var(--rouge); }
        .retour-lien::before { content: '←'; font-size: 1rem; }

        .sens-toggle {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
        }

        .sens-btn {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.6rem 1.4rem;
            border-radius: 4px;
            border: 2px solid var(--rouge);
            color: var(--rouge);
            background-color: transparent;
            transition: background-color 0.2s, color 0.2s;
        }

        .sens-btn:hover {
            background-color: var(--rouge);
            color: #fff;
        }

        .sens-btn.actif {
            background-color: var(--rouge);
            color: #fff;
            pointer-events: none;
        }

        .horaires-vide {
            padding: 2rem 0;
            color: var(--gris);
            font-style: italic;
        }

        /* ── Sticky desktop : colonne arrêts figée à gauche ── */
        table.desktop {
            display: table;
            width: 100%;
        }

        table.desktop thead th.th-arret,
        table.desktop tbody td.arret-nom {
            position: sticky;
            left: 0;
            background-color: var(--blanc);
            z-index: 2;
        }

        table.desktop thead th.th-arret {
            z-index: 3;
            color: var(--noir);
        }

        table.desktop tbody tr:hover td.arret-nom {
            background-color: #f0f0f0;
        }

        /* ── Sticky mobile : ligne villes figée en haut ────── */
        @media (min-width: 701px) {
            table.mobile { display: none; }
        }

        @media (max-width: 700px) {
            table.desktop { display: none; }

            .horaires-table-wrap {
                overflow-x: auto;
                overflow-y: auto;
                max-height: 80vh;
                -webkit-overflow-scrolling: touch;
            }

            table.mobile {
                display: table;
                width: max-content;
                min-width: 100%;
                border-collapse: collapse;
                font-size: 0.88rem;
            }

            table.mobile thead th {
                position: sticky;
                top: 0;
                background-color: var(--blanc);
                z-index: 2;
                font-family: var(--font-display);
                font-weight: 700;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                padding: 0.6rem 0.7rem;
                text-align: center;
                white-space: nowrap;
                color: var(--noir);
                border-bottom: 3px solid var(--rouge);
            }

            table.mobile thead th.th-label {
                text-align: left;
                position: sticky;
                top: 0;
                left: 0;
                z-index: 4;
                background-color: var(--blanc);
            }

            table.mobile tbody tr {
                border-bottom: 1px solid var(--gris-clair);
            }

            table.mobile tbody tr:last-child {
                border-bottom: 3px solid var(--rouge);
            }

            table.mobile td {
                padding: 0.6rem 0.7rem;
                vertical-align: middle;
            }

            table.mobile td.depart-label {
                position: sticky;
                left: 0;
                background-color: var(--blanc);
                z-index: 1;
                font-family: var(--font-display);
                font-weight: 700;
                font-size: 0.82rem;
                color: var(--rouge);
                white-space: nowrap;
                border-right: 1px solid var(--gris-clair);
            }

            table.mobile td.heure {
                font-family: var(--font-display);
                font-size: 0.9rem;
                font-weight: 600;
                color: var(--noir);
                text-align: center;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
<?php include_once("../PHP/nav.php"); ?>

<main>
<?php
include_once("../PHP/param_connexion_etu.php");
include_once("../PHP/pdo_agile.php");

$ligNum = isset($_GET['lig']) ? $_GET['lig'] : null;

if ($ligNum === null) {
    echo "<p class='horaires-vide'>Aucune ligne sélectionnée.</p>";
} else {
    $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

    // Extraire le numéro de base et le sens (A ou B)
    $sens    = strtoupper(substr(trim($ligNum), -1)); // 'A' ou 'B'
    $numBase = rtrim(trim($ligNum), 'ABab');          // ex: '1', '10'
    $ligA    = $numBase . 'A';
    $ligB    = $numBase . 'B';

    // Numéros de ligne précédente et suivante (boucle 1-19)
    $numInt  = (int)$numBase;
    $prevNum = ($numInt > 1  ? $numInt - 1 : 19) . $sens;
    $nextNum = ($numInt < 19 ? $numInt + 1 : 1)  . $sens;

    echo "<div class='ligne-nav'>";
    echo "<a href='horaire_ligne.php?lig=" . htmlspecialchars($prevNum) . "' class='ligne-nav-btn' title='Ligne précédente'>&#8592;</a>";
    echo "<h1>Horaires &ndash; Ligne " . htmlspecialchars($numBase . $sens) . "</h1>";
    echo "<a href='horaire_ligne.php?lig=" . htmlspecialchars($nextNum) . "' class='ligne-nav-btn' title='Ligne suivante'>&#8594;</a>";
    echo "</div>";
    echo "<p class='horaires-meta'>Réseau Viking Transport &mdash; Normandie</p>";
    echo "<div class='sens-toggle'>
        <a href='horaire_ligne.php?lig=" . htmlspecialchars($ligA) . "'
           class='sens-btn" . ($sens === 'A' ? ' actif' : '') . "'>
            &#9654; Ligne A
        </a>
        <a href='horaire_ligne.php?lig=" . htmlspecialchars($ligB) . "'
           class='sens-btn" . ($sens === 'B' ? ' actif' : '') . "'>
            &#9664; Ligne B
        </a>
    </div>";

    /*
     * ÉTAPE 1 — Récupérer tous les noeuds de la ligne
     * Chaque noeud = un segment (arret → suivant) avec une heure de passage.
     * Un même segment apparaît N fois (une fois par horaire de la journée).
     */
    $sql = "SELECT n.COM_CODE_INSEE_ARRET,
                   n.COM_CODE_INSEE_SUIVANT,
                   c.COM_NOM AS arret_nom,
                   TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') AS heure,
                   n.NOE_DUREE_PROCHAIN AS duree
            FROM VIK_NOEUD n
            JOIN VIK_COMMUNE c ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
            WHERE TRIM(n.LIG_NUM) = TRIM(:lig)
            ORDER BY n.NOE_HEURE_PASSAGE";

    $cur = preparerRequetePDO($conn, $sql);
    $cur->bindValue(':lig', $ligNum, PDO::PARAM_STR);
    LireDonneesPDOPreparee($cur, $tab);

    if (empty($tab)) {
        echo "<p class='horaires-vide'>Aucun horaire trouvé pour cette ligne.</p>";
    } else {

        /*
         * ÉTAPE 2 — Reconstituer la chaîne géographique des arrêts
         * On suit arret → suivant → suivant... depuis le premier arrêt
         * (celui qui n'apparaît jamais comme "suivant")
         */

        // Construire un index : arret_insee => [suivant_insee, arret_nom]
        $segmentsGeo = [];
        foreach ($tab as $row) {
            $segmentsGeo[$row['COM_CODE_INSEE_ARRET']] = [
                'suivant' => $row['COM_CODE_INSEE_SUIVANT'],
                'nom'     => $row['ARRET_NOM'],
            ];
        }

        // Trouver le premier arrêt : celui qui n'est jamais un "suivant"
        $tousSuivants = array_column($tab, 'COM_CODE_INSEE_SUIVANT');
        $premier = null;
        foreach (array_keys($segmentsGeo) as $insee) {
            if (!in_array($insee, $tousSuivants)) {
                $premier = $insee;
                break;
            }
        }
        if ($premier === null) {
            // Fallback : prendre le premier de la liste
            $premier = $tab[0]['COM_CODE_INSEE_ARRET'];
        }

        // Suivre la chaîne pour obtenir les arrêts dans l'ordre géographique
        $chaineArrets = []; // [ [insee, nom], ... ]
        $courant = $premier;
        $vus = [];
        while ($courant !== null && isset($segmentsGeo[$courant]) && !isset($vus[$courant])) {
            $vus[$courant] = true;
            $chaineArrets[] = [
                'insee' => $courant,
                'nom'   => $segmentsGeo[$courant]['nom'],
            ];
            $courant = $segmentsGeo[$courant]['suivant'];
        }
        // Ajouter le terminus (dernier "suivant" qui n'a pas de noeud propre)
        if ($courant !== null && !isset($segmentsGeo[$courant])) {
            // Récupérer le nom du terminus
            $sqlT = "SELECT COM_NOM FROM VIK_COMMUNE WHERE COM_CODE_INSEE = :insee";
            $curT = preparerRequetePDO($conn, $sqlT);
            $curT->bindValue(':insee', $courant, PDO::PARAM_STR);
            LireDonneesPDOPreparee($curT, $tabT);
            $terminusNom = !empty($tabT) ? $tabT[0]['COM_NOM'] : 'Terminus';
            $chaineArrets[] = ['insee' => $courant, 'nom' => $terminusNom, 'terminus' => true];
        }

        /*
         * ÉTAPE 3 — Pour chaque arrêt (sauf terminus), récupérer
         * toutes ses heures de passage triées
         */
        $heuresParArret = []; // [ insee => [heure1, heure2, ...] ]
        $dureeParArret  = []; // [ insee => duree ] (même durée pour tous les passages)
        foreach ($tab as $row) {
            $heuresParArret[$row['COM_CODE_INSEE_ARRET']][] = $row['HEURE'];
            $dureeParArret[$row['COM_CODE_INSEE_ARRET']]    = (int)$row['DUREE'];
        }

        // Nombre de colonnes = nombre d'horaires du premier arrêt
        $nbHoraires = count($heuresParArret[$chaineArrets[0]['insee']]);

        /*
         * ÉTAPE 4 — Affichage
         */
        echo "<div class='horaires-table-wrap'><table class='desktop'><thead><tr>";
        echo "<th class='th-arret'>Arrêt</th>";
        for ($i = 1; $i <= $nbHoraires; $i++) {
            echo "<th class='th-horaire'>Départ&nbsp;" . $i . "</th>";
        }
        echo "</tr></thead><tbody>";

        foreach ($chaineArrets as $arret) {
            $isTerminus = isset($arret['terminus']) && $arret['terminus'];
            echo "<tr><td class='arret-nom'>" . htmlspecialchars($arret['nom']) . "</td>";

            if ($isTerminus) {
                // Calculer l'heure d'arrivée = heure dernier arrêt + NOE_DUREE_PROCHAIN
                $dernierArret = $chaineArrets[count($chaineArrets) - 2]; // avant-dernier = dernier vrai arrêt
                $heuresDernier = $heuresParArret[$dernierArret['insee']] ?? [];
                $duree = $dureeParArret[$dernierArret['insee']] ?? 0;
                for ($i = 0; $i < $nbHoraires; $i++) {
                    if (isset($heuresDernier[$i])) {
                        // Ajouter $duree minutes à l'heure du dernier arrêt
                        list($hh, $mm) = explode(':', $heuresDernier[$i]);
                        $totalMin = (int)$hh * 60 + (int)$mm + $duree;
                        $hArrivee = sprintf('%02d:%02d', intdiv($totalMin, 60) % 24, $totalMin % 60);
                        echo "<td class='heure'>" . $hArrivee . "</td>";
                    } else {
                        echo "<td class='heure'>—</td>";
                    }
                }
            } else {
                $heures = $heuresParArret[$arret['insee']] ?? [];
                for ($i = 0; $i < $nbHoraires; $i++) {
                    $h = isset($heures[$i]) ? htmlspecialchars($heures[$i]) : '—';
                    echo "<td class='heure'>" . $h . "</td>";
                }
            }
            echo "</tr>";
        }

        echo "</tbody></table></div>";

        // ── Tableau mobile (transposé : 1 ligne = 1 départ) ──
        // Précalculer les heures terminus
        $heuresTerminus = [];
        $dernierArret = $chaineArrets[count($chaineArrets) - 2];
        $heuresDernier = $heuresParArret[$dernierArret['insee']] ?? [];
        $duree = $dureeParArret[$dernierArret['insee']] ?? 0;
        for ($i = 0; $i < $nbHoraires; $i++) {
            if (isset($heuresDernier[$i])) {
                list($hh, $mm) = explode(':', $heuresDernier[$i]);
                $totalMin = (int)$hh * 60 + (int)$mm + $duree;
                $heuresTerminus[$i] = sprintf('%02d:%02d', intdiv($totalMin, 60) % 24, $totalMin % 60);
            } else {
                $heuresTerminus[$i] = '—';
            }
        }

        echo "<div class='horaires-table-wrap'><table class='mobile'><thead><tr>";
        echo "<th class='th-label'>Départ</th>";
        foreach ($chaineArrets as $arret) {
            echo "<th class='th-depart'>" . htmlspecialchars($arret['nom']) . "</th>";
        }
        echo "</tr></thead><tbody>";

        for ($i = 0; $i < $nbHoraires; $i++) {
            echo "<tr><td class='depart-label'>Départ&nbsp;" . ($i + 1) . "</td>";
            foreach ($chaineArrets as $arret) {
                $isTerminus = isset($arret['terminus']) && $arret['terminus'];
                if ($isTerminus) {
                    echo "<td class='heure'>" . $heuresTerminus[$i] . "</td>";
                } else {
                    $heures = $heuresParArret[$arret['insee']] ?? [];
                    $h = isset($heures[$i]) ? htmlspecialchars($heures[$i]) : '—';
                    echo "<td class='heure'>" . $h . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</tbody></table></div>";
    }
}
$conn=null;
?>

    <a href="lignes.php" class="retour-lien">Retour aux lignes</a>
</main>

<?php include_once("../PHP/footer.php"); ?>
</body>
</html>