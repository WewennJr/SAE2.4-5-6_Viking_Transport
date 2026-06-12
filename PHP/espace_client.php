<?php
session_start();

if (!isset($_SESSION["num_client"])) {
    header("Location: formulaire_connexion.php");
    exit;
}

include_once "pdo_agile.php";
include_once "param_connexion_etu.php";

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

// --- Informations du client ---
$stmt = $conn->prepare("SELECT * FROM VIK_CLIENT WHERE CLI_NUM = :num");
$stmt->execute([':num' => $_SESSION['num_client']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

$nom      = $client['CLI_NOM'];
$prenom   = $client['CLI_PRENOM'];
$courriel = $client['CLI_COURRIEL'];
$ville    = $client['CLI_VILLE'];
$tel      = $client['CLI_TELEPHONE'];
$dep      = $client['DEP_NUM'];
$points   = $client['CLI_NB_POINTS_EC'];
$grade    = $client['CLI_GRADE'];

// --- Réservations du client (en-tête simple) ---
$stmtResa = $conn->prepare("
    SELECT RES_NUM, RES_DATE, RES_PRIX_TOT, RES_NB_POINTS
    FROM VIK_RESERVATION
    WHERE CLI_NUM = :num
    ORDER BY RES_DATE ASC
");
$stmtResa->execute([':num' => $_SESSION['num_client']]);
$reservations = $stmtResa->fetchAll(PDO::FETCH_ASSOC);

// --- Pour chaque réservation, on récupère le départ et l'arrivée du voyage ---
// Le vrai départ = la commune de départ qui n'est l'arrivée d'aucune autre étape
// Le vrai arrivée = la commune d'arrivée qui n'est le départ d'aucune autre étape
$stmtDA = $conn->prepare("
    SELECT
        (SELECT c.COM_NOM FROM VIK_ETAPE e
         JOIN VIK_COMMUNE c ON e.COM_CODE_INSEE_DEPART = c.COM_CODE_INSEE
         WHERE e.CLI_NUM = :cli1 AND e.RES_NUM = :res1
           AND e.COM_CODE_INSEE_DEPART NOT IN (
               SELECT e2.COM_CODE_INSEE_ARRIVEE FROM VIK_ETAPE e2
               WHERE e2.CLI_NUM = :cli2 AND e2.RES_NUM = :res2)
           AND ROWNUM = 1) AS DEPART,
        (SELECT c.COM_NOM FROM VIK_ETAPE e
         JOIN VIK_COMMUNE c ON e.COM_CODE_INSEE_ARRIVEE = c.COM_CODE_INSEE
         WHERE e.CLI_NUM = :cli3 AND e.RES_NUM = :res3
           AND e.COM_CODE_INSEE_ARRIVEE NOT IN (
               SELECT e2.COM_CODE_INSEE_DEPART FROM VIK_ETAPE e2
               WHERE e2.CLI_NUM = :cli4 AND e2.RES_NUM = :res4)
           AND ROWNUM = 1) AS ARRIVEE
    FROM DUAL
");

foreach ($reservations as $i => $resa) {
    $stmtDA->execute([
        ':cli1' => $_SESSION['num_client'], ':res1' => $resa['RES_NUM'],
        ':cli2' => $_SESSION['num_client'], ':res2' => $resa['RES_NUM'],
        ':cli3' => $_SESSION['num_client'], ':res3' => $resa['RES_NUM'],
        ':cli4' => $_SESSION['num_client'], ':res4' => $resa['RES_NUM'],
    ]);
    $da = $stmtDA->fetch(PDO::FETCH_ASSOC);
    $reservations[$i]['DEPART']  = $da['DEPART']  ?? '-';
    $reservations[$i]['ARRIVEE'] = $da['ARRIVEE'] ?? '-';
}

$conn = null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="description" content="Viking Transport">
    <title>Mon compte</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/compte.css">
</head>
<body>

<?php include_once "nav.php"; ?>

<main>
    <div class="compte-entete">
        <h1>Mon espace client</h1>
        <p class="sous-titre">Bonjour <?= htmlspecialchars($prenom) ?>, ravis de vous revoir</p>

        <?php if ($grade === 'administrateur') { ?>
            <a href="admin.php" class="lien-admin">⚙ Accéder au tableau de bord admin</a>
        <?php } ?>
    </div>

    <div class="compte-grille">
        <!-- Carte infos personnelles -->
        <section class="carte">
            <h2>Mes informations</h2>
            <ul class="info-liste">
                <li><span class="libelle">Nom</span><span class="valeur"><?= htmlspecialchars($nom) ?></span></li>
                <li><span class="libelle">Prénom</span><span class="valeur"><?= htmlspecialchars($prenom) ?></span></li>
                <li><span class="libelle">Email</span><span class="valeur"><?= htmlspecialchars($courriel) ?></span></li>
                <li><span class="libelle">Téléphone</span><span class="valeur"><?= htmlspecialchars($tel) ?></span></li>
                <li><span class="libelle">Ville</span><span class="valeur"><?= htmlspecialchars($ville) ?></span></li>
            </ul>
            <a href="modifier_profil.php" class="lien-modifier">Modifier mes informations</a>
        </section>

        <!-- Carte points fidélité -->
        <section class="carte-points">
            <h2>Points de fidélité</h2>
            <div class="points-valeur"><?= htmlspecialchars($points) ?></div>
            <div class="points-label">points cumulés</div>
        </section>
    </div>

    <!-- Tableau réservations -->
    <section class="carte-reservations">
        <h2>Mes réservations</h2>
        <table class="table-reservations">
            <thead>
                <tr>
                    <th>N° réservation</th>
                    <th>Date</th>
                    <th>Départ</th>
                    <th>Arrivée</th>
                    <th>Prix</th>
                    <th>Billet</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reservations) > 0) { ?>
                    <?php foreach ($reservations as $resa) { ?>
                        <tr>
                            <td><?= htmlspecialchars($resa['RES_NUM']) ?></td>
                            <td><?= htmlspecialchars($resa['RES_DATE']) ?></td>
                            <td><?= htmlspecialchars($resa['DEPART']) ?></td>
                            <td><?= htmlspecialchars($resa['ARRIVEE']) ?></td>
                            <td><?= htmlspecialchars($resa['RES_PRIX_TOT']) ?> €</td>
                            <td><a href="billet.php?res=<?= htmlspecialchars($resa['RES_NUM']) ?>" class="lien-billet">🎫 Télécharger</a></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="6" class="aucune-resa">Vous n'avez aucune réservation pour le moment.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
    <!-- Carte thème -->
    <section class="carte carte-theme">
        <h2>Apparence</h2>
        <div class="theme-options">
            <button id="btn-sombre" class="theme-option-btn" type="button">
                <span class="theme-option-icone">☾</span>
                <span class="theme-option-label">Mode sombre</span>
            </button>
            <button id="btn-dalton" class="theme-option-btn" type="button">
                <span class="theme-option-icone">◎</span>
                <span class="theme-option-label">Mode daltonien</span>
            </button>
        </div>
        <p class="theme-note">Vos préférences sont sauvegardées sur cet appareil.</p>
    </section>

</main>

<script>
(function () {
    var html      = document.documentElement;
    var btnSombre = document.getElementById('btn-sombre');
    var btnDalton = document.getElementById('btn-dalton');

    /* Applique (ou supprime) un thème et sauvegarde dans localStorage */
    function appliquerTheme(theme) {
        if (theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('vk-theme', theme);
        } else {
            html.removeAttribute('data-theme');
            localStorage.removeItem('vk-theme');
        }
        mettreAJourBoutons(theme);
    }

    /* Met les boutons dans le bon état visuel selon le thème actif */
    function mettreAJourBoutons(theme) {
        var sombreActif = (theme === 'sombre');
        btnSombre.classList.toggle('actif', sombreActif);
        btnSombre.querySelector('.theme-option-icone').textContent = sombreActif ? '☀' : '☾';

        var daltonActif = (theme === 'daltonien');
        btnDalton.classList.toggle('actif', daltonActif);
        btnDalton.querySelector('.theme-option-icone').textContent = daltonActif ? '◉' : '◎';
    }

    /* Clic mode sombre : bascule entre sombre et aucun thème */
    btnSombre.addEventListener('click', function () {
        var actuel = html.getAttribute('data-theme');
        appliquerTheme(actuel === 'sombre' ? null : 'sombre');
    });

    /* Clic mode daltonien : bascule entre daltonien et aucun thème */
    btnDalton.addEventListener('click', function () {
        var actuel = html.getAttribute('data-theme');
        appliquerTheme(actuel === 'daltonien' ? null : 'daltonien');
    });

    /* Au chargement de la page : lire localStorage et mettre les boutons à jour.
       nav.php a déjà appliqué data-theme sur <html> avant le rendu,
       donc on se base sur l'attribut réel du DOM. */
    mettreAJourBoutons(html.getAttribute('data-theme') || localStorage.getItem('vk-theme') || null);
})();
</script>

<?php include_once "footer.php"; ?>

</body>
</html>