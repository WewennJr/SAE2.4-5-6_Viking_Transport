<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$stmtCom = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
$communes = $stmtCom->fetchAll(PDO::FETCH_ASSOC);

$erreur     = "";
$depart     = $_POST['depart']  ?? '';
$arrivee    = $_POST['arrivee'] ?? '';
$dateVoyage = $_POST['date']    ?? date('Y-m-d');
$critere    = $_POST['critere'] ?? 'duree';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($depart) || empty($arrivee)) {
        $erreur = "Veuillez choisir une commune de départ et d'arrivée.";
    } elseif ($depart === $arrivee) {
        $erreur = "Le départ et l'arrivée doivent être différents.";
    } else {
        $graphe    = chargerGraphe($conn);
        $noms      = chargerNomsCommunes($conn);
        $resultats = trouverKTrajets($graphe, $depart, $arrivee, $critere, 5);

        if (empty($resultats)) {
            $erreur = "Aucun trajet trouvé entre ces deux communes.";
        } else {
            // Détermination de la réduction si le client est connecté
            $pourcentagePaye = 100;
            if (isset($_SESSION['num_client'])) {
                $stmtCli = $conn->prepare("
                    SELECT t.TYP_REDUC 
                    FROM VIK_CLIENT c JOIN VIK_TYPE_CLIENT t ON c.TYP_NUM = t.TYP_NUM 
                    WHERE c.CLI_NUM = :num
                ");
                $stmtCli->execute([':num' => $_SESSION['num_client']]);
                $niv = $stmtCli->fetch(PDO::FETCH_ASSOC);
                if ($niv) {
                    $pourcentagePaye = (float)$niv['TYP_REDUC'];
                }
            }

            // Préparation de la requête de tarification
            $stmtTar = $conn->prepare("SELECT TAR_PRIX FROM VIK_TARIF WHERE :dist >= TAR_MIN_DIST AND :dist <= TAR_MAX_DIST");

            $_SESSION['trajets_trouves'] = [];
            foreach ($resultats as $index => $res) {
                $distTot = $res['distanceTotale'];
                
                // Calcul du prix pour cette option spécifique
                $stmtTar->execute([':dist' => $distTot]);
                $tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);
                $tarifBase = $tarif ? (float)$tarif['TAR_PRIX'] : 0;
                $prixFinal = (int)round($tarifBase * $pourcentagePaye / 100);

                $_SESSION['trajets_trouves'][$index] = [
                    'depart'   => $depart,
                    'arrivee'  => $arrivee,
                    'date'     => $dateVoyage,
                    'chemin'   => $res['chemin'],
                    'duree'    => $res['dureeTotale'],
                    'distance' => $distTot,
                    'prix'     => $prixFinal,
                    'points'   => calculerPoints($distTot),
                    'segments' => regrouperParLigne($res['chemin'])
                ];
            }
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

    <?php if (!empty($_SESSION['trajets_trouves']) && empty($erreur)) {
        $connAff = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        $noms = chargerNomsCommunes($connAff);
        $connAff = null;

        $meilleur = $_SESSION['trajets_trouves'][0];
        $valeurRef = ($critere === 'distance') ? $meilleur['distance'] : $meilleur['duree'];
        $prixRef = $meilleur['prix'];
        $unite = ($critere === 'distance') ? 'km' : 'min';
    ?>
        <h2 style="margin-top: 2rem;">Résultats : <?= htmlspecialchars($noms[$depart] ?? $depart) ?> → <?= htmlspecialchars($noms[$arrivee] ?? $arrivee) ?></h2>

        <?php foreach ($_SESSION['trajets_trouves'] as $index => $trajet) {
            $h = intdiv($trajet['duree'], 60);
            $m = $trajet['duree'] % 60;
            $segments = $trajet['segments'];

            if ($index === 0) {
                $diffTexte = "— Le plus " . ($critere === 'distance' ? 'court' : 'rapide');
                $diffPrixTexte = "";
            } else {
                $diff = ($critere === 'distance') ? ($trajet['distance'] - $valeurRef) : ($trajet['duree'] - $valeurRef);
                $diffPrix = $trajet['prix'] - $prixRef;
                
                $diffTexte = "(+ {$diff} {$unite})";
                $diffPrixTexte = ($diffPrix >= 0) ? "(+ {$diffPrix} €)" : "({$diffPrix} €)";
            }
        ?>
            <section class="carte-trajet" style="margin-bottom: 1.5rem;">
                <div class="trajet-entete">
                    <h3 style="margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <span>Option <?= $index + 1 ?> <span style="color:var(--rouge); font-size:0.9rem; margin-left:0.5rem;"><?= $diffTexte ?></span></span>
                        <span style="font-family:var(--font-display); font-size:1.4rem; color:var(--noir);"><?= $trajet['prix'] ?> € <span style="font-size:0.85rem; color:var(--rouge); font-weight:normal;"><?= $diffPrixTexte ?></span></span>
                    </h3>
                    <div class="trajet-resume">
                        <span class="resume-item"><strong><?= $h ?>h<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></strong> de trajet</span>
                        <span class="resume-item"><strong><?= $trajet['distance'] ?> km</strong></span>
                        <span class="resume-item"><strong><?= count($segments) - 1 ?></strong> correspondance(s)</span>
                        <span class="resume-item points"><strong>+<?= $trajet['points'] ?></strong> points</span>
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
                    <input type="hidden" name="index_trajet" value="<?= $index ?>">
                    <input type="submit" value="Réserver cette option">
                </form>
            </section>
        <?php } ?>
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