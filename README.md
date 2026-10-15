# Stubborn - boutique en ligne Symfony

> *Don't compromise on your look*

Site e-commerce de la marque de sweat-shirts **Stubborn**, réalisé avec **Symfony 7.4** et **MySQL** dans le cadre du devoir CEF « Réaliser un site e-commerce avec Symfony ».

📄 **Documentation complète (PDF) :** [docs/documentation-stubborn.pdf](docs/documentation-stubborn.pdf)

![Page d'accueil](docs/captures/02-accueil-connecte.jpg)

## Fonctionnalités

| Route | Page | Accès |
|---|---|---|
| `/` | Accueil : présentation de la société et 3 sweat-shirts mis en avant | Tous (bouton « Voir » si connecté) |
| `/register` | Inscription, e-mail d'activation du compte | Visiteur |
| `/login` | Connexion (nom d'utilisateur + mot de passe), « Mot de passe oublié » | Visiteur |
| `/products` | Boutique, filtre par fourchette de prix (10-29 €, 29-35 €, 35-50 €) | Client connecté |
| `/product/{id}` | Fiche d'un sweat-shirt, choix de la taille, ajout au panier | Client connecté |
| `/cart` | Panier : total, retrait d'un article, paiement Stripe | Client connecté |
| `/admin` | Back-office : ajout, modification, suppression des sweat-shirts | Administrateur |

- Authentification avec le composant **Security** de Symfony, rôles `ROLE_USER` et `ROLE_ADMIN`.
- Compte activé par un **lien signé envoyé par e-mail** ; le lien connecte l'utilisateur et le renvoie sur l'accueil.
- Menu dynamique : le lien de la page courante est en **gras et souligné**.
- Panier conservé **en session** pendant toute l'expérience d'achat ; stock vérifié par taille.
- Paiement par **Stripe Checkout en mode test** via la classe service `App\Service\StripeService`.
- **Tests unitaires** du panier, du service Stripe et d'un achat complet, exécutés au lancement de l'application.

## Prérequis

- PHP **8.2** ou plus avec les extensions `pdo_mysql`, `ctype`, `iconv`, `mbstring` (XAMPP convient)
- [Composer](https://getcomposer.org/)
- MySQL **8**
- Un compte [Stripe](https://dashboard.stripe.com/register) (mode test, gratuit, sans carte bancaire)
- [Mailpit](https://mailpit.axllent.org/) pour recevoir les e-mails en local (ou tout autre serveur SMTP de test)

## Installation

```bash
git clone https://github.com/OrionLazu/symfony-CEF.git
cd symfony-CEF
composer install
```

Créer un fichier **`.env.local`** (il n'est jamais versionné) à la racine :

```dotenv
# Identifiants MySQL ; la base "stubborn" est créée automatiquement
DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@127.0.0.1:3306/stubborn?serverVersion=8.0.32&charset=utf8mb4"

# Clés de TEST Stripe : https://dashboard.stripe.com/test/apikeys
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
```

Créer la base, le schéma et charger les données de démonstration (10 sweat-shirts et 2 comptes) :

```bash
composer db:init
```

## Lancement

1. Démarrer Mailpit (SMTP sur le port 1025, interface sur http://localhost:8025) :

   ```bash
   mailpit
   ```

2. Lancer l'application :

   ```bash
   composer start
   ```

   Cette commande **exécute d'abord les tests unitaires** (résultats affichés dans la console), puis démarre le serveur sur **http://127.0.0.1:8000**.

   ```text
   Achat test
    ✔ Achat complet : panier, commande, paiement Stripe, confirmation
    ✔ Recharger la confirmation ne décompte pas le stock deux fois
    ...
   Panier
    ✔ Ajout d'un sweat-shirt au panier
    ✔ Le total est calculé sur toutes les lignes
    ✔ Retirer un article met à jour le total
    ...
   OK (25 tests, 74 assertions)
   ```

Les tests seuls se lancent avec `composer test`.

## Comptes de démonstration

| Rôle | Nom d'utilisateur | Mot de passe |
|---|---|---|
| Administrateur | `admin` | `StubbornAdmin2026` |
| Client | `johndoe` | `StubbornClient2026` |

Ces comptes n'existent que dans les données de démonstration locales (`src/DataFixtures/AppFixtures.php`).

## Effectuer un achat test

1. Se connecter avec `johndoe`, ouvrir la **Boutique**, cliquer sur **Voir**, choisir une taille, **Ajouter au panier**.
2. Dans le **Panier**, cliquer sur **Finaliser ma commande** : la page de paiement Stripe s'ouvre en « Environnement de test ».
3. Payer avec la carte de test Stripe **`4242 4242 4242 4242`**, une date d'expiration future (ex. `12/30`) et n'importe quel CVC (ex. `123`).
4. Stripe renvoie vers la page de confirmation : la commande est enregistrée comme payée, le stock est décrémenté et le panier vidé.

Autres cartes de test utiles : `4000 0000 0000 9995` (paiement refusé), `4000 0025 0000 3155` (authentification 3D Secure).

## Structure du projet

```text
src/
├── Controller/     Pages : accueil, boutique, panier, paiement, back-office, sécurité
├── Entity/         Product, User, Order, OrderItem, ResetPasswordRequest
├── Enum/Size.php   Tailles XS, S, M, L, XL
├── Form/           Formulaires (inscription, produit, ajout au panier…)
├── Model/          CartItem (ligne de panier), PriceRange (fourchettes de prix)
├── Repository/     Requêtes Doctrine
├── Security/       Vérification de l'e-mail, blocage des comptes non activés
├── Service/        CartService, StripeService, CheckoutService, ProductImageUploader
└── Twig/           Filtres d'affichage des prix
templates/          Vues Twig
tests/              Tests unitaires PHPUnit
docs/               Documentation PDF, captures et wireframes
```

## Sécurité

- Mots de passe hachés (algorithme `auto` de Symfony), jamais stockés en clair.
- Protection **CSRF** sur tous les formulaires et actions (connexion, panier, suppression, paiement).
- Lien d'activation **signé et expirant** ; un lien falsifié est refusé.
- `/admin` protégé deux fois : règle `access_control` et attribut `#[IsGranted('ROLE_ADMIN')]`.
- Le paiement est **revérifié auprès de l'API Stripe** au retour : l'URL de retour seule ne suffit pas à valider une commande.
- Images envoyées depuis le back-office : type MIME contrôlé, taille limitée, nom de fichier régénéré.
- Les secrets (base de données, Stripe) restent dans `.env.local`, ignoré par Git.

## Technologies

Symfony 7.4 LTS · Doctrine ORM 3 · Twig · MySQL 8 · Stripe PHP SDK · PHPUnit 11 · SymfonyCasts VerifyEmail & ResetPassword
