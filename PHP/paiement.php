<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

if (!isset($_SESSION['trajet_a_reserver'])) {
    header("Location: recherche.php");
    exit;
}
$trajet = $_SESSION['trajet_a_reserver'];

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
$noms = chargerNomsCommunes($conn);

$stmtTar = $conn->prepare("SELECT TAR_PRIX FROM VIK_TARIF WHERE :dist >= TAR_MIN_DIST AND :dist <= TAR_MAX_DIST");
$stmtTar->execute([':dist' => $trajet['distance']]);
$tarif = $stmtTar->fetch(PDO::FETCH_ASSOC);
$tarifBase = $tarif ? (float)$tarif['TAR_PRIX'] : 0;

$pourcentagePaye = 100;
$nomNiveau = "Non inscrit";
$pointsClient = 0;
$reductions = [];

if (isset($_SESSION['num_client'])) {
    // Récupération des infos niveau
    $stmtCli = $conn->prepare("
        SELECT c.CLI_NB_POINTS_EC, t.TYP_REDUC, t.TYP_NOM
        FROM VIK_CLIENT c JOIN VIK_TYPE_CLIENT t ON c.TYP_NUM = t.TYP_NUM
        WHERE c.CLI_NUM = :num
    ");
    $stmtCli->execute([':num' => $_SESSION['num_client']]);
    $niv = $stmtCli->fetch(PDO::FETCH_ASSOC);
    if ($niv) {
        $pourcentagePaye = (float)$niv['TYP_REDUC'];
        $nomNiveau = $niv['TYP_NOM'];
        $pointsClient = (int)$niv['CLI_NB_POINTS_EC'];
    }

    // Récupération des réductions possibles
    $stmtRed = $conn->query("SELECT NB_POINTS, VALEUR_REDUC FROM VIK_REDUCTION ORDER BY NB_POINTS ASC");
    if ($stmtRed) {
        $reductions = $stmtRed->fetchAll(PDO::FETCH_ASSOC);
    }
}
$prixFinal = (int)round($tarifBase * $pourcentagePaye / 100);

$conn = null;

$nomDep = $noms[$trajet['depart']]  ?? $trajet['depart'];
$nomArr = $noms[$trajet['arrivee']] ?? $trajet['arrivee'];

$estConnecte = isset($_SESSION['num_client']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/paiement.css">
    <style>
        .radio-reduction { display: block; margin-bottom: 0.5rem; cursor: pointer; }
        .radio-reduction input { margin-right: 0.5rem; }
        .radio-disabled { color: #999; cursor: not-allowed; }
    </style>
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <h1>Paiement</h1>

    <div class="paiement-grille">
        <section class="carte recap">
            <h2>Récapitulatif</h2>
            <p class="recap-trajet"><strong><?= htmlspecialchars($nomDep) ?></strong> → <strong><?= htmlspecialchars($nomArr) ?></strong></p>
            <p>Date : <?= htmlspecialchars($trajet['date']) ?></p>
            <p>Distance : <?= htmlspecialchars($trajet['distance']) ?> km</p>
            <p>Tarif de base : <?= $tarifBase ?> €</p>
            <p>Votre statut : <?= htmlspecialchars($nomNiveau) ?> (<?= $pourcentagePaye ?> %)</p>
            <hr>
            <p class="recap-total">Total à payer : <strong id="affichage-total"><?= $prixFinal ?> €</strong></p>
        </section>

        <section class="carte paiement-form">
            <h2>Coordonnées de paiement</h2>
            <form action="traitement_reservation.php" method="post" id="form-paiement" novalidate>

                <?php if (!$estConnecte) { ?>
                    <div class="bloc-form">
                        <p class="bloc-titre">Vos informations</p>
                        <div class="form-group">
                            <label for="v_prenom">Prénom</label>
                            <input type="text" id="v_prenom" name="v_prenom" placeholder="Jean" required>
                        </div>
                        <div class="form-group">
                            <label for="v_nom">Nom</label>
                            <input type="text" id="v_nom" name="v_nom" placeholder="Dupont" required>
                        </div>
                        <div class="form-group">
                            <label for="v_email">Email</label>
                            <input type="email" id="v_email" name="v_email" placeholder="jean.dupont@email.fr" required>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="bloc-form">
                        <p class="bloc-titre">Utiliser mes points (Solde : <?= $pointsClient ?> pts)</p>
                        <div class="form-group">
                            <label class="radio-reduction">
                                <input type="radio" name="reduction_points" value="0" data-remise="0" checked>
                                Ne pas utiliser de points
                            </label>
                            <?php foreach ($reductions as $red) { 
                                $ptsReq = (int)$red['NB_POINTS'];
                                $valReduc = (int)$red['VALEUR_REDUC'];
                                $disabled = ($pointsClient < $ptsReq) ? 'disabled' : '';
                                $classDis = ($pointsClient < $ptsReq) ? 'radio-disabled' : '';
                            ?>
                            <label class="radio-reduction <?= $classDis ?>">
                                <input type="radio" name="reduction_points" value="<?= $ptsReq ?>" data-remise="<?= $valReduc ?>" <?= $disabled ?>>
                                Utiliser <?= $ptsReq ?> points (- <?= $valReduc ?> €)
                            </label>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="bloc-form">
                    <p class="bloc-titre">Carte bancaire</p>
                    <div class="form-group">
                        <label for="titulaire">Titulaire de la carte</label>
                        <input type="text" id="titulaire" name="titulaire" placeholder="Jean Dupont" required>
                        <span class="erreur-champ" id="err-titulaire"></span>
                    </div>
                    <div class="form-group">
                        <label for="carte">Numéro de carte</label>
                        <input type="text" id="carte" name="carte" placeholder="4242 4242 4242 4242" maxlength="19" inputmode="numeric" required>
                        <span class="erreur-champ" id="err-carte"></span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiration">Expiration</label>
                            <input type="text" id="expiration" name="expiration" placeholder="MM/AA" maxlength="5" inputmode="numeric" required>
                            <span class="erreur-champ" id="err-expiration"></span>
                        </div>
                        <div class="form-group">
                            <label for="cvc">CVC</label>
                            <input type="text" id="cvc" name="cvc" placeholder="123" maxlength="3" inputmode="numeric" required>
                            <span class="erreur-champ" id="err-cvc"></span>
                        </div>
                    </div>
                </div>

                <input type="submit" id="btn-submit" value="Payer <?= $prixFinal ?> € et réserver">
            </form>
        </section>
    </div>
</main>

<?php include_once "footer.php"; ?>

<script>
// Gestion de la mise à jour du prix selon les points
const prixBase = <?= $prixFinal ?>;
const radios = document.querySelectorAll('input[name="reduction_points"]');
const affichageTotal = document.getElementById('affichage-total');
const btnSubmit = document.getElementById('btn-submit');

radios.forEach(radio => {
    radio.addEventListener('change', function() {
        let remise = parseInt(this.dataset.remise) || 0;
        let nouveauPrix = Math.max(0, prixBase - remise); // Le prix ne peut pas être négatif
        affichageTotal.textContent = nouveauPrix + ' €';
        btnSubmit.value = 'Payer ' + nouveauPrix + ' € et réserver';
    });
});

// Formatage carte
const form  = document.getElementById('form-paiement');
const carte = document.getElementById('carte');
const exp   = document.getElementById('expiration');
const cvc   = document.getElementById('cvc');

carte.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 16);
    this.value = v.replace(/(.{4})/g, '$1 ').trim();
});

exp.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) {
        this.value = v.substring(0, 2) + '/' + v.substring(2);
    } else {
        this.value = v;
    }
});

cvc.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').substring(0, 3);
});

form.addEventListener('submit', function (e) {
    let ok = true;
    document.querySelectorAll('.erreur-champ').forEach(s => s.textContent = '');

    const titulaire = document.getElementById('titulaire');
    if (titulaire.value.trim() === '') {
        document.getElementById('err-titulaire').textContent = "Veuillez indiquer le titulaire.";
        ok = false;
    }

    const numCarte = carte.value.replace(/\s/g, '');
    if (!/^\d{16}$/.test(numCarte)) {
        document.getElementById('err-carte').textContent = "Le numéro doit comporter 16 chiffres.";
        ok = false;
    }

    const m = exp.value.match(/^(\d{2})\/(\d{2})$/);
    if (!m) {
        document.getElementById('err-expiration').textContent = "Format attendu : MM/AA.";
        ok = false;
    } else {
        const mois = parseInt(m[1], 10);
        const annee = 2000 + parseInt(m[2], 10);
        if (mois < 1 || mois > 12) {
            document.getElementById('err-expiration').textContent = "Mois invalide.";
            ok = false;
        } else {
            const finMois = new Date(annee, mois, 0, 23, 59, 59);
            if (finMois < new Date()) {
                document.getElementById('err-expiration').textContent = "Carte expirée.";
                ok = false;
            }
        }
    }

    if (!/^\d{3}$/.test(cvc.value)) {
        document.getElementById('err-cvc').textContent = "Le CVC doit comporter 3 chiffres.";
        ok = false;
    }

    if (!ok) e.preventDefault();
});
</script>

</body>
</html>