# Helpdesk — Application de gestion de tickets

![CI](https://github.com/votre-username/helpdesk/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-7-black)

Application Helpdesk développée avec **Symfony 7** dans le cadre d'un mini-projet DevOps.
Elle permet aux utilisateurs de soumettre des tickets de support, aux agents de les traiter, et aux administrateurs de piloter l'activité via un dashboard.

---

## Fonctionnalités

| Rôle | Capacités |
|---|---|
| **Utilisateur** | Créer des tickets, voir ses propres tickets, répondre |
| **Agent** | Voir tous les tickets, s'assigner, changer le statut, répondre |
| **Admin** | Tout faire + dashboard de statistiques, supprimer des tickets |

- Tickets avec **priorité** (Basse / Moyenne / Haute / Urgente) et **statut** (Ouvert / En cours / Résolu / Fermé)
- Fil de réponses entre utilisateur et agent
- Filtres : par statut, priorité, agent assigné
- Dashboard admin : KPI, répartition par statut, stats par agent, activité 7 jours
- Interface responsive avec Tailwind CSS (via CDN, sans build Node.js)

---

## Stack technique

- **Backend** : PHP 8.2, Symfony 7, Doctrine ORM
- **Base de données** : MySQL 8.0
- **Assets** : AssetMapper + Tailwind CSS CDN (aucun build Node.js)
- **Tests** : PHPUnit 11 (unitaires + fonctionnels)
- **CI** : GitHub Actions

---

## Installation

### Prérequis

- PHP 8.2+
- Composer
- MySQL 8.0+
- Symfony CLI (optionnel, recommandé)

### Étapes

```bash
# 1. Cloner le projet
git clone https://github.com/votre-username/helpdesk.git
cd helpdesk

# 2. Installer les dépendances PHP
composer install

# 3. Configurer l'environnement
cp .env.example .env.local
# Éditer .env.local et renseigner DATABASE_URL avec vos credentials MySQL

# 4. Créer la base de données et appliquer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. (Optionnel) Créer un premier admin en base
php bin/console security:hash-password VotreMotDePasse
# Puis insérer en SQL :
# INSERT INTO `user` (email, roles, password, first_name, last_name)
# VALUES ('admin@example.com', '["ROLE_ADMIN"]', '<hash>', 'Admin', 'System');
```

### Configuration `.env.local`

```env
DATABASE_URL="mysql://utilisateur:motdepasse@127.0.0.1:3306/helpdesk?serverVersion=8.0&charset=utf8mb4"
APP_SECRET=votre_secret_aleatoire_32_caracteres
```

---

## Lancement

```bash
# Avec Symfony CLI (recommandé)
symfony serve

# Ou avec le serveur PHP intégré
php -S localhost:8000 -t public/

# Accéder à l'application
open http://localhost:8000
```

---

## Tests

```bash
# Configurer la base de données de test (Symfony ajoute automatiquement le suffixe _test)
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

# Lancer tous les tests
php bin/phpunit

# Tests unitaires uniquement (sans BDD, rapide)
php bin/phpunit tests/Unit/

# Tests fonctionnels uniquement
php bin/phpunit tests/Functional/
```

### Couverture des tests

| Suite | Fichier | Ce qui est testé |
|---|---|---|
| **Unitaire** | `TicketServiceTest` | createTicket, assignAgent, changeStatus, addResponse, canUserViewTicket |
| **Unitaire** | `TicketStatusTest` | Labels, couleurs, valeurs de l'enum |
| **Unitaire** | `TicketPriorityTest` | Labels, ordre de tri de l'enum |
| **Fonctionnel** | `SecurityControllerTest` | Login, register, redirections, accès non authentifié |
| **Fonctionnel** | `TicketControllerTest` | CRUD tickets, isolation par rôle, contrôle d'accès |

---

## Architecture

```
src/
├── Controller/          # Orchestration HTTP (thin controllers)
│   ├── DashboardController.php
│   ├── SecurityController.php
│   ├── RegistrationController.php
│   └── TicketController.php
├── Entity/              # Modèles Doctrine (User, Ticket, TicketResponse)
├── Enum/                # PHP 8.1 backed enums (TicketStatus, TicketPriority)
├── Form/                # Formulaires Symfony (TicketType, TicketFilterType...)
├── Repository/          # Queries Doctrine (findWithFilters, countByStatus...)
└── Service/             # Logique métier (TicketService)

tests/
├── Unit/                # Tests PHPUnit sans BDD (mocks)
└── Functional/          # Tests WebTestCase avec BDD de test
```

**Principe clé** : aucune logique métier dans les controllers. Tout passe par `TicketService` — les controllers se contentent de traiter la requête HTTP et d'appeler le service.

---

## Commits

Ce projet suit la convention **Conventional Commits** :

```
feat:     nouvelle fonctionnalité
fix:      correction de bug
test:     ajout/modification de tests
docs:     documentation
chore:    configuration, dépendances
refactor: refactorisation sans changement fonctionnel
```

---

## Comptes de démonstration

Après installation, créez des comptes via `/register` ou insérez directement en base.

Mot de passe pour tous les comptes de test : `Admin1234!`

| Email | Rôle |
|---|---|
| `admin@helpdesk.fr` | ROLE_ADMIN |
| `agent@helpdesk.fr` | ROLE_AGENT |
| `user@helpdesk.fr` | ROLE_USER |
