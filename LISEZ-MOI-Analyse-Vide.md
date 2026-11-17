# Déchargement cacao — « Analyser et comparer » ne renvoyait rien

## Le vrai problème
Le fichier est fourni avec des cellules de date écrites en **numéro Excel** (46001, 40361, 40348…) :
le classeur n'a pas de date de référence, donc Excel enregistre un simple quantième.
La colonne « PoidsNet » de la 2e ligne contenait ainsi `40361` → lue comme une **date** (19/06/2010),
et la « Date de déchargement » (2010 aussi) devenait **antérieure à la date du bon** →
chaque ligne était rejetée pour incohérence, et le rapport s'affichait vide.
« Vérifier la structure » restait OK, car la lecture des colonnes était juste.

## Ce qui a été corrigé
1. Le lecteur lit désormais le **format de chaque colonne** (styles de la feuille) : une cellule
   numérique au format DATE est convertie en vraie date, sans toucher aux nombres ordinaires
   (poids, sacs).
2. Une cellule dont la date reste illisible donne un message précis (« Date illisible (…) »)
   au lieu d'une date fausse.
3. La ligne d'en-tête n'est plus confondue avec une ligne de titre fusionné (ligne à 1-2 colonnes).
4. Les lignes réellement vides (mise en forme laissée sur la feuille) sont écartées.
5. Repli si le fichier n'a pas de table de textes partagés : les libellés ne sortent plus vides.

## Validation locale
1. `php bin/console cache:clear`
2. Ctrl+F5 sur la page d'import
3. « Vérifier la structure » puis « Analyser et comparer »
4. En cas de souci : `var/log/dev.log`

Exemple attendu sur le fichier test : 2 lignes lues, poids 6750 kg et (la 2e ligne a un poids
saisi sous forme de date dans le fichier d'origine → elle est signalée « PoidsNet illisible »
au lieu de partir silencieusement en rejet).
