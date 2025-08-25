# Symfony Docker Template “VodableWeb”

Un squelette de projet Symfony 6.x (PHP 8.3) entièrement Dockerisé, prêt à forker pour créer vos propres applications web (ERP, CRM, back-office, etc.).

---

## 📦 Caractéristiques principales

- **Symfony 6.x** avec Web App Pack (Twig, annotations, etc.)  
- **Doctrine ORM & Migrations** pour gérer vos entités et bases de données  
- **Docker & Docker Compose** :
  - PHP-FPM 8.3 (Alpine)  
  - Nginx (reverse proxy)  
  - MySQL 8.0 (+ volume persistant)  
  - phpMyAdmin (optionnel)  
- **MakerBundle** pour créer entités, CRUD, contrôleurs, etc.  
- **Base d’ERP/CRM** :
  - Entité `User` + CRUD généré  
  - Page d’accueil  

---

## 🚀 Prérequis

- macOS ou Linux  
- [Docker Desktop](https://www.docker.com/products/docker-desktop) (inclut Docker Engine & Compose)  
- [Git](https://git-scm.com/)  

---

## ⚙️ Installation locale

1. **Cloner le projet**  
   ```bash
   git clone git@github.com:TON_UTILISATEUR/symfony-docker-template.git
   cd symfony-docker-template
   docker compose up -d --build
   ```

   Possible également de cliquer sur le bouton `Use this temaplate`
<img width="194" height="111" alt="image" src="https://github.com/user-attachments/assets/b639995f-42fd-4503-84b9-73f0405a0972" />

Il faut penser à modifier le nom de votre projet dans les fichiers.

2. **Lancer les conteneurs**

```bash
docker compose up -d --build
```

3. **Vérifier**

    Symfony : ```http://localhost```

    phpMyAdmin (optionnel) : ```http://localhost:8080```

    MySQL root : *root* / *rootpassword*

    MySQL symfony : *symfony* / *symfonypass*

4. **Créer la base & tables**
   ```bash
   docker compose exec php php bin/console doctrine:database:create
   docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

## 📝 Licence

Ce projet est sous MIT License. Voir le fichier *LICENSE* pour plus de détails.
