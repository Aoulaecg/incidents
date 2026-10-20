# Gestion des parkings et des affectations — CGPark

Fonctionnalité ajoutée le 13 octobre 2026 dans le projet Laravel existant, connecté à MySQL `gestion_incendie`. Le logo, les couleurs, le menu, les formulaires et les personnalisations de présentation existantes sont conservés.

## Accès et utilisation

Se connecter avec son compte administrateur sur <http://127.0.0.1:8000/administration/connexion>.

| Page | URL |
| --- | --- |
| Gestion des parkings | `http://127.0.0.1:8000/administration/parkings` |
| Ajouter un parking | `http://127.0.0.1:8000/administration/parkings/ajouter` |
| Modifier un parking | `http://127.0.0.1:8000/administration/parkings/{id}/modifier` |
| Gestion des superviseurs | `http://127.0.0.1:8000/administration/superviseurs` |
| Ajouter un superviseur | `http://127.0.0.1:8000/administration/superviseurs/ajouter` |
| Modifier un superviseur | `http://127.0.0.1:8000/administration/superviseurs/{id}/modifier` |
| Affectations seules, accès conservé | `http://127.0.0.1:8000/administration/superviseurs/{id}/parkings` |

Remplacer `{id}` par l’identifiant du parking ou du compte. Le serveur doit être démarré et MySQL actif dans XAMPP.

**Ajouter un parking :** ouvrir **Gestion des parkings**, cliquer sur **Ajouter un parking**, saisir le nom, choisir le statut (actif par défaut), puis **Enregistrer le parking**. Le message « Parking ajouté avec succès » confirme l’enregistrement. Un parking actif apparaît immédiatement dans les formulaires d’affectation.

La recherche par nom, le filtre de statut et la pagination sont exécutés côté serveur et peuvent être combinés. Les statistiques total/actifs/inactifs portent sur l’ensemble du catalogue. Le tableau affiche les noms des superviseurs réellement associés, y compris les comptes inactifs avec une indication de leur statut. Sur téléphone, les lignes deviennent des fiches.

**Affecter des parkings :** ouvrir **Gestion des superviseurs**, puis **Ajouter un superviseur** ou **Modifier**. Choisir le rôle **Superviseur** pour afficher **Parkings affectés**. Cocher un ou plusieurs parkings actifs, puis enregistrer le formulaire. La création d’un superviseur et la transformation d’un autre rôle en superviseur exigent au moins un parking actif. Une modification d’un superviseur existant peut retirer toutes ses affectations actives.

La sélection offre une recherche immédiate, **Tout sélectionner**, **Désélectionner tout** et un compteur. Elle affiche deux colonnes sur ordinateur et une sur téléphone. La recherche ne modifie pas les cases déjà cochées. **Tout sélectionner** sélectionne tous les parkings actifs, même ceux masqués par la recherche. Les cases cochées sont conservées lorsque le rôle est changé puis remis sur **Superviseur**, mais aucun parking n’est transmis ou affecté pour les autres rôles.

Les listes d’affectation proviennent de `parkings`, sans catalogue écrit en dur dans les vues. La liste des superviseurs affiche les noms jusqu’à trois parkings, puis leur nombre et une liste dépliable.

## Désactivation, historique et sécurité

La modification d’un parking permet de changer son nom et son statut. Il n’existe aucune route de suppression définitive. La désactivation interdit les nouvelles affectations et les deux types de déclaration. Les affectations déjà présentes sont conservées. Elles apparaissent sous **Affectations inactives conservées**, sans case permettant une nouvelle affectation inactive. Réactiver le parking pour modifier ces associations.

Les changements de compte et d’affectation sont enregistrés ensemble dans une transaction. `sync()` met à jour les associations actives et conserve les associations inactives existantes. Une saisie invalide ne modifie ni le compte ni ses affectations. La modification ordinaire conserve le mot de passe existant. La réinitialisation explicite des mots de passe, déjà présente, conserve son fonctionnement.

Au prochain chargement, le superviseur obtient uniquement ses parkings actifs. Sans parking disponible, le message « Aucun parking ne vous est actuellement affecté. Veuillez contacter votre administrateur. » apparaît et les formulaires de déclaration sont indisponibles. Le service de déclaration revérifie en base le compte actif, son rôle, sa session, le parking actif et son affectation, même si une requête HTTP est forgée ou si le formulaire était ouvert avant un changement d’affectation.

Toutes les nouvelles pages et écritures sont protégées par l’authentification, le statut actif, les droits administrateur, le changement obligatoire de mot de passe et CSRF. Les services revérifient également les droits de l’administrateur en base sous verrou. Les noms sont échappés dans les vues. Le Directeur reste en lecture sur tous les incidents, y compris après retrait d’une affectation, désactivation ou renommage du parking.

## Migration et catalogue initial

Les tables existantes `parkings` et `parking_user`, leurs relations Eloquent, les clés étrangères restrictives et l’unicité `(user_id, parking_id)` ont été vérifiées et réutilisées.

Migration additive : `database/migrations/2026_10_13_000000_add_normalized_name_to_parkings.php`. Elle ajoute une clé `nom_key` avec index unique, calculée après suppression des espaces superflus et conversion Unicode en minuscules. La validation et le modèle utilisent la même normalisation. L’index protège également les enregistrements concurrents. Le nom affiché est nettoyé lors d’une nouvelle saisie ; la migration conserve les noms, statuts et dates existants. En présence de doublons ambigus, elle refuse avant toute modification du schéma et indique les identifiants concernés.

**Cette migration est déjà appliquée à la base locale et les assets sont compilés.** Sur une autre copie existante, depuis `gestion-incendie`, exécuter :

```powershell
php artisan migrate
npm.cmd run build
```

Les 14 parkings initiaux sont déjà présents sans ambiguïté. Leur vérification seule a été exécutée :

```powershell
php artisan app:init-parkings
```

Si des parkings manquent sur une autre installation, la commande suivante ajoute uniquement les absents :

```powershell
php artisan app:init-parkings --apply
```

Elle est idempotente, refuse les ambiguïtés avant écriture, conserve les noms et statuts existants, ne crée aucun compte et ne modifie aucune affectation. Le catalogue initial est dérivé de la configuration déjà existante `config/parking_assignments.php`. Les ajouts futurs se font depuis le web. L’ancienne commande `app:assign-parking-map` est distincte et modifie des affectations ; elle n’est pas nécessaire à cette fonctionnalité et n’a pas été exécutée sur MySQL pour cette étape.

L’empreinte des données existantes a été comparée avant et après : **5 comptes, 14 parkings, 14 affectations, 3 déclarations et 3 entrées d’audit conservés**, avec leurs champs et mots de passe inchangés. Seule la nouvelle colonne d’unicité est ajoutée.

## Vérifications exécutées

| Vérification | Résultat et portée |
| --- | --- |
| `php artisan test --compact` | 90 tests, 772 assertions : authentification, rôles, comptes, mots de passe, déclarations, directeur et nouvelle gestion des parkings. Base SQLite en mémoire. |
| `npm.cmd run build` | Assets Vite compilés. |
| `npm.cmd run test:parkings-ui` | Sept nouvelles vues, quatre largeurs : 1440, 768, 390 et 320 px. Sélection, recherche, compteur, rôles, précochage, affectations inactives, liste dépliable, menu mobile et absence de débordement horizontal/erreurs JavaScript vérifiés. |
| `npm.cmd run test:admin-ui` | Cinq vues d’administration sur quatre largeurs, mots de passe visibles/masqués et confirmation de désactivation. |
| `npm.cmd run test:workspaces-ui` | Espaces superviseur/directeur et affectations sur quatre largeurs ; confirmation/annulation RAS conservées. |
| `php scripts/verify-parkings-mysql.php --run` | 18 contrôles sur le moteur MySQL réel : création et modification de parkings, doublons, création temporaire d’un superviseur avec deux parkings, `sync()`, listes du superviseur, refus incident/RAS hors affectation ou sur parking inactif, historique Directeur, réactivation et conservation finale. |
| Migration, routes et catalogue | Migration appliquée ; cinq routes de gestion des parkings présentes ; 14/14 parkings initiaux vérifiés. |

Le script MySQL vérifie que les tables utilisent InnoDB, puis place **toutes les écritures de test dans une transaction extérieure systématiquement annulée**. Il compare les données après annulation à leur empreinte initiale, y compris les comptes et leurs mots de passe. Aucun compte, parking ou incident de test ne reste enregistré. MySQL peut conserver des trous d’auto-incrément après annulation ; les identifiants existants restent inchangés. Ce script n’exécute aucune migration, suppression ou initialisation.

Les contrôles navigateur utilisent du HTML rendu par Laravel avec des données de test en mémoire et les vrais assets compilés. Ils ne simulent pas une connexion interactive à un compte MySQL réel. **À vérifier manuellement avec votre compte administrateur :** votre parcours complet depuis la connexion jusqu’à l’enregistrement d’un parking réel et d’une affectation réelle ; aucun mot de passe existant n’a été demandé ou utilisé pour ces tests. Les validations HTTP et les écritures des services sont couvertes séparément.

Pour régénérer les pages de revue puis relancer les contrôles :

```powershell
$env:EXPORT_REVIEW_VIEWS='1'
php artisan test --compact
Remove-Item Env:EXPORT_REVIEW_VIEWS
npm.cmd run test:parkings-ui
npm.cmd run test:admin-ui
npm.cmd run test:workspaces-ui
```

## Fichiers créés

- `app/Support/ParkingName.php` : normalisation commune.
- `app/Http/Requests/Admin/SaveParkingRequest.php` : validation du parking.
- `app/Services/ParkingService.php` : écritures administrateur transactionnelles.
- `app/Http/Controllers/Admin/ParkingController.php` : liste, création et modification.
- `app/Console/Commands/InitializeParkingCatalog.php` : initialisation sans modification des données existantes.
- `database/migrations/2026_10_13_000000_add_normalized_name_to_parkings.php` : index d’unicité normalisée.
- `resources/views/admin/parkings/index.blade.php` et `form.blade.php` : nouvelles interfaces.
- `resources/views/components/parking-selector.blade.php` : sélection multiple réutilisable.
- `tests/Feature/ParkingManagementTest.php` : 16 tests supplémentaires.
- `scripts/check-parkings-ui.mjs` : contrôle navigateur des nouvelles vues.
- `scripts/verify-parkings-mysql.php` : contrôle transactionnel MySQL réel.
- `docs/GESTION-PARKINGS.md` : documentation de cette étape.

## Fichiers modifiés

- `app/Models/Parking.php` : calcul automatique de la clé d’unicité.
- `app/Http/Requests/Admin/SaveSupervisorRequest.php` : validation des affectations selon le rôle.
- `app/Services/UserAccountService.php` : création avec affectations, synchronisation et maintien des relations inactives.
- `app/Http/Controllers/Admin/SupervisorController.php` : chargement des parkings et affectations dans les formulaires/liste.
- `app/Http/Controllers/Admin/ParkingAssignmentController.php` : sélection active et validation renforcée.
- `routes/web.php` : cinq routes administrateur pour les parkings.
- `resources/views/layouts/admin.blade.php` : navigation et contexte des parkings.
- `resources/views/admin/supervisors/form.blade.php`, `index.blade.php`, `parkings.blade.php` : affectations intégrées, colonne et formulaire spécialisé.
- `resources/views/supervisor/declarations.blade.php` : message demandé en l’absence de parking autorisé.
- `resources/css/app.css` : sélection, états cochés, listes et adaptation mobile.
- `resources/js/app.js` : recherche, sélection/désélection, compteur et visibilité selon le rôle.
- `tests/Feature/AdministrationTest.php` : les fixtures de création fournissent désormais un parking actif.
- `package.json` : script `test:parkings-ui`.
- `README.md`, `docs/ESPACES-SUPERVISEUR-DIRECTEUR.md` : liens et usage actualisés.
- `public/build/manifest.json` et les fichiers CSS/JS compilés correspondants : générés par Vite.

Les captures/HTML de revue et les scripts d’empreinte locale sont dans `.artifacts`, dossier déjà exclu des fichiers à versionner. Le fichier `.env`, les tables de comptes et les seeders n’ont pas été modifiés.
