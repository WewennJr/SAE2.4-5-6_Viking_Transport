<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="Viking Transports">
	<meta author="T.UI.sto">
	<title>Politique de confidentialité – Viking Transport</title>
	<link rel="stylesheet" href="../CSS/style.css">
</head>

<body>
	<?php include_once("../PHP/nav.php"); ?>

	<main>
		<h1>Politique de confidentialité</h1>

		<section>
			<h2>1. Responsable du traitement</h2>
			<p>Viking Transport, réseau de cars normands, est responsable du traitement de vos données personnelles
				collectées
				via ce site.</p>
		</section>

		<section>
			<h2>2. Données collectées</h2>
			<p>Nous collectons les données suivantes lors de la création de votre compte ou d'une réservation :</p>
			<ul>
				<li>Nom et prénom</li>
				<li>Adresse e-mail</li>
				<li>Numéro de téléphone</li>
				<li>Ville et département de résidence</li>
				<li>Historique des voyages et des points de fidélité</li>
			</ul>
		</section>

		<section>
			<h2>3. Finalité du traitement</h2>
			<p>Vos données sont utilisées exclusivement pour :</p>
			<ul>
				<li>La gestion de votre compte client</li>
				<li>Le traitement de vos réservations</li>
				<li>La gestion du programme de fidélité</li>
				<li>L'envoi de communications promotionnelles (avec votre consentement)</li>
			</ul>
		</section>

		<section>
			<h2>4. Durée de conservation</h2>
			<p>Vos données sont conservées tant que votre compte est actif. Un compte sans connexion depuis plus de deux
				ans
				est automatiquement supprimé.</p>
		</section>

		<section>
			<h2>5. Vos droits</h2>
			<p>Conformément au RGPD, vous disposez des droits suivants :</p>
			<ul>
				<li>Droit d'accès à vos données</li>
				<li>Droit de rectification</li>
				<li>Droit à l'effacement</li>
				<li>Droit à la portabilité</li>
				<li>Droit d'opposition</li>
			</ul>
			<p>Pour exercer ces droits, contactez-nous à : <a
					href="mailto:sae.tuisto@gmail.com">sae.tuisto@gmail.com</a></p>
		</section>

		<section>
			<h2>6. Cookies</h2>
			<p>Ce site utilise uniquement des cookies techniques nécessaires à son bon fonctionnement (session de
				connexion).
				Aucun cookie publicitaire n'est utilisé.</p>
		</section>

		<p><em>Dernière mise à jour : juin 2026</em></p>
	</main>

	<?php include_once("../PHP/footer.php"); ?>
</body>

</html>