<style>
.footer-contenu {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 2rem;
  padding: 2.5rem 8%;
  width: 100%;
}

.footer-colonne h3 {
  font-family: 'Oswald', sans-serif;
  font-weight: 600;
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #f5f5f5;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #C0392B;
}

/* Surcharge des styles ul/li globaux de style.css */
.footer-colonne ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.footer-colonne ul li {
  font-size: 0.95rem;
  color: #e8e8e8;
  opacity: 0.8;
  margin-bottom: 0; /* annule le margin-bottom global */
}

.footer-colonne ul li a {
  color: #e8e8e8;
  opacity: 0.8;
  text-decoration: none;
  transition: color 0.2s, opacity 0.2s;
  display: inline; /* annule le display:flex global sur nav a */
  justify-content: unset;
  padding: 0;
  width: auto;
  text-align: left;
  letter-spacing: normal;
  text-transform: none;
  font-weight: normal;
  font-size: 0.95rem;
}

.footer-colonne ul li a:hover {
  color: #C0392B;
  opacity: 1;
  background-color: transparent;
  transform: none;
}

.footer-logo-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
}

.footer-logo-col img {
  max-width: 140px;
  opacity: 0.9;
}

@media (max-width: 640px) {
  .footer-contenu {
    grid-template-columns: 1fr;
    padding: 2rem 5%;
  }
}
</style>

<footer>
  <div class="footer-contenu">

    <div class="footer-colonne">
      <h3>Liens utiles</h3>
      <ul>
        <li><a href="../PHP/mentionslegales.php">Mentions légales</a></li>
        <li><a href="../PHP/donnees.php">Politique de confidentialité</a></li>
        <li><a href="../PHP/cgu.php">Conditions générales d'utilisation</a></li>
        <li><a href="../PHP/contact.php">Nous contacter</a></li>
        <li><a href="../PHP/formulaire_inscription.php">Créer un compte</a></li>
      </ul>
    </div>

    <div class="footer-colonne">
      <h3>Équipe T.UI.sto</h3>
      <ul>
        <li>BUCHAILLAT Enzo</li>
        <li>DUMÉNIL Gaëlig</li>
        <li>FONTAINE Louis</li>
        <li>GAUMONT Gabriel</li>
        <li>GUILLAUME Rafaël</li>
        <li>LEGROS Ewenn</li>
        <li>MATHIEU Alix</li>
      </ul>
    </div>

    <div class="footer-colonne footer-logo-col">
      <h3>T.UI.sto</h3>
      <img src="../images/tuisto.png" alt="Logo T.UI.sto">
    </div>

  </div>
</footer>