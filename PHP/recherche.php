<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$stmtCom = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
$communes = $stmtCom->fetchAll(PDO::FETCH_ASSOC);

$trajets    = [];
$erreur     = "";
$depart     = $_POST['depart']  ?? '';
$arrivee    = $_POST['arrivee'] ?? '';
$dateVoyage = $_POST['date']    ?? date('Y-m-d');
$critere    = $_POST['critere'] ?? 'duree';
$noms       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($depart) || empty($arrivee)) {
        $erreur = "Veuillez choisir une commune de départ et d'arrivée.";
    } elseif ($depart === $arrivee) {
        $erreur = "Le départ et l'arrivée doivent être différents.";
    } else {
        $graphe  = chargerGraphe($conn);
        $noms    = chargerNomsCommunes($conn);
        $trajets = trouverKTrajets($graphe, $depart, $arrivee, 5, $critere);

        if (empty($trajets)) {
            $erreur = "Aucun trajet trouvé entre ces deux communes.";
        } else {
            // On mémorise les trajets proposés en session pour la réservation
            $_SESSION['trajets_proposes'] = [];
            foreach ($trajets as $i => $t) {
                $dist = $t['distanceTotale'];
                $_SESSION['trajets_proposes'][$i] = [
                    'depart'   => $depart,
                    'arrivee'  => $arrivee,
                    'date'     => $dateVoyage,
                    'chemin'   => $t['chemin'],
                    'duree'    => $t['dureeTotale'],
                    'distance' => $dist,
                    'points'   => calculerPoints($dist),
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
                    <label>Trier par</label>
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

            <input type="submit" value="Rechercher les trajets">
        </form>
    </section>

    <?php if (!empty($erreur)) { ?>
        <p id="erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php } ?>

    <?php if (!empty($trajets) && empty($erreur)) {
        $libelleCritere = ($critere === 'distance') ? 'du plus court au plus long' : 'du plus rapide au plus lent';
    ?>
        <div class="resultats-entete">
            <h2><?= htmlspecialchars($noms[$depart] ?? $depart) ?> → <?= htmlspecialchars($noms[$arrivee] ?? $arrivee) ?></h2>
            <p><?= count($trajets) ?> trajet(s) trouvé(s), triés <?= $libelleCritere ?></p>
        </div>

        <div class="liste-trajets">
            <?php
            // Valeur de référence = le meilleur trajet (le premier, selon le critère)
            $refDuree    = $trajets[0]['dureeTotale'];
            $refDistance = $trajets[0]['distanceTotale'];

            foreach ($trajets as $i => $t) {
                $h = intdiv($t['dureeTotale'], 60);
                $m = $t['dureeTotale'] % 60;
                $segments = regrouperParLigne($t['chemin']);
                $points = calculerPoints($t['distanceTotale']);
                $estMeilleur = ($i === 0);

                // Calcul de l'écart par rapport au meilleur, selon le critère
                if ($critere === 'distance') {
                    $ecart = $t['distanceTotale'] - $refDistance;
                    $texteEcart = ($ecart == 0) ? 'Le plus court' : '+' . $ecart . ' km';
                } else {
                    $ecart = $t['dureeTotale'] - $refDuree;
                    $texteEcart = ($ecart == 0) ? 'Le plus rapide' : '+' . $ecart . ' min';
                }
            ?>
                <article class="carte-option <?= $estMeilleur ? 'meilleure' : '' ?>">
                    <?php if ($estMeilleur) { ?>
                        <span class="badge-meilleur"><?= ($critere === 'distance') ? 'Le plus court' : 'Le plus rapide' ?></span>
                    <?php } else { ?>
                        <span class="badge-ecart"><?= htmlspecialchars($texteEcart) ?></span>
                    <?php } ?>

                    <div class="option-resume">
                        <div class="option-chiffres">
                            <?php if ($critere === 'distance') { ?>
                                <span class="option-duree"><?= $t['distanceTotale'] ?> km</span>
                                <span class="option-detail"><?= $h ?>h<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?> · <?= count($segments) - 1 ?> corresp. · +<?= $points ?> pts</span>
                            <?php } else { ?>
                                <span class="option-duree"><?= $h ?>h<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="option-detail"><?= $t['distanceTotale'] ?> km · <?= count($segments) - 1 ?> corresp. · +<?= $points ?> pts</span>
                            <?php } ?>
                        </div>
                    </div>

                    <ol class="option-etapes">
                        <?php foreach ($segments as $s) {
                            $nomDep = $noms[$s['depart']]  ?? $s['depart'];
                            $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];
                        ?>
                            <li>
                                <span class="mini-ligne">L<?= htmlspecialchars($s['ligne']) ?></span>
                                <span class="mini-trajet"><?= htmlspecialchars($nomDep) ?> → <?= htmlspecialchars($nomArr) ?></span>
                            </li>
                        <?php } ?>
                    </ol>

                    <form action="paiement.php" method="post" class="form-choisir">
                        <input type="hidden" name="trajet_choisi" value="<?= $i ?>">
                        <input type="submit" value="Choisir ce trajet">
                    </form>
                </article>
            <?php } ?>
        </div>
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