<?php
session_start();
include_once "pdo_agile.php";
include_once "param_connexion_etu.php";
include_once "moteur_trajet.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

$stmtCom = $conn->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
$communesListe = $stmtCom->fetchAll(PDO::FETCH_ASSOC);

$erreur = "";

// ── Traitement du clic "Réserver" : on recalcule côté serveur (sécurité) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserver') {
    $communes = json_decode($_POST['communes'] ?? '[]', true);
    $communes = is_array($communes) ? array_values(array_filter($communes, function ($c) {
        return $c !== '' && $c !== null;
    })) : [];
    $date  = $_POST['date']  ?? date('Y-m-d');
    $heure = $_POST['heure'] ?? '08:00';

    if (count($communes) >= 2) {
        $graphe = chargerGraphe($conn);
        $res = composerTrajetManuel($graphe, $communes, 'duree');

        if (!isset($res['erreur'])) {
            $segs = regrouperParLigne($res['chemin']);
            $horaires = calculerHorairesTrajet($conn, $segs, hhmmEnMinutes($heure));
            $dist = $res['distanceTotale'];

            // On stocke au même format que la recherche auto, pour réutiliser paiement.php
            $_SESSION['trajets_proposes'] = [
                0 => [
                    'depart'   => $communes[0],
                    'arrivee'  => $communes[count($communes) - 1],
                    'date'     => $date,
                    'chemin'   => $res['chemin'],
                    'duree'    => $res['dureeTotale'],
                    'distance' => $dist,
                    'points'   => calculerPoints($dist),
                    'prix'     => calculerTarif($conn, $dist),
                    'horaires' => $horaires,
                ]
            ];
            $conn = null;
            header('Location: paiement.php?trajet_choisi=0');
            exit;
        } else {
            $erreur = $res['erreur'];
        }
    } else {
        $erreur = "Choisissez au moins un départ et une arrivée.";
    }
}

$conn = null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Composer mon trajet - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/recherche.css">
    <link rel="stylesheet" href="../CSS/manuel.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../CSS/flatpickr-viking.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <h1>Composer mon trajet</h1>
    <p class="manuel-intro">Choisissez votre départ et votre arrivée, puis ajoutez autant d'étapes que vous voulez. Le prix et la durée se mettent à jour automatiquement.</p>

    <?php if (!empty($erreur)) { ?>
        <p id="erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php } ?>

    <section class="carte-recherche-form">
        <div class="form-row">
            <div class="form-group">
                <label for="date">Date du voyage</label>
                <input type="text" name="date" id="date" value="<?= date('Y-m-d') ?>" required readonly>
            </div>
            <div class="form-group">
                <label for="heure">Heure de départ souhaitée</label>
                <input type="time" name="heure" id="heure" value="08:00" required>
            </div>
        </div>

        <div id="liste-communes"></div>

        <button type="button" id="btn-ajouter" class="btn-ajouter">+ Ajouter une étape</button>
    </section>

    <!-- Récapitulatif dynamique -->
    <section id="recap-manuel" class="recap-manuel" style="display:none;">
        <div class="recap-entete">
            <h2>Votre trajet composé</h2>
            <div class="recap-chiffres">
                <span><strong id="recap-duree">—</strong> de trajet</span>
                <span><strong id="recap-distance">—</strong></span>
                <span class="recap-points">+<strong id="recap-points">—</strong> pts</span>
                <span class="recap-prix"><strong id="recap-prix">—</strong> €</span>
            </div>
        </div>
        <div id="recap-detail"></div>

        <form id="form-reserver" action="reservation_manuelle.php" method="post">
            <input type="hidden" name="action" value="reserver">
            <input type="hidden" name="communes" id="communes-input">
            <input type="hidden" name="date" id="date-input">
            <input type="hidden" name="heure" id="heure-input">
            <input type="submit" id="btn-reserver" value="Réserver ce trajet">
        </form>
    </section>

    <p id="message-erreur-trajet" class="message-erreur-trajet" style="display:none;"></p>
</main>

<?php include_once "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script>
// Options de communes (générées une fois en PHP)
const OPTIONS_COMMUNES = `<?php
    $opts = '<option value="">-- Choisir une commune --</option>';
    foreach ($communesListe as $c) {
        $code = trim($c['COM_CODE_INSEE']);
        $nom  = htmlspecialchars($c['COM_NOM'], ENT_QUOTES);
        $opts .= "<option value=\"$code\">$nom</option>";
    }
    echo $opts;
?>`;

const liste = document.getElementById('liste-communes');
const recap = document.getElementById('recap-manuel');
const recapDetail = document.getElementById('recap-detail');
const msgErreur = document.getElementById('message-erreur-trajet');

// Crée une ligne (départ / étape / arrivée)
function creerLigne(estDepart) {
    const div = document.createElement('div');
    div.className = 'ligne-commune';

    const label = document.createElement('label');
    label.textContent = estDepart ? 'Départ' : 'Puis vers';
    label.className = 'ligne-label';

    const select = document.createElement('select');
    select.className = 'select-commune';
    select.innerHTML = OPTIONS_COMMUNES;
    select.addEventListener('change', recalculer);

    div.appendChild(label);
    div.appendChild(select);

    // Bouton supprimer (sauf pour le départ)
    if (!estDepart) {
        const btnSuppr = document.createElement('button');
        btnSuppr.type = 'button';
        btnSuppr.className = 'btn-supprimer';
        btnSuppr.innerHTML = '✕';
        btnSuppr.title = 'Retirer cette étape';
        btnSuppr.addEventListener('click', function () {
            div.remove();
            majLabels();
            recalculer();
        });
        div.appendChild(btnSuppr);
    }

    return div;
}

// Remet les bons labels (Départ pour le 1er, "Puis vers" pour les autres)
function majLabels() {
    const lignes = liste.querySelectorAll('.ligne-commune');
    lignes.forEach((l, i) => {
        const lab = l.querySelector('.ligne-label');
        lab.textContent = (i === 0) ? 'Départ' : 'Puis vers';
    });
}

// Récupère la liste ordonnée des codes communes choisis
function getCommunes() {
    const selects = liste.querySelectorAll('.select-commune');
    return Array.from(selects).map(s => s.value);
}

// Appelle l'endpoint AJAX et met à jour l'affichage
async function recalculer() {
    const communes = getCommunes().filter(c => c !== '');
    msgErreur.style.display = 'none';

    if (communes.length < 2) {
        recap.style.display = 'none';
        return;
    }

    try {
        const reponse = await fetch('calcul_trajet_manuel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ communes: communes })
        });
        const data = await reponse.json();

        if (!data.ok) {
            recap.style.display = 'none';
            msgErreur.textContent = '⚠ ' + data.message;
            msgErreur.style.display = 'block';
            return;
        }

        // Mise à jour des totaux
        const h = Math.floor(data.dureeTotale / 60);
        const m = data.dureeTotale % 60;
        document.getElementById('recap-duree').textContent = h + 'h' + String(m).padStart(2, '0');
        document.getElementById('recap-distance').textContent = data.distanceTotale + ' km';
        document.getElementById('recap-points').textContent = data.points;
        document.getElementById('recap-prix').textContent = data.prix;

        // Détail des sous-trajets
        let html = '';
        data.sousTrajets.forEach((st, idx) => {
            const hh = Math.floor(st.duree / 60);
            const mm = st.duree % 60;
            html += '<div class="sous-trajet">';
            html += '<div class="sous-trajet-titre"><span class="st-num">' + (idx + 1) + '</span> ' +
                    st.depart + ' → ' + st.arrivee +
                    ' <span class="st-info">' + hh + 'h' + String(mm).padStart(2, '0') + ' · ' + st.distance + ' km</span></div>';
            html += '<ol class="st-lignes">';
            st.lignes.forEach(li => {
                html += '<li><span class="mini-ligne">L' + li.ligne + '</span> ' +
                        li.depart + ' → ' + li.arrivee + '</li>';
            });
            html += '</ol></div>';
        });
        recapDetail.innerHTML = html;

        recap.style.display = 'block';
    } catch (err) {
        recap.style.display = 'none';
        msgErreur.textContent = '⚠ Erreur lors du calcul du trajet.';
        msgErreur.style.display = 'block';
    }
}

// Au chargement : départ + arrivée
liste.appendChild(creerLigne(true));
liste.appendChild(creerLigne(false));

// Bouton "Ajouter une étape"
document.getElementById('btn-ajouter').addEventListener('click', function () {
    liste.appendChild(creerLigne(false));
    majLabels();
    recalculer();
});

// Avant de réserver : on remplit les champs cachés
document.getElementById('form-reserver').addEventListener('submit', function () {
    document.getElementById('communes-input').value = JSON.stringify(getCommunes().filter(c => c !== ''));
    document.getElementById('date-input').value = document.getElementById('date').value;
    document.getElementById('heure-input').value = document.getElementById('heure').value;
});

// Calendrier
flatpickr("#date", {
    locale: "fr",
    dateFormat: "Y-m-d",
    minDate: "today",
    defaultDate: "<?= date('Y-m-d') ?>"
});
</script>

</body>
</html>
