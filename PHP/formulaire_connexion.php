<?php session_start(); ?>
<!doctype html>
<html lang="fr">

<head>
	<meta charset="utf-8">
	<meta name="description" content="Viking Transports">
	<title>connexion Viking Transport</title>
	<link rel="stylesheet" href="../CSS/style.css">
</head>

<body>
	<?php
	include_once("../PHP/nav.php");
	?>
	<main>
		<h1>Connexion</h1>

		<p id="erreur">
			<?php
			if (isset($_SESSION["erreur"])) {
				echo htmlspecialchars($_SESSION["erreur"]);
				unset($_SESSION["erreur"]);
			}
			?>
		</p>

		<form action="../PHP/traitement_connexion.php" method="post">
			<div class="form-group">
				<label for="CLI_COURRIEL">Email</label>
				<input type="email" id="CLI_COURRIEL" name="CLI_COURRIEL" placeholder="votre@email.fr" required>
			</div>

			<div class="form-group">
				<label for="CLI_MDP">Mot de passe</label>
				<input type="password" id="CLI_MDP" name="CLI_MDP" placeholder="••••••••" required>
			</div>

			<p>
				<a class="link-rouge" href="#">Mot de passe oublié ?</a>
			</p>

			<button type="submit">Se connecter</button>
		</form>



		<p>Pas encore de compte ? <a class="link-rouge" href="../PHP/formulaire_inscription.php">Inscrivez-vous</a></p>
	</main>
	<?php
	include_once("../PHP/footer.php");
	?>
</body>

</html>

<p><a class="link-rouge">Mot de passe oublié ?</a></p>