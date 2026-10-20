# Espaces Superviseur et Directeur — CGPark

## Accès

- Connexion Superviseur : `http://127.0.0.1:8000/connexion`.
- Connexion Administrateur : `http://127.0.0.1:8000/administration/connexion`.
- Connexion Directeur : `http://127.0.0.1:8000/direction/connexion`.
- Superviseur : `http://127.0.0.1:8000/tableau-de-bord` (également accessible via `/superviseur/declarations`).
- Directeur : `http://127.0.0.1:8000/directeur`.
- Administration : `http://127.0.0.1:8000/administration/superviseurs`.

La connexion dirige chaque utilisateur vers son espace selon son rôle **enregistré dans MySQL**. Un mot de passe temporaire doit être remplacé avant tout accès interne. L’administrateur conserve son interface et ses contrôles existants. Le Directeur consulte les données, sans accès aux écritures d’incidents ni à la gestion des comptes.

## Installation sur cette copie

La migration est appliquée, les assets sont compilés, et les 14 affectations demandées sont enregistrées. Aucun compte ni mot de passe existant n’a été modifié. Depuis `gestion-incendie`, avec MySQL démarré dans XAMPP :

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Sur une autre copie équipée des dépendances et connectée à la même structure MySQL :

```powershell
php artisan migrate
npm.cmd ci
npm.cmd run build
php artisan app:assign-parking-map
# Après vérification des identités affichées :
php artisan app:assign-parking-map --apply
```

La commande sans `--apply` vérifie seulement les identités. Si l’une des trois identités est absente, ambiguë, inactive ou n’a pas le rôle superviseur, aucune affectation ni aucun parking n’est créé. La commande ne crée aucun utilisateur et ne change aucun compte existant. `--apply` crée les parkings nommés dans le cahier des charges s’ils sont absents et ajoute leurs affectations sans supprimer les affectations déjà présentes. Les modifications ultérieures se font dans l’administration.

## Affectations vérifiées dans MySQL

| Compte réel | Parkings |
| --- | --- |
| Rabie Benmahmoud | Taghazout ; Aéroport Marrakech |
| Ahmed Allali | Casa Marina A8 ; A9 - CFC |
| Abdelali Inbach | Bab Jdid P1 ; Bab Jdid P2 ; Bab Jdid P3 ; Bab Hamra ; Bab Guissa ; Ain Azliten Principale ; Ain Azliten Annexe ; Bin Lamdoune ; Oued Zhoun ; Bounafaa |

Pour modifier ces affectations : se connecter comme administrateur, ouvrir **Gestion des superviseurs**, puis **Modifier**. La section **Parkings affectés** est intégrée au formulaire. L’action **Parkings** du compte reste également disponible. Cocher les parkings actifs autorisés puis enregistrer. Décocher les parkings actifs retire ces affectations sans supprimer les incidents historiques. Les affectations inactives sont conservées et affichées en lecture seule ; réactiver le parking avant de modifier ces affectations. Voir [la gestion des parkings](GESTION-PARKINGS.md).

## Créer le vrai compte Directeur

Depuis l’administration existante, cliquer sur **Ajouter un superviseur**, renseigner les informations personnelles réelles et l’email individuel, choisir le rôle **Directeur**, laisser le compte actif et saisir un mot de passe initial robuste et sa confirmation. Le titulaire doit remplacer ce mot de passe temporaire à sa première connexion, puis accède à `/directeur`.

Aucun Directeur fictif n’est créé automatiquement. Aucun mot de passe n’est codé en dur ou ajouté aux seeders.

## Déclarations quotidiennes

Le superviseur voit uniquement ses parkings actifs. Un parking unique est présélectionné. La date et l’heure sont proposées dans le fuseau `Africa/Casablanca`, avec possibilité de déclarer un événement antérieur. La description est obligatoire pour un incident. L’enregistrement utilise toujours le superviseur authentifié et revérifie son rôle, son statut, sa session, l’activité du parking et l’affectation en base.

**Rien à signaler aujourd’hui** concerne uniquement le parking choisi. Une boîte de confirmation affiche son nom. La date et l’heure de validation sont calculées côté serveur ; les champs de date, d’heure et de description de l’autre formulaire ne sont pas utilisés. Sans JavaScript, un formulaire avec sélection et confirmation explicite reste disponible.

Chaque formulaire porte un identifiant de soumission unique. Une nouvelle tentative d’envoi du même incident est reconnue sans créer de deuxième ligne. La réutilisation du formulaire avec d’autres faits est refusée. Une contrainte unique supplémentaire empêche plusieurs confirmations RAS pour un même superviseur, parking et jour. Le bouton est désactivé pendant la soumission et rétabli lorsque le navigateur revient à la page.

Les enregistrements sont transactionnels et verrouillent le parking, puis le compte superviseur. Ces contrôles sérialisent les confirmations et incidents concurrents pour un parking. Un incident déjà déclaré par n’importe quel superviseur empêche une nouvelle confirmation RAS pour ce parking et cette date.

Lorsqu’un incident arrive après un RAS, toutes les confirmations actives du parking à cette date passent au statut `superseded`. Le propriétaire d’origine, l’heure de validation et la ligne RAS sont conservés. La date d’annulation, l’incident qui la remplace et l’auteur de la modification sont enregistrés. L’interface affiche la confirmation annulée et la synthèse ne la compte plus comme un RAS valide.

Le tableau du superviseur affiche les déclarations **saisies aujourd’hui**, triées de la plus récente à la plus ancienne, sur ses parkings affectés, y compris les déclarations d’autres superviseurs sur les parkings partagés. Si la date de l’événement est antérieure, elle est indiquée sous la description. Aucun contenu d’un parking non affecté n’est affiché. Les valeurs de saisie sont conservées après validation incorrecte.

## Suivi Directeur

Les filtres de parking, date, type, recherche et ordre sont combinables. La date est celle de l’événement (`date_incident`), contrairement à la liste de saisies du jour du superviseur. Les résultats sont paginés par 20 et les liens conservent les filtres.

La synthèse porte sur la date et le parking sélectionnés, indépendamment de la recherche textuelle et du type de déclaration affiché. Elle compte les incidents actifs, les confirmations RAS encore valides et les parkings **actifs** sans déclaration valide. Les noms des parkings en attente sont affichés et permettent d’appliquer leur filtre. Les parkings inactifs sont exclus de ce nombre, mais leurs déclarations historiques peuvent être consultées.

Les trois espaces réutilisent Bootstrap, les couleurs et le logo CGPark existants. Les tableaux deviennent des cartes sur téléphone. Les données de l’application sont récupérées depuis MySQL ; les fixtures utilisées pour tester les vues restent isolées en mémoire.

## Tables et audit

Une seule migration additive crée `parkings`, `parking_user`, `incidents` et `incident_audits`. La table `users`, les comptes, les hashes, les sessions et les données existantes ne sont ni supprimés ni recréés. Les clés étrangères restreignent la suppression des utilisateurs/parkings portant des déclarations ; les affectations sont uniques par utilisateur et parking.

`incidents` conserve les champs métier demandés, ainsi que `status`, `validated_at`, `superseded_at`, `superseded_by_id`, `submission_token` et `ras_key`. Un RAS a `heure_incident = NULL`, afin de distinguer l’heure d’un événement de l’heure de validation. `incident_audits` conserve les événements `created` et `ras_superseded`, l’acteur, les détails et l’horodatage. Aucun endpoint de modification ou suppression des incidents n’est exposé.

## Fichiers de cette étape

Créés :

- `database/migrations/2026_10_12_000002_create_parking_declaration_tables.php`.
- `app/Models/Parking.php`, `Incident.php`, `IncidentAudit.php`.
- `app/Http/Controllers/Supervisor/DeclarationController.php`, `Director/DashboardController.php`, `Admin/ParkingAssignmentController.php`.
- `app/Http/Requests/StoreDeclarationRequest.php`, `app/Services/DeclarationService.php`.
- `app/Console/Commands/AssignParkingMap.php`, `config/parking_assignments.php`.
- `resources/views/layouts/journal.blade.php`, `supervisor/declarations.blade.php`, `director/dashboard.blade.php`, `components/declarations-table.blade.php`, `admin/supervisors/parkings.blade.php`.
- `tests/Feature/DeclarationTest.php`, `scripts/check-workspaces-ui.mjs`, ce document.

Modifiés : `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `app/Services/UserAccountService.php`, `app/Http/Controllers/Admin/SupervisorController.php`, `routes/web.php`, `resources/views/layouts/admin.blade.php`, `resources/views/admin/supervisors/index.blade.php`, `form.blade.php`, `resources/views/components/icon.blade.php`, `resources/css/app.css`, `resources/js/app.js`, `lang/fr/validation.php`, `package.json`, `README.md`. Les assets générés sont dans `public/build/`.

## Vérification

```powershell
php artisan test --compact
npm.cmd run build
$env:EXPORT_REVIEW_VIEWS = '1'
php artisan test --filter=test_workspaces_can_be_rendered_for_browser_review
Remove-Item Env:\EXPORT_REVIEW_VIEWS
npm.cmd run test:workspaces-ui
```

Les tests PHP utilisent SQLite en mémoire et ne modifient pas les données MySQL. Ils couvrent notamment les affectations des trois superviseurs, les identités ambiguës, les accès interdits, la création d’incidents, les rejouements, les RAS sans description, les doublons, les contradictions entre superviseurs, l’annulation auditée, les parkings partagés, les filtres, les indicateurs, la pagination, la création et connexion du Directeur, les affectations administrateur et CSRF. L’ensemble des tests d’authentification et d’administration précédents est conservé.

La vérification navigateur utilise Edge installé avec Playwright Core. Les vues rendues par Laravel avec des fixtures de test sont examinées à 1440, 768, 390 et 320 px : absence de débordement horizontal, navigation, ouverture/annulation de la confirmation RAS et nom du parking concerné. Les captures restent dans `.artifacts/` (ignoré par Git). Aucune déclaration fictive n’est écrite dans MySQL.

Sur MySQL, les migrations, l’identité des comptes et les affectations 2/2/10 sont vérifiées directement. La lecture des espaces et les requêtes de synthèse ont également été exécutées sur la base réelle. Les quatre comptes existants sont conservés ; 14 parkings et 14 affectations sont présents.
