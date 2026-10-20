# Notifications automatiques au Directeur

Les incidents et confirmations « Rien à signaler » réutilisent `DeclarationService`, les autorisations et les jetons de soumission existants. Aucune action supplémentaire du superviseur n’est nécessaire. La connexion seule ne déclenche pas de message.

## Installation

Depuis le dossier `gestion-incendie` :

```powershell
php artisan migrate
```

La migration additive `2026_10_16_000000_create_declaration_notifications_table.php` crée uniquement l’historique et la boîte d’envoi `declaration_notifications`. Les tables Laravel `jobs` et `failed_jobs` existent déjà. Les anciennes déclarations ne sont pas notifiées rétroactivement.

Configurer ces variables dans **le fichier `.env` du serveur** avec les valeurs fournies par le prestataire SMTP :

```dotenv
APP_URL=https://journal.votre-domaine.ma
APP_TIMEZONE=Africa/Casablanca
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.votre-prestataire.ma
MAIL_PORT=587
MAIL_USERNAME="identifiant-fourni-par-le-prestataire"
MAIL_PASSWORD="secret-fourni-par-le-prestataire"
MAIL_FROM_ADDRESS="notifications@votre-domaine.ma"
MAIL_FROM_NAME="Journal d'Incidents CGPark"
MAIL_TIMEOUT=30
```

Pour un serveur TLS implicite sur le port 465, utiliser `MAIL_SCHEME=smtps` et `MAIL_PORT=465`. Le transport SMTP sur 587 négocie STARTTLS lorsque le serveur le propose. Utiliser les paramètres de sécurité imposés par le prestataire. Ne pas versionner `.env` ni communiquer ses secrets. `.env.example` conserve `MAIL_MAILER=log` pour le développement : ce transport écrit un aperçu dans les journaux et **ne délivre pas d’email**. Utiliser `smtp` en exploitation, sans repli vers `log` si l’on souhaite que le statut représente une remise au serveur SMTP.

L’adresse du destinataire provient toujours de `users` : compte actif de rôle `directeur`. Si plusieurs comptes Directeur existent, le compte `prenom=Hamza`, `nom=ELHAJOUI` est retenu (sans distinction de casse). À défaut, un unique compte Directeur actif est utilisé. Aucun envoi n’a lieu en cas d’absence, d’ambiguïté ou d’adresse invalide ; la déclaration reste conservée et les tentatives sont tracées. L’adresse est relue à chaque tentative pour tenir compte d’une correction du compte. Aucune adresse du Directeur n’est codée dans un contrôleur ou dans `.env`.

Les notifications utilisent explicitement la connexion de file `database`, même si la file par défaut de l’application est `sync`. Laisser `DB_QUEUE_CONNECTION` absent ou identique à la connexion applicative ; la table `jobs` et la boîte d’envoi doivent partager la même base pour garantir l’atomicité de la remise en file. Ne pas utiliser un serveur ou une base de file distincts avec cette implémentation.

```powershell
php artisan config:clear
php artisan queue:work database --queue=default --tries=5 --timeout=60
```

Le worker doit fonctionner en continu (service Windows ou gestionnaire de processus du serveur). En développement, `composer run dev` démarre aussi un listener avec la connexion par défaut ; conserver `QUEUE_CONNECTION=database`. Le job fixe cinq tentatives avec des délais de 60, 300, 900 puis 3600 secondes, indépendamment de l’option `--tries` du listener. Le délai SMTP est de 30 secondes ; conserver le `retry_after` de la file supérieur au timeout du worker (90 secondes par défaut). Après déploiement de code ou configuration, redémarrer les workers avec `php artisan queue:restart`.

Le planificateur récupère chaque minute les notifications en attente qui n’ont pas pu être mises en file, ainsi que les remises en file interrompues depuis au moins quinze minutes. En développement, lancer un deuxième terminal :

```powershell
php artisan schedule:work
```

En production, exécuter `php artisan schedule:run` chaque minute (cron ou Planificateur de tâches Windows, répertoire de démarrage = dossier du projet). Maintenir également le worker en activité.

## Conservation et suivi

La déclaration, son audit et la notification sont enregistrés dans la même transaction. La mise en file intervient après validation de cette transaction. Si elle échoue, le superviseur obtient quand même la confirmation de sa déclaration ; la boîte d’envoi reste récupérable par le planificateur. Aucun SMTP n’est appelé pendant la requête HTTP.

L’historique est accessible via **Espace Directeur → Historique des notifications par email** (`/directeur/notifications`). Il affiche l’identifiant de déclaration, le parking, le destinataire effectif, le type, le statut, les tentatives et l’heure d’envoi à Casablanca. Le destinataire apparaît après sa résolution par le worker. Les statuts techniques sont `pending`, `sent`, `failed` (En attente, Envoyé, Échec). L’échec peut être temporaire pendant les tentatives automatiques.

Après cinq tentatives infructueuses, le job rejoint `failed_jobs`. Après correction du SMTP ou du compte Directeur, relancer les notifications en échec :

```powershell
php artisan notifications:dispatch --retry-failed
```

Pour réessayer un job Laravel identifié, `php artisan queue:failed` puis `php artisan queue:retry IDENTIFIANT` sont également possibles. Choisir une seule méthode de relance ; une notification déjà envoyée est ignorée même si son job est rejoué. Le compteur `attempts` cumule les appels au traitement d’envoi, y compris les tentatives dont le destinataire est indisponible. Les erreurs sont consignées dans les logs Laravel avec l’identifiant, le compteur et la classe d’erreur, sans réponse SMTP brute ; `last_error` contient un code générique. Les déclarations restent intactes.

La clé unique sur `incident_id`, les jetons existants et les verrous empêchent les doublons issus des doubles clics, des rechargements et de jobs concurrents. Comme tout envoi SMTP classique, une coupure **après acceptation par le serveur et avant confirmation en base** peut toutefois entraîner une nouvelle remise lors d’une tentative suivante. Une garantie absolue d’envoi unique dans ce cas nécessite un prestataire proposant une clé d’idempotence. Le statut « Envoyé » atteste uniquement la prise en charge par le transport configuré, pas la réception en boîte principale ni la lecture.

Les modèles Blade HTML et texte incluent le nom du parking, le superviseur, la date et, pour l’incident, l’heure et la description complète échappée. Les informations métier sont figées lors de la déclaration. Le logo officiel existant est embarqué dans le message, sans dépendre d’un téléchargement distant. Le bouton mène au journal protégé par authentification et autorisation Directeur. La connexion Directeur mène ensuite à son tableau de bord ; aucune signature ou jeton ne contourne les contrôles d’accès. Les dates sont conservées en `Africa/Casablanca`.

## Vérification

```powershell
php artisan test --compact --filter=DeclarationNotificationTest
php artisan test --compact
$env:EXPORT_REVIEW_VIEWS = '1'
php artisan test --filter=test_both_email_layouts_and_history_render_for_browser_review
Remove-Item Env:\EXPORT_REVIEW_VIEWS
node scripts/check-notification-emails-ui.mjs
php scripts/verify-notifications-mysql.php --run
```

Les tests PHPUnit utilisent SQLite en mémoire et le transport de test Laravel ; aucun email réel ni donnée fictive MySQL. Ils couvrent les deux scénarios, les dates à Casablanca, la file réelle avec worker, les transactions et annulations, les doublons, les droits et contradictions RAS, l’indisponibilité de la file, les erreurs SMTP et nouvelles tentatives, les comptes Directeur absents ou ambigus, l’historique protégé, le MIME HTML/texte et le logo embarqué. Le script MySQL utilise une transaction externe toujours annulée et des emails capturés en mémoire : il vérifie les deux types de déclaration, l’idempotence, les verrous et la conservation des données réelles. La vérification navigateur couvre les emails et l’historique sur ordinateur et téléphone ; les captures sont écrites dans `.artifacts/`.

Références : [Laravel 12 — Mail](https://laravel.com/docs/12.x/mail), [Laravel 12 — Queues](https://laravel.com/docs/12.x/queues).
