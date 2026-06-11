<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="Viking Transports">
	<meta author="T.UI.sto">
	<title>Contact – Viking Transport</title>
	<link rel="stylesheet" href="../CSS/style.css">
</head>

<body>
	<?php
	include_once("../PHP/nav.php");
	?>

	<main>
		<article>
			<h2>Contacter Viking Transport</h2>
			<p>Une question sur une réservation, un voyage ou nos services ? Remplissez le formulaire ci-dessous, nous
				vous répondons sous 48h ouvrées.</p>

      <form  method="post">
        <label for="prenom">Prénom *</label>
        <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required>

				<label for="nom">Nom *</label>
				<input type="text" id="nom" name="nom" placeholder="Votre nom" required>

				<label for="email">Email *</label>
				<input type="email" id="email" name="email" placeholder="votre@email.fr" required>

				<label for="objet">Objet *</label>
				<select id="objet" name="objet" required>
					<option value="" disabled selected>-- Choisissez un objet --</option>
					<option value="reservation">Réservation</option>
					<option value="reclamation">Réclamation</option>
					<option value="information">Demande d'information</option>
					<option value="fidelite">Programme de fidélité</option>
					<option value="autre">Autre</option>
				</select>

				<label for="message">Message *</label>
				<textarea id="message" name="message" rows="6" placeholder="Votre message..." required></textarea>

				<p><em>* Champs obligatoires</em></p>

				<button type="submit">Envoyer</button>
			</form>
		</article>

		<article>
			<h3>Contacter T.UI.sto</h3>
			<p>Pour toute question technique relative au site :</p>
			<p><a href="mailto:sae.tuisto@gmail.com">sae.tuisto@gmail.com</a></p>
		</article>
	</main>


	<?php
	include_once("../PHP/footer.php");
	?>
</body>

</html>