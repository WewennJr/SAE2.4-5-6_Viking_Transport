<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lignes – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <?php 
    include_once("../PHP/nav.php");
  ?>
    <main class="main-lignes">
        <h1>Lignes du réseau</h1>
        <p class="lignes-sous-titre">19 lignes de transport régional en Normandie</p>

        <a href="recherche.php" class="btn-reserver-lignes">
            <span class="btn-reserver-icone">🔍</span>
            <span>Rechercher et réserver un trajet</span>
        </a>

        <div class="lignes-grille">

            <a href="horaire_ligne.php?lig=1A" class="ligne-carte" style="--couleur: #e63946; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 1</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">La Hague</span>
                    <div class="ligne-etapes">
                        <span>Cherbourg-en-Cotentin</span>
                        <span>Valognes</span>
                        <span>Taillepied</span>
                        <span>Carentan-les-Marais</span>
                        <span>Lison</span>
                        <span>Bayeux</span>
                    </div>
                    <span class="ligne-ville arrivee">Caen</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=2A" class="ligne-carte" style="--couleur: #22ff0f; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 2</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Cherbourg-en-Cotentin</span>
                    <div class="ligne-etapes">
                        <span>la Hague</span>
                        <span>Flamanville</span>
                        <span>Banneville-Carteret</span>
                        <span>Taillepied</span>
                        <span>Grandcamp-Maisy</span>
                        <span>Courseulles-sur-Mer</span>
                    </div>
                    <span class="ligne-ville arrivee">Caen</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=3A" class="ligne-carte" style="--couleur: #8338ec; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 3</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Bayeux</span>
                        <span>Caumont-l'Éventé</span>
                        <span>Saint-Lô</span>
                        <span>Coutances</span>
                        <span>Périers</span>
                        <span>Taillepied</span>
                        <span>Cherbourg-en-Cotentin</span>
                    </div>
                    <span class="ligne-ville arrivee">Gatteville-le-Phare</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=4A" class="ligne-carte" style="--couleur: #519617; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 4</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Aunay-sur-Odon</span>
                        <span>Saint-Lô</span>
                        <span>Coutances</span>
                        <span>Granville</span>
                        <span>Avranches</span>
                    </div>
                    <span class="ligne-ville arrivee">Vire Normandie</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=5A" class="ligne-carte" style="--couleur: #e76f51; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 5</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Flamanville</span>
                    <div class="ligne-etapes">
                        <span>Taillepied</span>
                        <span>Granville</span>
                        <span>Villedieu-les-Poêles-Rouffigny</span>
                        <span>Avranches</span>
                    </div>
                    <span class="ligne-ville arrivee">Le Mont-Saint-Michel</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=6A" class="ligne-carte" style="--couleur: #ff697d; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 6</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Granville</span>
                    <div class="ligne-etapes">
                        <span>Villedieu-les-Poêles-Rouffigny</span>
                        <span>Vire Normandie</span>
                        <span>Condé-en-Normandie</span>
                        <span>Aunay-sur-Odon</span>
                    </div>
                    <span class="ligne-ville arrivee">Caen</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=7A" class="ligne-carte" style="--couleur: #664040; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 7</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Courseulles-sur-Mer</span>
                        <span>Deauville</span>
                        <span>Pont-l'Évêque</span>
                        <span>Honfleur</span>
                        <span>Le Havre</span>
                        <span>Fécamp</span>
                        <span>Saint-Valery-en-Caux</span>
                        <span>Dieppe</span>
                        <span>Petit-Caux</span>
                        <span>Le tréport</span>
                    </div>
                    <span class="ligne-ville arrivee">Gamaches</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=8A" class="ligne-carte" style="--couleur: #8a8888; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 8</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Falaise</span>
                        <span>Argentan</span>
                        <span>La Ferté Macé</span>
                        <span>Bagnoles de l'Orne Normandie</span>
                        <span>Bomfront en Poiraie</span>
                    </div>
                    <span class="ligne-ville arrivee">Le Mont-Saint-Michel</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=9A" class="ligne-carte" style="--couleur: #40deba; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 9</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Moult-Chicheboville</span>
                        <span>Mézidon-Canon</span>
                        <span>Lisieux</span>
                        <span>Bernay</span>
                        <span>Beaumont-le-Roger</span>
                        <span>Evreux</span>
                        <span>Pacy-sur-Eurne</span>
                    </div>
                    <span class="ligne-ville arrivee">Vernon</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=10A" class="ligne-carte" style="--couleur: #4a2f2b; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 10</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Caen</span>
                    <div class="ligne-etapes">
                        <span>Saint-Pierre-en-Auge</span>
                        <span>Argentan</span>
                        <span>Briouze</span>
                        <span>Flers</span>
                        <span>Tinchebray-Bocage</span>
                        <span>Vire Normandie</span>
                    </div>
                    <span class="ligne-ville arrivee">Bomfront en Poiraie</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=11A" class="ligne-carte" style="--couleur: #72288a; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 11</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Argentan</span>
                    <div class="ligne-etapes">
                        <span>Sées</span>
                        <span>Gacé</span>
                        <span>Vimoutiers</span>
                        <span>Orbec</span>
                        <span>Lisieux</span>
                        <span>Pont-l'Évêque</span>
                    </div>
                    <span class="ligne-ville arrivee">Le Havre</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=12A" class="ligne-carte" style="--couleur: #db7740; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 12</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Nogent-le-Rotrou</span>
                    <div class="ligne-etapes">
                        <span>Mamers</span>
                        <span>Bellême</span>
                        <span>Mortagne-au-Perche</span>
                        <span>l'Aigle</span>
                        <span>Gacé</span>
                        <span>Argentan</span>
                    </div>
                    <span class="ligne-ville arrivee">Alençon</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=13A" class="ligne-carte" style="--couleur: #33221d; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 13</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Rouen</span>
                    <div class="ligne-etapes">
                        <span>Yvetot</span>
                        <span>Fécamp</span>
                        <span>Bolbec</span>
                        <span>Pont-Audemer</span>
                        <span>Epagnes</span>
                    </div>
                    <span class="ligne-ville arrivee">Lisieux</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=14A" class="ligne-carte" style="--couleur: #e8a0e3; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 14</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Lisieux</span>
                    <div class="ligne-etapes">
                        <span>Vernon</span>
                        <span>Gaillon</span>
                        <span>Louviers</span>
                        <span>Elbeuf</span>
                        <span>Rouen</span>
                    </div>
                    <span class="ligne-ville arrivee">Buchy</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=15A" class="ligne-carte" style="--couleur: #fb5607; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 15</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Rouen</span>
                    <div class="ligne-etapes">
                        <span>Tôtes</span>
                        <span>Dieppe</span>
                        <span>Londinières</span>
                        <span>Neufchâtel-en-Bray</span>
                        <span>Buchy</span>
                        <span>Gourney-en-Bray</span>
                    </div>
                    <span class="ligne-ville arrivee">Gison</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=16A" class="ligne-carte" style="--couleur: #5e5b5b; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 16</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Saint-Valéry-en-Caux</span>
                    <div class="ligne-etapes">
                        <span>Rouen</span>
                        <span>Val-de-Reuil</span>
                        <span>Louviers</span>
                        <span>Evreux</span>
                        <span>Verneuil-d'Avre-et-d'Iton</span>
                        <span>L'Aigle</span>
                        <span>orbec</span>
                    </div>
                    <span class="ligne-ville arrivee">Lisieux</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=17A" class="ligne-carte" style="--couleur: #219ebc; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 17</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Gison</span>
                    <div class="ligne-etapes">
                        <span>Rouen</span>
                        <span>Bernay</span>
                        <span>Orbec</span>
                        <span>Saint-Pierre-en-Auge</span>
                    </div>
                    <span class="ligne-ville arrivee">Caen</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=18A" class="ligne-carte" style="--couleur: #d9e336; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 18</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Le Havre</span>
                    <div class="ligne-etapes">
                        <span>Bolbec</span>
                        <span>Rouen</span>
                        <span>Evreux</span>
                        <span>L'Aigle</span>
                    </div>
                    <span class="ligne-ville arrivee">Sées</span>
                </div>
            </a>

            <a href="horaire_ligne.php?lig=19A" class="ligne-carte" style="--couleur: #2e2c2c; text-decoration:none; display:block;">
                <div class="ligne-numero">Ligne 19</div>
                <div class="ligne-trajet">
                    <span class="ligne-ville depart">Deauville</span>
                    <div class="ligne-etapes">
                        <span>Moult-Chicheboville</span>
                        <span>Condé-en-Normandie</span>
                        <span>Flers</span>
                        <span>Bagnoles-de-l'Orne Normandie</span>
                    </div>
                    <span class="ligne-ville arrivee">Alençon</span>
                </div>
            </a>

        </div>
    </main>

    <?php 
    include_once("../PHP/footer.php");
  ?>
</body>
</html>