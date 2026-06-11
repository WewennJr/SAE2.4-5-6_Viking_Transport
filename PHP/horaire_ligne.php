<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Horaires – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .horaires-meta {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gris);
            margin-bottom: 2.5rem;
        }

        .horaires-table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
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
            min-width: 180px;
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

        td.heure-vide {
            text-align: center;
            color: var(--gris-clair);
            font-size: 0.8rem;
        }

        thead th.th-horaire {
            color: var(--rouge);
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

        .retour-lien:hover {
            color: var(--rouge);
        }

        .retour-lien::before {
            content: '←';
            font-size: 1rem;
        }

        .horaires-vide {
            padding: 2rem 0;
            color: var(--gris);
            font-style: italic;
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

    echo "<h1>Horaires &ndash; Ligne " . htmlspecialchars($ligNum) . "</h1>";
    echo "<p class='horaires-meta'>Réseau Viking Transport &mdash; Normandie</p>";

    // Récupère tous les noeuds de la ligne, triés par heure
    $sql = "SELECT n.COM_CODE_INSEE_ARRET,
                   n.COM_CODE_INSEE_SUIVANT,
                   cd.COM_NOM AS arret_nom,
                   TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') AS heure_passage
            FROM VIK_NOEUD n
            JOIN VIK_COMMUNE cd ON cd.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
            WHERE TRIM(n.LIG_NUM) = TRIM(:lig)
            ORDER BY n.NOE_HEURE_PASSAGE";

    $cur = preparerRequetePDO($conn, $sql);
    $cur->bindValue(':lig', $ligNum, PDO::PARAM_STR);
    LireDonneesPDOPreparee($cur, $tab);

    if (empty($tab)) {
        echo "<p class='horaires-vide'>Aucun horaire trouvé pour cette ligne.</p>";
    } else {
        $trajets  = [];  // [ [ [arret_nom, heure], ... ], ... ]
        $trajetCourant = [];
        $suivantAttendu = null;

        foreach ($tab as $row) {

            if ($suivantAttendu !== null && $row['COM_CODE_INSEE_ARRET'] !== $suivantAttendu) {
                $trajets[] = $trajetCourant;
                $trajetCourant = [];
            }
            $trajetCourant[] = [
                'arret'  => $row['ARRET_NOM'],
                'insee'  => $row['COM_CODE_INSEE_ARRET'],
                'heure'  => $row['HEURE_PASSAGE'],
            ];
            $suivantAttendu = $row['COM_CODE_INSEE_SUIVANT'];
        }
        if (!empty($trajetCourant)) {
            $trajets[] = $trajetCourant;
        }

        $sqlTerminus = "SELECT COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT, 
                        TO_CHAR(NOE_HEURE_PASSAGE,'HH24:MI') AS heure
                        FROM VIK_NOEUD
                        WHERE TRIM(LIG_NUM) = '10A'
                        ORDER BY COM_CODE_INSEE_ARRET, NOE_HEURE_PASSAGE
                        )";
        $curT = preparerRequetePDO($conn, $sqlTerminus);
        $curT->bindValue(':lig',  $ligNum, PDO::PARAM_STR);
        $curT->bindValue(':lig2', $ligNum, PDO::PARAM_STR);
        LireDonneesPDOPreparee($curT, $tabT);
        $terminusNom = !empty($tabT) ? $tabT[0]['COM_NOM'] : 'Terminus';

        $arrets = []; 
        foreach ($trajets[0] as $etape) {
            $arrets[$etape['insee']] = $etape['arret'];
        }

        $arrets['__terminus__'] = $terminusNom;

        $trajetsIndexes = [];
        foreach ($trajets as $t) {
            $idx = [];
            foreach ($t as $etape) {
                $idx[$etape['insee']] = $etape['heure'];
            }
            $trajetsIndexes[] = $idx;
        }


        $nbTrajets = count($trajetsIndexes);

        echo "<div class='horaires-table-wrap'><table><thead><tr>";
        echo "<th class='th-arret'>Arrêt</th>";
        for ($i = 1; $i <= $nbTrajets; $i++) {
            echo "<th class='th-horaire'>Départ " . $i . "</th>";
        }
        echo "</tr></thead><tbody>";

        foreach ($arrets as $insee => $nom) {
            echo "<tr><td class='arret-nom'>" . htmlspecialchars($nom) . "</td>";
            foreach ($trajetsIndexes as $trajetIdx) {
                if ($insee === '__terminus__') {
                    echo "<td class='heure-vide'>—</td>";
                } elseif (isset($trajetIdx[$insee])) {
                    echo "<td class='heure'>" . htmlspecialchars($trajetIdx[$insee]) . "</td>";
                } else {
                    echo "<td class='heure-vide'>—</td>";
                }
            }
            echo "</tr>";
        }

        echo "</tbody></table></div>";
    }
}
?>

    <a href="lignes.php" class="retour-lien">Retour aux lignes</a>
</main>

<?php include_once("../PHP/footer.php"); ?>
</body>
</html>