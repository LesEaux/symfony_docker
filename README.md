# VodableWeb — template Symfony Docker

Base d’application web Symfony 7.4 LTS / PHP 8.3 : Doctrine, Twig, Bootstrap,
AssetMapper, Stimulus, Turbo, authentification et gestion des comptes. Licence MIT.

## Installation locale

Prérequis : Git, Docker Desktop et Docker Compose. Compatible Windows/WSL2,
macOS et Linux. Toutes les commandes partent de la racine du dépôt.

```bash
docker compose build php
docker compose run --rm --no-deps php composer install
docker compose up -d
docker compose exec php php bin/console app:init --name="Mon application" --company="Mon entreprise"
```

`app:init` génère un secret local, conserve les réglages déjà présents dans
`symfony/.env.local`, applique les migrations et crée le compte administrateur
local **root / root**. Son email interne est `root@example.test` ; le formulaire
de connexion accepte directement l’identifiant `root` en développement.
Les valeurs personnalisées sont prises en compte
à la requête suivante. Pour changer ensuite un réglage existant, éditer `.env.local`.

Pour ajouter trois utilisateurs de démonstration, utiliser `app:init --demo`.
Leurs mots de passe sont aléatoires et affichés uniquement à la création. Une
nouvelle exécution conserve les comptes existants, y compris le mot de passe
du compte root s’il a été modifié. Ces commandes sont réservées à `dev`.
Pour créer uniquement ce compte sur une installation existante :

```bash
docker compose exec php php bin/console app:create-default-user
```

Pour créer un autre administrateur avec un mot de passe personnel :

```bash
docker compose exec php php bin/console app:create-admin
```

- Application : http://localhost
- Emails de développement : http://localhost:8025 (Mailpit, aucun email externe)
- phpMyAdmin facultatif : `docker compose --profile tools up -d`, puis http://localhost:8080
  (serveur `db`, utilisateur `symfony`, mot de passe local `symfonypass`).

Les ports se personnalisent dans `.env` à la racine, ignoré par Git :
`HTTP_PORT`, `MAILPIT_PORT`, `PHPMYADMIN_PORT`. Si le port applicatif change,
modifier aussi `APP_URL` dans `symfony/.env.local` pour les liens envoyés par email.

## Comptes et sécurité

- Les administrateurs créent, modifient, désactivent et suppriment les comptes.
- Le dernier administrateur actif ne peut pas être supprimé, désactivé ou rétrogradé.
- La désactivation empêche la connexion et invalide la session à la requête suivante.
- « Mon profil » permet de modifier ses coordonnées et son mot de passe, avec
  vérification du mot de passe actuel. Les rôles ne sont pas modifiables depuis le profil.
- « Mot de passe oublié » utilise un lien à usage unique valable 15 minutes.
  Seule son empreinte est stockée en base. Une nouvelle demande pour le même compte
  est limitée à une toutes les cinq minutes, et à cinq demandes par IP en quinze minutes.
- Une demande pour une adresse inconnue ou inactive reçoit le même message public.
- Les formulaires et la déconnexion sont protégés par CSRF.

En local, les emails partent directement vers Mailpit, sans worker Messenger.
En production, renseigner un serveur SMTP réel et un expéditeur autorisé.

## Personnalisation et ressources front

Les réglages `APP_NAME`, `APP_COMPANY`, `APP_URL` et `MAILER_FROM` sont centralisés
dans les variables d’environnement. Le nom de l’application et de l’entreprise
s’affichent dans les pages et le menu.

Bootstrap et ses icônes sont verrouillés dans Composer et servis par AssetMapper.
Stimulus et Turbo sont téléchargés à l’installation via `importmap:install`,
puis servis localement. Aucun CDN n’est nécessaire à l’exécution. Un navigateur
moderne avec prise en charge native des importmaps est requis.

Le menu mobile fonctionne au clavier (Échap et gestion du focus), les champs
possèdent des libellés et les actions affichent des messages de résultat.
Les fichiers applicatifs sont dans `symfony/assets`, les templates dans
`symfony/templates`. Le thème sombre professionnel utilise une palette bleu nuit
et turquoise, centralisée dans les variables au début de `assets/styles/app.css`.
Les compteurs et derniers utilisateurs du tableau de bord proviennent de la base.
Le bouton « Apparence » du menu du compte (également dans « Mon profil ») bascule
entre les thèmes clair et sombre. La préférence est enregistrée pour chaque
utilisateur et retrouvée à sa prochaine connexion. Le thème sombre reste le défaut.
Ne pas lancer `asset-map:compile` sur le montage de
travail de développement : cette commande est exécutée dans l’image de production.

## Dépendances et stockage Docker

Le code est partagé avec l’hôte ; `vendor` et `var` utilisent les volumes Docker
`vendor_data` et `var_data` pour éviter les lenteurs sous Windows. Exécuter Composer
et les commandes Symfony dans le conteneur. Le dossier `vendor` éventuellement
présent sur l’hôte n’est pas celui utilisé par PHP ; pour l’IDE, utiliser l’interpréteur Docker.

`db_data` conserve MySQL. `docker compose down` conserve tous ces volumes.
L’option `-v` supprime aussi la base et les dépendances. Avant le passage d’une
ancienne base MySQL 8.0 à 8.4, effectuer une sauvegarde.

```bash
docker compose exec php composer update
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Les identifiants et le secret de `.env.dev` sont des valeurs de développement.
Ne jamais réutiliser ces valeurs pour un environnement public.

## Tests et qualité

```bash
docker compose exec php composer validate --strict
docker compose exec php composer audit
docker compose exec php php bin/console lint:container
docker compose exec php php bin/console lint:yaml config
docker compose exec php php bin/console lint:twig templates
docker compose exec php php bin/console doctrine:schema:validate
docker compose exec php composer analyse
docker compose exec php composer cs-check
docker compose exec php php bin/phpunit
```

`composer cs-fix` applique le formatage. PHPStan analyse le code au niveau 5 avec
l’extension Doctrine. Les tests fonctionnels utilisent SQLite dans `var/test.db`
et reconstruisent exclusivement cette base ; ils couvrent les droits, les comptes
inactifs, le dernier administrateur, le profil et la récupération du mot de passe.

Le workflow `.github/workflows/ci.yml` exécute les contrôles à chaque push et pull
request, vérifie les migrations sur MySQL 8.4 et construit l’image de production.
Il sera exécuté sur GitHub une fois les modifications poussées.

## Production

La configuration indépendante `compose.prod.yaml` contient PHP, Nginx et MySQL,
sans montage du code local ni outils de développement. Les images contiennent
les dépendances de production et les ressources compilées. PHP s’exécute avec
l’utilisateur `www-data` ; les secrets sont injectés au démarrage.

1. Copier `.env.production.example` vers `.env.production` et remplacer toutes les
   valeurs d’exemple. Générer `APP_SECRET` avec un générateur cryptographique.
2. Choisir deux mots de passe MySQL différents. Le mot de passe dans `DATABASE_URL`
   doit correspondre à `MYSQL_PASSWORD` et être encodé pour une URL si nécessaire.
3. Renseigner le domaine HTTPS, le SMTP, l’expéditeur et l’IP/CIDR exacte du reverse
   proxy dans `TRUSTED_PROXIES` (adresse vue par PHP dans le réseau de l’hébergement).
4. Construire, initialiser puis démarrer :

```bash
docker compose --env-file .env.production -f compose.prod.yaml build
docker compose --env-file .env.production -f compose.prod.yaml up -d db
docker compose --env-file .env.production -f compose.prod.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.production -f compose.prod.yaml run --rm php php bin/console app:create-admin
docker compose --env-file .env.production -f compose.prod.yaml up -d
```

Le port HTTP interne est publié sur `127.0.0.1:8088` par défaut. Placer un reverse
proxy HTTPS devant ce port (par exemple Caddy sur le serveur hôte avec
`reverse_proxy 127.0.0.1:8088`). Les cookies de session de production exigent HTTPS.
Ne pas exposer directement ce port comme une application publique en HTTP.

Avant chaque mise à jour, sauvegarder la base, construire les nouvelles images,
relire et exécuter les migrations, puis recréer les services. Conserver une image
précédente et une sauvegarde pour pouvoir revenir en arrière. Les données MySQL
restent dans le volume `production_db`. Pour plusieurs réplicas PHP, prévoir un
stockage partagé des sessions et du rate limiter (Redis, par exemple).
