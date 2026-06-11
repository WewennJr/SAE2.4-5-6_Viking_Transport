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
	<title>Conditions générales d'utilisation – Viking Transport</title>
	<link rel="stylesheet" href="../CSS/style.css">
</head>

<body>
	<?php include_once("../PHP/nav.php"); ?>

	<main>
		<h1>Conditions générales d'utilisation</h1>

		<section>
			<h2>1. Objet</h2>
			<p>Les présentes conditions générales d'utilisation régissent l'accès et l'utilisation du site Viking
				Transport. En accédant au site, vous acceptez sans réserve les présentes CGU.</p>
		</section>

		<section>
			<h2>2. Accès au site</h2>
			<p>Le site est accessible gratuitement à tout utilisateur disposant d'un accès à internet. Viking Transport
				se réserve le droit de modifier, suspendre ou interrompre l'accès au site à tout moment.</p>
		</section>

		<section>
			<h2>3. Création de compte</h2>
			<p>Pour accéder aux fonctionnalités réservées aux clients inscrits, vous devez créer un compte en
				fournissant des informations exactes et à jour. Vous êtes responsable de la confidentialité de vos
				identifiants de connexion.</p>
			<p>Un compte inactif depuis plus d'un an verra ses points de fidélité réinitialisés. Un compte inactif
				depuis plus de deux ans sera supprimé.</p>
		</section>

		<section>
			<h2>4. Réservations</h2>
			<p>Toute réservation effectuée sur le site vaut engagement ferme de paiement. Le prix est calculé en
				fonction de la distance parcourue et du tarif en vigueur.</p>
			<p>Les clients inscrits bénéficient de réductions selon leur niveau de fidélité et leurs points accumulés.
			</p>
		</section>

		<section>
			<h2>5. Programme de fidélité</h2>
			<p>Chaque voyage rapporte 1 point pour 10 km parcourus, avec un minimum d'1 point par voyage. Les points
				peuvent être utilisés pour obtenir des réductions sur les prochaines réservations.</p>
		</section>

		<section>
			<h2>6. Responsabilités</h2>
			<p>Viking Transport s'engage à assurer le service dans les meilleures conditions possibles. En cas de
				perturbation du service, Viking Transport ne pourra être tenu responsable des préjudices indirects subis
				par les utilisateurs.</p>
		</section>

		<section>
			<h2>7. Propriété intellectuelle</h2>
			<p>L'ensemble du contenu du site (textes, images, logo) est la propriété exclusive de Viking Transport et
				est protégé par les lois relatives à la propriété intellectuelle. Toute reproduction sans autorisation
				est interdite.</p>
		</section>

		<section>
			<h2>8. Droit applicable</h2>
			<p>Les présentes CGU sont soumises au droit français. En cas de litige, les tribunaux français seront seuls
				compétents.</p>
		</section>

		<p><em>Dernière mise à jour : juin 2026</em></p>
	</main>

	<?php include_once("../PHP/footer.php"); ?>
</body>

</html>