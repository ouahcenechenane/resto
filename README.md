# 🍽️ RestauManager

**Système de gestion de restaurant complet** — gestion des tables, commandes, cuisine, caisse et statistiques en temps réel, avec des interfaces dédiées à chaque rôle (Admin, Caissier, Serveur, Réception, Cuisinier).

![Statut](https://img.shields.io/badge/statut-en%20d%C3%A9veloppement-blue)
![PHP](https://img.shields.io/badge/PHP-8-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-FF2D20?logo=laravel&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)

---

## 📖 Sommaire

- [À propos](#-à-propos)
- [Fonctionnalités](#-fonctionnalités)
- [Aperçu de l'application](#-aperçu-de-lapplication)
- [Stack technique](#-stack-technique)
- [Installation](#-installation)
- [Utilisation](#-utilisation)
- [Structure du projet](#-structure-du-projet)
- [Rôles et permissions](#-rôles-et-permissions)
- [Auteur](#-auteur)

---

## 📝 À propos

RestauManager est une application web complète de gestion de restaurant, pensée pour couvrir l'ensemble du flux opérationnel : de la prise de commande en salle jusqu'à l'encaissement, en passant par la cuisine et le suivi des statistiques. L'application propose une interface différente selon le rôle de l'utilisateur connecté, avec une synchronisation en temps réel entre les différents postes (salle, cuisine, caisse).

## ✨ Fonctionnalités

- 🔐 **Authentification par rôle** : Admin, Caissier, Serveur, Réception, Cuisinier
- 📊 **Tableau de bord Admin** : chiffre d'affaires du jour, tickets émis, taux d'occupation des tables, ventes par heure, top articles vendus
- 🪑 **Gestion des tables en temps réel** : suivi des tables libres/occupées par section (Salle, Terrasse, Cafétéria)
- 🧾 **Point de Vente (POS)** : prise de commande par table, gestion des articles par catégorie, remises, clôture de table
- 👨‍🍳 **Interface Cuisine** : suivi des commandes en cours, notifications de commandes urgentes, gestion des articles retournés
- 💳 **Tickets / Caisse** : historique des tickets, filtres (payés, imprimés, annulés), export CSV
- 📈 **Statistiques avancées** : chiffre d'affaires (semaine/mois), remises accordées, répartition des modes de paiement
- 🔄 **Synchronisation temps réel** entre les postes (via Server-Sent Events)
- 🏨 **Module réservation** intégré (chambres et emporter)

## 📸 Aperçu de l'application

### Connexion — sélection du rôle
![Écran de connexion](docs/screenshots/login.png)

### Tableau de bord Admin — vue d'ensemble
![Vue d'ensemble Admin](docs/screenshots/dashboard-admin.png)

### Gestion des tables en temps réel
![Tables en direct](docs/screenshots/tables-en-direct.png)

### Point de Vente (POS) — prise de commande
![Point de vente](docs/screenshots/pos-caisse.png)

### Plan de salle
![Plan de salle](docs/screenshots/plan-de-salle.png)

### Interface Serveur — POS dédié
![POS Serveur](docs/screenshots/pos-serveur.png)

### Interface Cuisine — suivi des commandes
![Interface Cuisine](docs/screenshots/cuisine.png)

### Tickets / Caisse
![Tickets et caisse](docs/screenshots/tickets-caisse.png)

### Statistiques
![Statistiques](docs/screenshots/statistiques.png)

## 🛠️ Stack technique

**Back-end**
- PHP 8 / Laravel
- Base de données : MySQL / SQLite (configurable)
- Authentification par rôle avec middleware dédié
- Architecture orientée services (`app/Services`)
- Événements et écouteurs pour la synchronisation temps réel (`app/Events`, `app/Listeners`)

**Front-end**
- HTML5 / CSS3 / JavaScript (vanilla)
- Communication temps réel via Server-Sent Events (SSE)
- Interfaces dédiées par rôle : `admin-dashboard.html`, `restaurant-pos.html`, `cuisine.html`, `reception.html`, `serveur.html`

## 🚀 Installation

### Prérequis
- PHP >= 8.1
- Composer
- Node.js (pour les assets front-end du backend, si nécessaire)

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/ouahcenechenane/resto.git
cd resto/backend/restaumanager-backend

# 2. Installer les dépendances PHP
composer install

# 3. Copier le fichier d'environnement et configurer
cp .env.example .env
php artisan key:generate

# 4. Configurer la base de données dans .env
# (SQLite par défaut, ou MySQL selon vos besoins)

# 5. Lancer les migrations et les seeders
php artisan migrate --seed

# 6. Lancer le serveur
php artisan serve
```

L'application sera accessible sur `http://127.0.0.1:8000`.

Le front-end (dossier `frontend/restaumanager-frontend`) peut être servi directement via un serveur local (ex: XAMPP, Live Server) en veillant à ce que les appels API pointent vers l'URL du backend Laravel.

## 💻 Utilisation

1. Ouvrez `login.html` dans votre navigateur
2. Sélectionnez votre rôle (Admin, Caissier, Serveur, Réception, Cuisinier)
3. Connectez-vous avec vos identifiants
4. Accédez à l'interface dédiée à votre rôle

## 📁 Structure du projet

```
restaumanager/
├── backend/
│   └── restaumanager-backend/       # API Laravel
│       ├── app/
│       │   ├── Http/Controllers/Api/  # Contrôleurs API (Auth, Order, Table, Ticket...)
│       │   ├── Models/                 # Modèles Eloquent
│       │   ├── Services/               # Logique métier
│       │   ├── Events/ & Listeners/    # Temps réel (SSE)
│       │   └── Http/Middleware/        # RoleMiddleware, ActivityLogMiddleware
│       └── database/
│           ├── migrations/
│           └── seeders/
├── frontend/
│   └── restaumanager-frontend/      # Interfaces HTML/JS par rôle
│       ├── login.html
│       ├── admin-dashboard.html
│       ├── restaurant-pos.html
│       ├── cuisine.html
│       ├── reception.html
│       └── serveur.html
└── docs/
    └── screenshots/                  # Captures d'écran (README)
```

## 👥 Rôles et permissions

| Rôle | Accès |
|---|---|
| **Admin** | Vue d'ensemble complète, statistiques, gestion des tables, tickets, configuration |
| **Caissier** | Point de vente, encaissement, gestion des tickets |
| **Serveur** | Prise de commande, plan de salle |
| **Réception** | Gestion des réservations et commandes à emporter |
| **Cuisinier** | Suivi des commandes en cuisine, gestion des priorités |

## 👤 Auteur

**Ouahcene Chenane** — Développeur Web Full-Stack
📧 ouahcenechenane@gmail.com
🔗 [github.com/ouahcenechenane](https://github.com/ouahcenechenane)
