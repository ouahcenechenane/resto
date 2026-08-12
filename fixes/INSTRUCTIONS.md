# RestauManager — Instructions de déploiement des correctifs
# ============================================================

## STRUCTURE DES FICHIERS FOURNIS

fixes/
├── .env                                   ← Remplace .env (SQLite au lieu de MySQL)
├── app/
│   ├── Http/Middleware/
│   │   └── ActivityLogMiddleware.php      ← Corrige le crash du canal 'activity'
│   └── Models/
│       ├── User.php                       ← Modèle séparé (était dans Models.php)
│       ├── Section.php                    ← Modèle séparé
│       ├── Category.php                   ← Modèle séparé
│       ├── MenuItem.php                   ← Modèle séparé
│       ├── RestaurantTable.php            ← Modèle séparé
│       ├── Order.php                      ← Modèle séparé (MANQUANT = cause 500)
│       ├── OrderPerson.php                ← Modèle séparé
│       ├── OrderItem.php                  ← Modèle séparé
│       └── Ticket.php                     ← Modèle séparé
├── config/
│   └── logging.php                        ← Ajoute le canal 'activity'
└── frontend/
    └── auth.js                            ← Gestion 401 plus robuste

## ÉTAPES D'INSTALLATION

### 1. Copier les fichiers backend

Depuis le dossier fixes/, copiez TOUT le contenu vers votre projet Laravel :

backend/restaumanager-backend/.env
backend/restaumanager-backend/app/Models/     (les 9 fichiers .php)
backend/restaumanager-backend/app/Http/Middleware/ActivityLogMiddleware.php
backend/restaumanager-backend/config/logging.php

### 2. Supprimer l'ancien Models.php

  rm backend/restaumanager-backend/app/Models/Models.php

  ⚠️  IMPORTANT : ce fichier doit être supprimé, il contenait
  plusieurs classes dans un seul fichier ce qui empêche l'autoload.

### 3. Copier le fichier frontend

frontend/restaumanager-frontend/auth.js

### 4. Vider les caches Laravel

Dans le terminal, depuis le dossier backend/restaumanager-backend/ :

  php artisan config:clear
  php artisan cache:clear
  php artisan route:clear

### 5. Vérifier que le fichier SQLite existe

  ls database/database.sqlite

  S'il n'existe pas :
    touch database/database.sqlite
    php artisan migrate --force
    php artisan db:seed

  S'il existe déjà avec des données → rien à faire, tout est conservé.

### 6. Redémarrer le serveur

  php artisan serve

## RÉSUMÉ DES BUGS CORRIGÉS

| # | Fichier modifié             | Bug corrigé                                      |
|---|-----------------------------|--------------------------------------------------|
| 1 | .env                        | DB_CONNECTION=mysql → sqlite (cause déconnexion) |
| 2 | .env                        | SESSION_DRIVER=file (plus besoin de MySQL)        |
| 3 | app/Models/*.php            | Modèles séparés (autoload PSR-4 fonctionnel)      |
| 4 | app/Models/Models.php       | À SUPPRIMER                                       |
| 5 | config/logging.php          | Canal 'activity' ajouté (supprime les EMERGENCY)  |
| 6 | ActivityLogMiddleware.php   | try/catch sur Log::channel() → plus de crash      |
| 7 | frontend/auth.js            | 401 répété = déco, 401 unique = notification seule|

## NOTE SUR MYSQL (si tu préfères garder MySQL)

Si tu veux utiliser MySQL au lieu de SQLite :
  1. Garde DB_CONNECTION=mysql dans .env
  2. Assure-toi que XAMPP MySQL est DÉMARRÉ avant de lancer php artisan serve
  3. Crée la base : CREATE DATABASE restaumanager CHARACTER SET utf8mb4;
  4. Lance : php artisan migrate --force && php artisan db:seed

Le problème était que MySQL était parfois éteint → Sanctum ne pouvait
plus valider les tokens → backend retournait 401 → frontend déconnectait.
