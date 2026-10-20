# Connexions séparées — CGPark

## Accès et redirections

| Espace | Page de connexion | Identifiant | Rôle MySQL requis | Destination |
| --- | --- | --- | --- | --- |
| Superviseur | `/connexion` | Liste des superviseurs actifs | `supervisor` | `/tableau-de-bord` |
| Administrateur | `/administration/connexion` | Adresse email | `admin` | `/administration/superviseurs` |
| Directeur | `/direction/connexion` | Adresse email | `directeur` | `/directeur` |

Les URL complètes utilisent le serveur local `http://127.0.0.1:8000`. Après déconnexion, chaque utilisateur revient à la page correspondant au rôle qu’il avait avant la fermeture de sa session. Une session de compte désactivé ou révoquée est également renvoyée vers son portail.

Si un utilisateur connecté ouvre n’importe laquelle des trois pages de connexion, il revient à son propre espace. Les comptes avec un mot de passe temporaire continuent d’être dirigés vers `/changer-mot-de-passe` avant d’accéder à leur espace.

## Vérification des comptes existants

Les identifiants, emails exacts, rôles et statuts ont été examinés dans MySQL. Les comptes #4 (Kenza Raghay, `admin`) et #5 (Hamza El Hajoui, `directeur`) étaient corrects et n’ont pas été modifiés.

Les comptes superviseurs #1, #2 et #3 portaient l’ancienne valeur `superviseur`. La correction nécessaire a été signalée avant exécution ; leurs identifiants et emails ont été revérifiés au moment de l’écriture, puis seule la colonne `role` a été harmonisée en `supervisor`. Une comparaison de tous les autres champs utilisateurs confirme leur conservation, y compris les hashes de mots de passe. Aucun compte n’a été créé ou supprimé ; les affectations et les déclarations restent associées aux mêmes identifiants.

La migration `2026_10_12_000003_set_supervisor_role_default.php` adapte seulement la valeur par défaut de `users.role` pour les insertions futures. Elle ne réalise pas de correction massive des comptes existants. Sur une autre base portant encore des rôles historiques, vérifier les identifiants/emails et signaler les corrections avant de les appliquer. Les nouveaux superviseurs créés depuis l’administration utilisent `supervisor` et apparaissent automatiquement sur `/connexion` s’ils sont actifs.

## Contrôles côté serveur

Les trois portails utilisent le même guard Laravel, la même table `users` et les mêmes sessions. Le rôle attendu est choisi uniquement à partir de la route serveur ; une valeur `role` envoyée dans le formulaire ne peut pas le remplacer.

La connexion superviseur exige un identifiant de compte avec le rôle `supervisor` et un statut actif. Les connexions privilégiées exigent un email et vérifient respectivement `admin` et `directeur`, ainsi que le statut actif. Le même message **« Connexion impossible. Vérifiez vos identifiants. »** est renvoyé pour un compte inconnu, un mot de passe incorrect, un compte inactif ou un rôle incompatible.

La liste publique exclut les administrateurs et les directeurs, et ne publie pas les emails ni les hashes. Aucun lien vers les portails privilégiés n’est ajouté à la page superviseur. Les pages privilégiées ne présentent aucune liste de comptes et ne préremplissent aucune identité réelle.

Les routes métier vérifient leur rôle avant le changement de mot de passe obligatoire : un administrateur ne peut pas consulter ou utiliser les routes superviseur/Directeur ; un Directeur ne peut pas utiliser l’administration ou déclarer un incident ; un superviseur n’a pas accès aux espaces privilégiés. Les visiteurs non authentifiés sont dirigés vers la connexion du périmètre demandé.

Les protections existantes sont conservées : CSRF, régénération de session à la connexion, invalidation et nouveau jeton CSRF à la déconnexion, cookies et sessions configurés dans `.env`, contrôle `auth_version` et des comptes actifs à chaque requête. La limitation des échecs est commune aux trois portails : 5 échecs par identifiant/IP et 20 échecs par IP sur 60 secondes. Les deux portails email partagent le compteur du même email normalisé pour empêcher un contournement en changeant de page ou de casse. Les mots de passe ne sont jamais réaffichés après une erreur.

L’administration des comptes, les affectations, les incidents, les confirmations RAS, leur audit et le tableau de bord Directeur restent disponibles avec leurs routes existantes. La page `/tableau-de-bord` est maintenant exclusivement réservée aux superviseurs ; l’ancien lien administrateur vers ce point d’entrée a donc été retiré.

## Fichiers créés/modifiés

Créés :

- `resources/views/auth/role-login.blade.php` : formulaire email commun aux deux portails privilégiés.
- `database/migrations/2026_10_12_000003_set_supervisor_role_default.php` : valeur par défaut du rôle canonique.
- `tests/Feature/SeparateLoginTest.php` : matrice des rôles, portails, redirections et protections.
- Ce document.

Modifiés :

- `app/Models/User.php` : rôle `supervisor` et choix de la page de connexion selon le rôle.
- `app/Http/Requests/Auth/LoginRequest.php` : validation par portail, email normalisé, rôle serveur strict, erreur générique et compteurs communs.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` : liste filtrée, nouvelles pages et déconnexion vers le portail d’origine.
- `app/Http/Middleware/EnsureAccountIsActive.php`, `bootstrap/app.php` : portail lors d’une session révoquée ou d’un accès visiteur.
- `routes/web.php`, `app/Http/Controllers/Supervisor/DeclarationController.php` : séparation stricte des espaces.
- `app/Http/Controllers/Admin/SupervisorController.php`, `app/Console/Commands/CreateFirstAdministrator.php` : redirection et instructions vers la connexion administrateur.
- `resources/views/layouts/auth.blade.php`, `resources/views/components/icon.blade.php`, `resources/js/app.js` : identité visuelle réutilisée, icône email et état du bouton compatible avec les pages sans liste déroulante.
- `resources/views/layouts/admin.blade.php`, `resources/views/admin/supervisors/form.blade.php`, `index.blade.php` : navigation et valeurs de rôle harmonisées.
- `tests/Feature/AuthenticationTest.php`, `AdministrationTest.php`, `DeclarationTest.php`, `PasswordChangeTest.php`, `scripts/check-auth-ui.mjs` : vérifications adaptées aux connexions séparées.
- `README.md`, `docs/ESPACES-SUPERVISEUR-DIRECTEUR.md` : liens et instructions actualisés.

Le CSS, le logo et le design de la page superviseur sont conservés. Les assets générés sont dans `public/build/`.

## Commandes et tests

Sur cette installation, la migration et la compilation sont déjà réalisées. MySQL étant démarré dans XAMPP, lancer si nécessaire :

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Pour vérifier :

```powershell
php artisan test --compact
npm.cmd run build
# Serveur Laravel démarré :
npm.cmd run test:browser
```

Les tests PHP utilisent SQLite en mémoire et n’altèrent pas MySQL. Les 74 tests / 612 assertions vérifient notamment les neuf combinaisons compte/portail, les accès croisés, les comptes inactifs, les tentatives de forger un rôle, les redirections des sessions existantes, la déconnexion, CSRF, la régénération de session, les compteurs partagés, l’absence des comptes privilégiés de la liste, l’apparition des futurs superviseurs et les fonctionnalités métier précédentes.

Le test navigateur examine les trois pages réelles sur le serveur local à 1440, 768, 390 et 320 px. Il vérifie leurs champs, l’absence de liens/listes incompatibles, le bouton du mot de passe, le fonctionnement JavaScript et l’absence de débordement. Sans identifiants de test fournis, il n’utilise aucun mot de passe réel et ne tente aucune connexion sur les comptes existants. Les parcours d’authentification réussis et refusés sont vérifiés par les tests PHP isolés. Les captures se trouvent dans `.artifacts/`, exclu de Git.
