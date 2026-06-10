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
}

.footer-colonne ul li a {
    color: #e8e8e8;
    opacity: 0.8;
    text-decoration: none;
    transition: color 0.2s, opacity 0.2s;
}

.footer-colonne ul li a:hover {
    color: #C0392B;
    opacity: 1;
}

.footer-logo-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
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
                <li><a href="mentionslegales.html">Mentions légales</a></li>
                <li><a href="donnees.html">Politique de confidentialité</a></li>
                <li><a href="cgu.html">Conditions générales d'utilisation</a></li>
                <li><a href="contact.html">Nous contacter</a></li>
                <li><a href="formulaire_inscription.php">Créer un compte</a></li>
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
            <h3>Notre équipe</h3>
            <img src="../images/tuisto.png" alt="Logo T.UI.sto">
        </div>
    </div>
</footer>