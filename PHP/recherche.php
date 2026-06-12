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
$heureDepart = $_POST['heure']  ?? '08:00';   // heure de départ souhaitée
$noms       = [];
$modeActif  = 'auto';   // 'auto' (5 trajets) ou 'manuel' (composition)

// ── Réservation MANUELLE : l'utilisateur a composé son trajet et clique Réserver ──
// On recalcule côté serveur (sécurité) puis on enchaîne sur le paiement.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserver_manuel') {
    $modeActif = 'manuel';
    $communesM = json_decode($_POST['communes'] ?? '[]', true);
    $communesM = is_array($communesM) ? array_values(array_filter($communesM, function ($c) {
        return $c !== '' && $c !== null;
    })) : [];
    $dateM  = $_POST['date']  ?? date('Y-m-d');
    $heureM = $_POST['heure'] ?? '08:00';

    if (count($communesM) >= 2) {
        $graphe = chargerGraphe($conn);
        $resM = composerTrajetManuel($graphe, $communesM, 'duree');

        if (!isset($resM['erreur'])) {
            $segsM = regrouperParLigne($resM['chemin']);
            $horairesM = calculerHorairesTrajet($conn, $segsM, hhmmEnMinutes($heureM));

            // Sécurité : on refuse un trajet dont les correspondances ne s'enchaînent pas
            if (empty($horairesM['realisable'])) {
                $erreur = "Ce trajet n'est pas réalisable à l'heure choisie (une correspondance n'a plus de car). Essayez une heure de départ plus matinale.";
            } else {
                $distM = $resM['distanceTotale'];
                $_SESSION['trajets_proposes'] = [
                    0 => [
                        'depart'   => $communesM[0],
                        'arrivee'  => $communesM[count($communesM) - 1],
                        'date'     => $dateM,
                        'chemin'   => $resM['chemin'],
                        'duree'    => $resM['dureeTotale'],
                        'distance' => $distM,
                        'points'   => calculerPoints($distM),
                        'prix'     => calculerTarif($conn, $distM),
                        'horaires' => $horairesM,
                    ]
                ];
                $conn = null;
                header('Location: paiement.php?trajet_choisi=0');
                exit;
            }
        } else {
            $erreur = $resM['erreur'];
        }
    } else {
        $erreur = "Choisissez au moins un départ et une arrivée.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === '') {
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
            $heureSouhaiteeMin = hhmmEnMinutes($heureDepart);
            $_SESSION['trajets_proposes'] = [];
            foreach ($trajets as $i => $t) {
                $dist = $t['distanceTotale'];
                $segs = regrouperParLigne($t['chemin']);
                $horaires = calculerHorairesTrajet($conn, $segs, $heureSouhaiteeMin);
                $_SESSION['trajets_proposes'][$i] = [
                    'depart'   => $depart,
                    'arrivee'  => $arrivee,
                    'date'     => $dateVoyage,
                    'chemin'   => $t['chemin'],
                    'duree'    => $t['dureeTotale'],
                    'distance' => $dist,
                    'points'   => calculerPoints($dist),
                    'prix'     => calculerTarif($conn, $dist),
                    'horaires' => $horaires,
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

    <!-- Bascule entre recherche automatique et composition manuelle -->
    <div class="bascule-mode">
        <button type="button" class="btn-mode actif" data-mode="auto" id="btn-mode-auto">Recherche automatique</button>
        <button type="button" class="btn-mode" data-mode="manuel" id="btn-mode-manuel">Composer mon trajet</button>
    </div>

    <!-- Plan du réseau (dépliable) + lien horaires -->
    <div class="bloc-plan">
        <div class="bloc-plan-actions">
            <button type="button" id="btn-plan" class="btn-plan" aria-expanded="false">
                <span class="btn-plan-icone">🗺</span>
                <span id="btn-plan-texte">Voir le plan du réseau</span>
                <span class="btn-plan-fleche" id="btn-plan-fleche">▾</span>
            </button>
            <a href="lignes.php" class="btn-horaires" target="_blank">
                <span class="btn-plan-icone">🕒</span>
                <span>Consulter les lignes et horaires</span>
            </a>
        </div>
        <div id="conteneur-plan" class="conteneur-plan">
            <img src="../images/plan-reseau.png" alt="Plan du réseau Viking Transport" class="image-plan">
        </div>
    </div>

    <!-- ════════ MODE AUTOMATIQUE ════════ -->
    <div id="bloc-auto">
    <section class="carte-recherche-form">
        <form action="recherche.php" method="post">
            <input type="hidden" name="action" value="">
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
                    <label for="heure">Heure de départ souhaitée</label>
                    <input type="time" name="heure" id="heure" value="<?= htmlspecialchars($heureDepart) ?>" required>
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
                $prix = $_SESSION['trajets_proposes'][$i]['prix'];
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
                        <div class="option-prix"><?= $prix ?> €</div>
                    </div>

                    <?php
                    $horaires = $_SESSION['trajets_proposes'][$i]['horaires'];
                    if (!$horaires['realisable']) { ?>
                        <p class="trajet-non-realisable">⚠ Ce trajet n'est pas réalisable à partir de <?= htmlspecialchars($heureDepart) ?> (dernière correspondance déjà passée). Essayez une heure plus matinale.</p>
                    <?php } ?>

                    <ol class="option-etapes-horaires">
                        <?php foreach ($horaires['etapes'] as $e) {
                            $s = $e['segment'];
                            $nomDep = $noms[$s['depart']]  ?? $s['depart'];
                            $nomArr = $noms[$s['arrivee']] ?? $s['arrivee'];
                        ?>
                            <?php if (!$e['realisable']) { ?>
                                <li class="etape-ko">
                                    <span class="mini-ligne">L<?= htmlspecialchars($s['ligne']) ?></span>
                                    <span class="etape-villes"><?= htmlspecialchars($nomDep) ?> → <?= htmlspecialchars($nomArr) ?></span>
                                    <span class="etape-ko-txt">Plus de car</span>
                                </li>
                            <?php } else { ?>
                                <?php if ($e['attente'] > 0) { ?>
                                    <li class="etape-attente">⏱ <?= $e['attente'] ?> min d'attente</li>
                                <?php } ?>
                                <li class="etape-ok">
                                    <span class="mini-ligne">L<?= htmlspecialchars($s['ligne']) ?></span>
                                    <span class="etape-villes"><?= htmlspecialchars($nomDep) ?> → <?= htmlspecialchars($nomArr) ?></span>
                                    <span class="etape-horaire"><?= minutesEnHhmm($e['depart']) ?> → <?= minutesEnHhmm($e['arrivee']) ?></span>
                                </li>
                            <?php } ?>
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
    </div><!-- /#bloc-auto -->

    <!-- ════════ MODE MANUEL ════════ -->
    <div id="bloc-manuel" style="display:none;">
        <p class="manuel-intro">Composez votre voyage : choisissez vos arrêts, ajoutez-en autant que vous voulez, et faites-les glisser pour les réordonner. Le prix et la durée s'adaptent en direct.</p>

        <section class="carte-recherche-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="date-m">Date du voyage</label>
                    <input type="text" name="date" id="date-m" value="<?= htmlspecialchars($dateVoyage) ?>" required readonly>
                </div>
                <div class="form-group">
                    <label for="heure-m">Heure de départ souhaitée</label>
                    <input type="time" name="heure" id="heure-m" value="<?= htmlspecialchars($heureDepart) ?>" required>
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

            <p id="avert-realisable" class="trajet-non-realisable" style="display:none;">⚠ Ce trajet n'est pas entièrement réalisable à l'heure choisie (une correspondance n'a plus de car). Essayez une heure de départ plus matinale.</p>

            <form id="form-reserver" action="recherche.php" method="post">
                <input type="hidden" name="action" value="reserver_manuel">
                <input type="hidden" name="communes" id="communes-input">
                <input type="hidden" name="date" id="date-input">
                <input type="hidden" name="heure" id="heure-input">
                <input type="submit" id="btn-reserver" value="Réserver ce trajet">
            </form>
        </section>

        <p id="message-erreur-trajet" class="message-erreur-trajet" style="display:none;"></p>
    </div><!-- /#bloc-manuel -->
</main>

<?php include_once "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script>
const MODE_INITIAL = "<?= $modeActif ?>";

flatpickr("#date", {
    locale: "fr",
    dateFormat: "Y-m-d",
    minDate: "today",
    defaultDate: "<?= htmlspecialchars($dateVoyage) ?>"
});

// Calendrier du mode manuel
flatpickr("#date-m", {
    locale: "fr",
    dateFormat: "Y-m-d",
    minDate: "today",
    defaultDate: "<?= htmlspecialchars($dateVoyage) ?>"
});

// ════════════════════════════════════════════════
// BASCULE Auto / Manuel
// ════════════════════════════════════════════════
const blocAuto = document.getElementById('bloc-auto');
const blocManuel = document.getElementById('bloc-manuel');
const btnModeAuto = document.getElementById('btn-mode-auto');
const btnModeManuel = document.getElementById('btn-mode-manuel');

function activerMode(mode) {
    if (mode === 'manuel') {
        blocAuto.style.display = 'none';
        blocManuel.style.display = 'block';
        btnModeManuel.classList.add('actif');
        btnModeAuto.classList.remove('actif');
    } else {
        blocAuto.style.display = 'block';
        blocManuel.style.display = 'none';
        btnModeAuto.classList.add('actif');
        btnModeManuel.classList.remove('actif');
    }
}
btnModeAuto.addEventListener('click', () => activerMode('auto'));
btnModeManuel.addEventListener('click', () => activerMode('manuel'));

// ════════════════════════════════════════════════
// Plan du réseau (dépliable)
// ════════════════════════════════════════════════
const btnPlan = document.getElementById('btn-plan');
const conteneurPlan = document.getElementById('conteneur-plan');
const btnPlanTexte = document.getElementById('btn-plan-texte');
const btnPlanFleche = document.getElementById('btn-plan-fleche');

btnPlan.addEventListener('click', function () {
    const ouvert = conteneurPlan.classList.toggle('ouvert');
    btnPlan.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    btnPlanTexte.textContent = ouvert ? 'Masquer le plan du réseau' : 'Voir le plan du réseau';
    btnPlanFleche.textContent = ouvert ? '▴' : '▾';
});

// ════════════════════════════════════════════════
// MODE MANUEL : composition du trajet
// ════════════════════════════════════════════════
const OPTIONS_COMMUNES = `<?php
    $opts = '<option value="">-- Choisir une commune --</option>';
    foreach ($communes as $c) {
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

// Crée une ligne déplaçable (départ / étape)
function creerLigne(estDepart) {
    const div = document.createElement('div');
    div.className = 'ligne-commune';
    div.draggable = false;   // activé seulement via la poignée

    // Poignée de glisser-déposer
    const poignee = document.createElement('span');
    poignee.className = 'drag-handle';
    poignee.innerHTML = '⠿';
    poignee.title = 'Glisser pour réordonner';
    // On active le drag uniquement quand on saisit la poignée
    poignee.addEventListener('mousedown', () => { div.draggable = true; });
    div.addEventListener('dragend', () => { div.draggable = false; });

    const label = document.createElement('span');
    label.className = 'ligne-label';

    const select = document.createElement('select');
    select.className = 'select-commune';
    select.innerHTML = OPTIONS_COMMUNES;
    select.addEventListener('change', recalculer);

    div.appendChild(poignee);
    div.appendChild(label);
    div.appendChild(select);

    // Bouton supprimer (toujours, mais on garde un minimum de 2 lignes)
    const btnSuppr = document.createElement('button');
    btnSuppr.type = 'button';
    btnSuppr.className = 'btn-supprimer';
    btnSuppr.innerHTML = '✕';
    btnSuppr.title = 'Retirer cet arrêt';
    btnSuppr.addEventListener('click', function () {
        if (liste.querySelectorAll('.ligne-commune').length <= 2) {
            return;   // on garde au moins départ + arrivée
        }
        div.remove();
        majLabels();
        recalculer();
    });
    div.appendChild(btnSuppr);

    return div;
}

// Remet les bons labels selon la position
function majLabels() {
    const lignes = liste.querySelectorAll('.ligne-commune');
    lignes.forEach((l, i) => {
        const lab = l.querySelector('.ligne-label');
        if (i === 0) lab.textContent = 'Départ';
        else if (i === lignes.length - 1) lab.textContent = 'Arrivée';
        else lab.textContent = 'Étape';
    });
}

function getCommunes() {
    return Array.from(liste.querySelectorAll('.select-commune')).map(s => s.value);
}

// ── Glisser-déposer (API native) ──
liste.addEventListener('dragover', function (e) {
    e.preventDefault();
    const enCours = liste.querySelector('.en-deplacement');
    if (!enCours) return;
    const apres = elementApres(e.clientY);
    if (apres == null) {
        liste.appendChild(enCours);
    } else {
        liste.insertBefore(enCours, apres);
    }
});

liste.addEventListener('dragstart', function (e) {
    const ligne = e.target.closest('.ligne-commune');
    if (ligne) ligne.classList.add('en-deplacement');
});

liste.addEventListener('dragend', function (e) {
    const ligne = e.target.closest('.ligne-commune');
    if (ligne) ligne.classList.remove('en-deplacement');
    majLabels();
    recalculer();
});

// Trouve l'élément après lequel insérer selon la position de la souris
function elementApres(y) {
    const elements = [...liste.querySelectorAll('.ligne-commune:not(.en-deplacement)')];
    return elements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// ── Calcul AJAX du trajet composé ──
async function recalculer() {
    const communes = getCommunes().filter(c => c !== '');
    msgErreur.style.display = 'none';

    if (communes.length < 2) {
        recap.style.display = 'none';
        return;
    }

    try {
        const heureChoisie = document.getElementById('heure-m').value || '08:00';
        const reponse = await fetch('calcul_trajet_manuel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ communes: communes, heure: heureChoisie })
        });
        const data = await reponse.json();

        if (!data.ok) {
            recap.style.display = 'none';
            msgErreur.textContent = '⚠ ' + data.message;
            msgErreur.style.display = 'block';
            return;
        }

        const h = Math.floor(data.dureeTotale / 60);
        const m = data.dureeTotale % 60;
        document.getElementById('recap-duree').textContent = h + 'h' + String(m).padStart(2, '0');
        document.getElementById('recap-distance').textContent = data.distanceTotale + ' km';
        document.getElementById('recap-points').textContent = data.points;
        document.getElementById('recap-prix').textContent = data.prix;

        // Avertissement si le trajet n'est pas réalisable à l'heure choisie
        const blocAvert = document.getElementById('avert-realisable');
        const btnReserver = document.getElementById('btn-reserver');
        if (data.realisable === false) {
            blocAvert.style.display = 'block';
            btnReserver.disabled = true;
            btnReserver.value = 'Trajet non réalisable à cette heure';
        } else {
            blocAvert.style.display = 'none';
            btnReserver.disabled = false;
            btnReserver.value = 'Réserver ce trajet';
        }

        let html = '';
        data.sousTrajets.forEach((st, idx) => {
            const hh = Math.floor(st.duree / 60);
            const mm = st.duree % 60;
            html += '<div class="sous-trajet">';
            html += '<div class="sous-trajet-titre"><span class="st-num">' + (idx + 1) + '</span> ' +
                    st.depart + ' → ' + st.arrivee +
                    ' <span class="st-info">' + hh + 'h' + String(mm).padStart(2, '0') + ' · ' + st.distance + ' km</span></div>';
            // On réutilise EXACTEMENT les mêmes classes que le mode auto
            html += '<ol class="option-etapes-horaires">';
            st.lignes.forEach(li => {
                if (li.realisable === false) {
                    html += '<li class="etape-ko">' +
                            '<span class="mini-ligne">L' + li.ligne + '</span>' +
                            '<span class="etape-villes">' + li.depart + ' → ' + li.arrivee + '</span>' +
                            '<span class="etape-ko-txt">Plus de car</span>' +
                            '</li>';
                } else if (li.heureDep && li.heureArr) {
                    // Segment avec horaire normal
                    if (li.attente && li.attente > 0) {
                        html += '<li class="etape-attente">⏱ ' + li.attente + ' min d\'attente</li>';
                    }
                    html += '<li class="etape-ok">' +
                            '<span class="mini-ligne">L' + li.ligne + '</span>' +
                            '<span class="etape-villes">' + li.depart + ' → ' + li.arrivee + '</span>' +
                            '<span class="etape-horaire">' + li.heureDep + ' → ' + li.heureArr + '</span>' +
                            '</li>';
                } else {
                    // Segment après un blocage : pas d'horaire disponible
                    html += '<li class="etape-ko">' +
                            '<span class="mini-ligne">L' + li.ligne + '</span>' +
                            '<span class="etape-villes">' + li.depart + ' → ' + li.arrivee + '</span>' +
                            '<span class="etape-ko-txt">Horaire indisponible</span>' +
                            '</li>';
                }
            });
            html += '</ol></div>';
        });
        recapDetail.innerHTML = html;
        recap.style.display = 'block';
    } catch (err) {
        recap.style.display = 'none';
        msgErreur.textContent = '⚠ Erreur lors du calcul du trajet : ' + err.message;
        msgErreur.style.display = 'block';
    }
}

// Au chargement du mode manuel : départ + arrivée
liste.appendChild(creerLigne(true));
liste.appendChild(creerLigne(false));
majLabels();

document.getElementById('btn-ajouter').addEventListener('click', function () {
    liste.appendChild(creerLigne(false));
    majLabels();
    recalculer();
});

// Recalcul quand on change l'heure de départ souhaitée
document.getElementById('heure-m').addEventListener('change', recalculer);

// Avant de réserver : on remplit les champs cachés
document.getElementById('form-reserver').addEventListener('submit', function () {
    document.getElementById('communes-input').value = JSON.stringify(getCommunes().filter(c => c !== ''));
    document.getElementById('date-input').value = document.getElementById('date-m').value;
    document.getElementById('heure-input').value = document.getElementById('heure-m').value;
});

// Mode actif au chargement (manuel si une réservation manuelle a échoué)
activerMode(MODE_INITIAL);
</script>

</body>
</html>