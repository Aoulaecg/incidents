# Journal d’Incidents — CGPark

La [gestion des parkings et des affectations](docs/GESTION-PARKINGS.md) est disponible dans l’administration : ajout, modification, activation/désactivation, recherche et affectations multiples intégrées aux formulaires des superviseurs. La migration additive du 13 octobre 2026 est déjà appliquée à cette installation ; les données et mots de passe existants sont conservés.

Les espaces métier Superviseur et Directeur sont disponibles. Consultez [leur documentation](docs/ESPACES-SUPERVISEUR-DIRECTEUR.md) pour les URL, les affectations, la création du compte Directeur et la liste des fichiers de cette étape.

Les connexions sont séparées par rôle : [Superviseur](http://127.0.0.1:8000/connexion), [Administrateur](http://127.0.0.1:8000/administration/connexion), [Direction](http://127.0.0.1:8000/direction/connexion). Voir [la documentation des connexions](docs/CONNEXIONS-PAR-ROLE.md).

Application Laravel 12 / Blade / Bootstrap 5 connectée à la base MySQL existante `gestion_incendie`. L’authentification initiale est conservée et complétée par la gestion des comptes, les rôles et les mots de passe temporaires. Le Journal d’Incidents reste le point d’entrée protégé prévu pour les futures fonctionnalités métier.

## Mise en route dans le projet existant

Démarrer MySQL dans XAMPP. Dans un terminal interactif, depuis `gestion-incendie` :

```powershell
php artisan config:clear
php artisan migrate
npm.cmd run build
php artisan app:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

La migration a déjà été appliquée sur cette installation ; la relancer ne recrée aucune table. Les dépendances sont déjà installées. Sur une autre copie du projet, utiliser `composer install` et `npm.cmd ci` avant ces commandes.

Ouvrir la page de connexion correspondant au rôle. La liste `/connexion` provient directement de `users` et affiche uniquement les superviseurs actifs avec le rôle `supervisor`. L’administrateur se connecte par email à `/administration/connexion` et arrive sur `/administration/superviseurs`. Le Directeur utilise `/direction/connexion` et arrive sur `/directeur`.

## Premier administrateur réel

Une personne autorisée ayant accès au terminal du serveur exécute :

```powershell
php artisan app:create-admin
```

La commande demande le prénom, le nom et l’email professionnel, puis le mot de passe et sa confirmation **en saisie masquée**. Elle refuse un mot de passe faible, un email déjà utilisé ou une confirmation incorrecte et demande une confirmation finale avant l’enregistrement. Utiliser un terminal interactif qui prend en charge la saisie masquée ; la commande refuse de revenir à une saisie visible.

Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial, avec un maximum de 72 octets pour éviter la troncature bcrypt. Aucun mot de passe n’est fourni dans les sources, les seeders ou les arguments de commande. Le compte initial utilise le mot de passe personnel choisi par son titulaire.

Dès qu’un compte `admin` existe, même inactif, cette commande refuse de créer un autre administrateur. Les comptes suivants sont créés dans l’interface web. Elle ne transforme ni ne remplace un compte existant. Les transactions et verrous MySQL empêchent deux procédures d’initialisation simultanées de créer deux premiers administrateurs.

Aucun administrateur n’est créé automatiquement dans la base locale. Les comptes déjà présents sont conservés. `DatabaseSeeder` et l’ancien `SupervisorSeeder` ne créent plus de comptes fictifs et ne changent aucun mot de passe.

## Gestion des superviseurs

L’administration offre :

- Une liste issue de MySQL : ID, prénom/nom, email, rôle, statut, date de création et actions.
- Une recherche par nom, prénom ou email, les filtres de rôle et statut, et une pagination de 15 comptes conservant les filtres.
- La création d’un superviseur ou administrateur avec un email unique et un mot de passe initial robuste, haché automatiquement par Laravel.
- La modification des informations et des droits, sans changer le mot de passe.
- Une page de confirmation avant désactivation, et une réactivation depuis la liste. Une désactivation dans le formulaire de modification exige également une confirmation explicite.
- La réinitialisation du mot de passe avec confirmation et changement obligatoire lors de la prochaine connexion.

Un compte initial créé depuis l’administration utilise aussi un mot de passe temporaire. Après connexion, son titulaire doit saisir ce mot de passe actuel et choisir un nouveau mot de passe personnel avant d’accéder à l’application. Il peut se déconnecter pendant cette étape. Un titulaire peut également changer volontairement son mot de passe depuis son espace.

Le menu reste latéral sur ordinateur et devient un volet sur téléphone/tablette. Sur téléphone, les lignes du tableau deviennent des fiches lisibles. Les formulaires, contrôles et messages sont en français ; les boutons d’affichage du mot de passe sont accessibles au clavier.

## Sécurité et conservation des données

Toutes les routes d’administration utilisent `auth`, `active`, `password.changed` et la Gate Laravel `manage-users`. Les requêtes d’écriture valident aussi le rôle administrateur. Un superviseur reçoit une réponse 403 même s’il appelle directement une route de création, modification, changement de statut ou réinitialisation.

Les écritures verrouillent les comptes administrateurs dans un ordre constant et revérifient les droits avant de modifier les données. Le dernier administrateur actif ne peut pas perdre ses droits et un administrateur ne peut pas désactiver son propre compte. Une rétrogradation personnelle autorisée, lorsqu’un autre administrateur actif existe, redirige vers l’espace superviseur.

Une désactivation conserve le compte et son historique, révoque ses sessions et empêche la réutilisation d’une ancienne session après réactivation. Aucun endpoint de suppression physique n’existe.

La migration additive `2026_10_12_000001_add_password_security_fields_to_users_table.php` ajoute uniquement :

| Colonne | Utilité |
| --- | --- |
| `must_change_password` | Exiger un mot de passe personnel après création web ou réinitialisation |
| `auth_version` | Révoquer les anciennes sessions à chaque changement de mot de passe ou désactivation |

Les autres colonnes, comptes, emails, hashes et données existants sont conservés. Il n’y a ni nouvelle base applicative, ni suppression/recréation de la table `users`.

Les sessions restent celles de Laravel. `auth_version` est vérifié à chaque requête protégée. Les sessions du compte cible sont supprimées de la table `sessions` lors d’une désactivation ou réinitialisation ; la version protège aussi les autres pilotes de session. Les jetons de reconnexion sont renouvelés. Une réinitialisation de son propre compte déconnecte l’administrateur. Le changement personnel conserve la session actuelle, la régénère et révoque les autres sessions.

Les protections CSRF et la limitation des essais de connexion existantes restent actives (5 échecs utilisateur/IP, 20 échecs IP, fenêtre de 60 secondes). Le changement personnel est limité à 6 requêtes par minute. Les mots de passe actuel, nouveau et de confirmation sont exclus des données réaffichées après validation ; ni les anciens mots de passe ni les hashes ne sont inclus dans les vues ou messages. Le modèle conserve une liste restreinte de champs assignables en masse ; les droits et champs de sécurité sont affectés explicitement par les services autorisés.

`.env` reste local, ignoré par Git, et conserve la connexion MySQL et APP_KEY existantes. Les assets sont compilés localement et le logo existant dans `public/images` est réutilisé, sans dépendance CDN. Le fuseau est `Africa/Casablanca`. Pour la production, conserver une connexion HTTPS, `APP_ENV=production`, `APP_DEBUG=false` et `SESSION_SECURE_COOKIE=true`.

## Fichiers créés et modifiés pour cette étape

| Domaine | Fichiers créés |
| --- | --- |
| Migration | `database/migrations/2026_10_12_000001_add_password_security_fields_to_users_table.php` |
| Initialisation | `app/Console/Commands/CreateFirstAdministrator.php` |
| Contrôleurs | `app/Http/Controllers/Admin/SupervisorController.php`, `app/Http/Controllers/Auth/PasswordChangeController.php` |
| Validation | `app/Http/Requests/Admin/SaveSupervisorRequest.php`, `app/Http/Requests/Admin/ResetPasswordRequest.php`, `app/Support/PasswordRules.php` |
| Services / middleware | `app/Services/UserAccountService.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php` |
| Vues | `resources/views/layouts/admin.blade.php`, `resources/views/admin/supervisors/index.blade.php`, `form.blade.php`, `password.blade.php`, `deactivate.blade.php`, `resources/views/auth/change-password.blade.php` |
| Composants / erreurs | `resources/views/components/password-field.blade.php`, `feedback.blade.php`, `resources/views/errors/403.blade.php`, `419.blade.php`, `429.blade.php` |
| Traductions | `lang/fr/validation.php`, `lang/fr/pagination.php`, `lang/fr.json` |
| Tests | `tests/Feature/AdministrationTest.php`, `PasswordChangeTest.php`, `CreateFirstAdministratorTest.php`, `scripts/check-admin-ui.mjs` |

Fichiers modifiés : `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Http/Middleware/EnsureAccountIsActive.php`, `bootstrap/app.php`, `routes/web.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/SupervisorSeeder.php`, `resources/css/app.css`, `resources/js/app.js`, `resources/views/components/icon.blade.php`, `resources/views/auth/connected.blade.php`, `tests/Feature/AuthenticationTest.php`, `scripts/check-auth-ui.mjs`, `package.json` et ce `README.md`. Les fichiers compilés sont dans `public/build/`.

## Notifications email au Directeur

Les incidents et déclarations « Rien à signaler » génèrent automatiquement une notification en file, avec historique dans l’espace Directeur. Installation SMTP, worker, planificateur et relances : [docs/NOTIFICATIONS-EMAIL.md](docs/NOTIFICATIONS-EMAIL.md).

## Tests et vérifications

```powershell
php artisan test --compact
npm.cmd run build
```

Les tests PHP utilisent exclusivement SQLite **en mémoire**, sans modifier MySQL ni créer une deuxième base applicative. Ils vérifient les droits de chaque route, la création et modification des comptes, l’unicité de l’email, les mots de passe robustes, la confirmation de désactivation, la protection du dernier administrateur, les sessions révoquées, les mots de passe temporaires, CSRF, la limitation des essais, la procédure d’initialisation unique, l’échappement HTML et la conservation des comptes par les seeders. Les mots de passe de test sont générés aléatoirement dans les fixtures.

Pour générer les vues de revue et vérifier l’administration avec Edge déjà installé :

```powershell
$env:EXPORT_REVIEW_VIEWS = '1'
php artisan test --filter=test_admin_views_can_be_rendered_for_browser_review
Remove-Item Env:\EXPORT_REVIEW_VIEWS
npm.cmd run test:admin-ui
```

Ces cinq écrans sont rendus par Laravel avec des fixtures isolées en mémoire, puis vérifiés dans le navigateur à 1440, 768, 390 et 320 px : liste, création, modification, réinitialisation et confirmation de désactivation. Le script vérifie les débordements, le volet de navigation, les boutons de mot de passe et la confirmation conditionnelle. Les captures sont dans `.artifacts/`, exclu de Git. Aucun compte d’administration de test n’est ajouté à MySQL.

Le serveur local étant démarré, `npm.cmd run test:browser` vérifie aussi la page de connexion existante. Sans identifiants fournis, il se limite à l’affichage et au bouton de mot de passe. Son parcours authentifié facultatif nécessite `TEST_SUPERVISOR_ID` et `TEST_SUPERVISOR_PASSWORD`, à fournir pour un compte de test autorisé sans les écrire dans les sources. `BROWSER_EXECUTABLE_PATH` permet d’utiliser un autre navigateur Chromium existant.
