<?php
session_start();      // récupère la session en cours
session_unset();      // on vide toutes les variables ($_SESSION devient vide)
session_destroy();    // on supprime la session côté serveur
header("Location: ../PHP/index.php");  // on renvoie vers l'accueil
exit;
?>