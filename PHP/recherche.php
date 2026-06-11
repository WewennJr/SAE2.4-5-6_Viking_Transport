<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$stmtCom = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
$communes = $stmtCom->fetchAll(PDO::FETCH_ASSOC);

$resultat   = null;
$segments   = [];
$erreur     = "";
$depart     = $_POST['depart']  ?? '';
$arrivee    = $_POST['arrivee'] ?? '';
$dateVoyage = $_POST['date']    ?? date('Y-m-d');
$critere    = $_POST['critere'] ?? 'duree';   // 'duree' = plus rapide, 'distance' = plus court

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($depart) || empty($arrivee)) {
        $erreur = "Veuillez choisir une commune de départ et d'arrivée.";
    } elseif ($depart === $arrivee) {
        $erreur = "Le départ et l'arrivée doivent être différents.";
    } else {
        $graphe   = chargerGraphe($conn);
        $noms     = chargerNomsCommunes($conn);
        $resultat = trouverTrajet($graphe, $depart, $arrivee, $critere);

        if ($resultat === null) {
            $erreur = "Aucun trajet trouvé entre ces deux communes.";
        } else {
            $segments = regrouperParLigne($resultat['chemin']);
            $distTot  = $resultat['distanceTotale'];
            $points   = calculerPoints($distTot);
            $_SESSION['trajet_a_reserver'] = [
                'depart'   => $depart,
                'arrivee'  => $arrivee,
                'date'     => $dateVoyage,
                'chemin'   => $resultat['chemin'],
                'duree'    => $resultat['dureeTotale'],
                'distance' => $distTot,
                'points'   => $points,
            ];
        }
    }
}

$conn = null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechercher un trajet - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/recherche.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../CSS/flatpickr-viking.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <h1>Rechercher un trajet</h1>

    <section class="carte-recherche-form">
        <form action="recherche.php" method="post">
            <div class="form-row">
                <div class="form-group">
                    <label for="depart">Départ</label>
                    <select name="depart" id="depart" required>
                        <option value="">-- Choisir une commune --</option>
                        <?php foreach ($communes as $c) {
                            $code = trim($c['COM_CODE_INSEE']);
                            $sel  = ($code === $depart) ? 'selected' : '';
                            echo "<option value=\"$code\" $sel>" . htmlspecialchars($c['COM_NOM']) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="arrivee">Arrivée</label>
                    <select name="arrivee" id="arrivee" required>
                        <option value="">-- Choisir une commune --</option>
                        <?php foreach ($communes as $c) {
                            $code = trim($c['COM_CODE_INSEE']);
                            $sel  = ($code === $arrivee) ? 'selected' : '';
                            echo "<option value=\"$code\" $sel>" . htmlspecialchars($c['COM_NOM']) . "</option>";
                        } ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date">Date du voyage</label>
                    <input type="text" name="date" id="date" value="<?= htmlspecialchars($dateVoyage) ?>" required readonly>
                </div>
                <div class="form-group">
                    <label>Critère de recherche</label>
                    <div class="choix-critere">
                        <label class="radio-critere">
                            <input type="radio" name="critere" value="duree" <?= ($critere === 'duree') ? 'checked' : '' ?>>
                            <span>Le plus rapide</span>
                        </label>
                        <label class="radio-critere">
                            <input type="radio" name="critere" value="distance" <?= ($critere === 'distance') ? 'checked' : '' ?>>
                            <span>Le plus court</span>
                        </label>
                    </div>
                </div>
            </div>

            <input type="submit" value="Rechercher le trajet">
        </form>
    </section>

    <?php if (!empty($erreur)) { ?>
        <p id="erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php } ?>

    <?php if ($resultat !== null && empty($erreur)) {
        $connAff = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        $noms = chargerNomsCommunes($connAff);
        $connAff = null;

        $h = intdiv($resultat['dureeTotale'], 60);
        $m = $resultat['dureeTotale'] % 60;
        $libelleCritere = ($critere === 'distance') ? 'le plus court' : 'le plus rapide';
    ?>
        <section class="carte-trajet">
            <div class="trajet-entete">
                <h2><?= htmlspecialchars($noms[$depart] ?? $depart) ?> → <?= htmlspecialchars($noms[$arrivee] ?? $arrivee) ?></h2>
                <p class="trajet-critere">Trajet <?= $libelleCritere ?></p>
                <div class="trajet-resume">
                    <span class="resume-item"><strong><?= $h ?>h<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></strong> de trajet</span>
                    <span class="resume-item"><strong><?= $distTot ?> km</strong></span>
                    <span class="resume-item"><strong><?= count($segments) - 1 ?></strong> correspondance(s)</span>
                    <span class="resume-item points"><strong>+<?= $points ?></strong> points</span>
                </div>
            </div>

            <ol class="trajet-etapes">
                <?php foreach ($segments as $s) {
                    $nomDep = $noms[$s['depart']]  ?? $s['depart'];
                    $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];
                ?>
                    <li>
                        <span class="etape-ligne">Ligne <?= htmlspecialchars($s['ligne']) ?></span>
                        <span class="etape-trajet"><?= htmlspecialchars($nomDep) ?> → <?= htmlspecialchars($nomArr) ?></span>
                        <span class="etape-info"><?= $s['duree'] ?> min · <?= $s['distance'] ?> km</span>
                    </li>
                <?php } ?>
            </ol>

            <form action="paiement.php" method="post" class="form-reserver">
                <input type="submit" value="Réserver ce trajet">
            </form>
        </section>
    <?php } ?>
</main>

<?php include_once "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script>
flatpickr("#date", {
    locale: "fr",
    dateFormat: "Y-m-d",
    minDate: "today",
    defaultDate: "<?= htmlspecialchars($dateVoyage) ?>"
});
</script>

</body>
</html>