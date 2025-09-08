# BabiPay – Multi-Currency Wallet API

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel) ![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql) ![API](https://img.shields.io/badge/API-RESTful-green?style=for-the-badge)

BabiPay is a robust multi-currency wallet and transaction management system built with Laravel 11. It provides secure financial operations with role-based access control, comprehensive transaction tracking, and multi-currency support.

## ✨ Features

### 🔐 Advanced Security

-   **Laravel Sanctum Authentication** with configurable token expiration
-   **Row-level locking** for transaction integrity
-   **Atomic database transactions** for financial operations
-   **Rate limiting** on sensitive endpoints (login, P2P transfers)
-   **Policy-based authorization** throughout the application

### 💰 Financial Operations

-   **Multi-currency support** with proper decimal handling
-   **Decimal-aware balance management** (conversion to/from cents)
-   **Peer-to-peer transfers** between users
-   **Deposit and withdrawal systems** with employee authorization
-   **Wallet status management** (Active, Frozen) with admin controls

### 📊 Transaction System

-   **Complete transaction history** with auditing
-   **Transaction type tracking** (Deposit, Withdraw, PeerToPeer)
-   **Status management** (Pending, Complete, Failed, Cancelled)
-   **Smart transaction side detection** (sender/receiver) for P2P transfers

### 👥 User Management

-   **Role-based system** (Admin, Employee, Customer)
-   **User status control** (Active, Deactivated)
-   **Promotion/demotion system** for employee management
-   **Comprehensive wallet ownership** and access control

## 🏗️ Project Structure

### Models

-   **User** → UUID primary keys, roles (UserRole), relations to wallets/transactions
-   **Wallet** → belongs to User + Currency, has balance + status (WalletStatus)
-   **Currency** → stores available currencies (code, name, decimal places)
-   **Transaction** → logs all financial operations with type and status tracking

### Controllers

-   **AuthController** → register, login, me, logout with secure token management
-   **WalletController** → create wallet, show wallet(s) with proper authorization
-   **CurrencyController** → create/list currencies (admin only)
-   **TransactionsController** → deposit, withdraw, peer-to-peer transfer with atomic operations
-   **UserController** → manage user promotion, activation, deactivation

### Request Validation

-   **Form Request classes** for all operations with custom validation rules
-   **Custom validation rules** for user existence checking
-   **Business logic validation** beyond basic input validation

### Enum System

-   **UserRole** → Admin, Employee, Customer
-   **UserStatus** → Active, Inactive, Suspended
-   **WalletStatus** → Active, Inactive, Suspended
-   **TransactionType** → Deposit, Withdraw, PeerToPeer
-   **TransactionStatus** → Pending, Complete, Failed, Cancelled

## 🛡️ Authorization Gates

Defined in `AuthServiceProvider` and applied in routes:

-   `create-currency` → Admin only
-   `view-wallet` → Wallet owner
-   `view-wallets` → Owner, Admin, Employee
-   `canSendFromWallet` → Wallet owner
-   `deposit` → Admin or Employee
-   `withdraw` → Admin or Employee
-   `view-transactions` → Transaction participant or admin
-   `isAdmin` → Admin role validation

## 📋 API Endpoints

### Authentication

```ma
POST /register → Register new user (throttled: 5/1min)
POST /login → User login (throttled: 10/1min)
GET /me → Get current user info (auth required)
POST /logout → Logout user (auth required)
```

### Currency Management

```ma
GET /currency → List all currencies
POST /currency → Create new currency (Admin only)
```

### Wallet Operations

```ma
POST /wallets → Create new wallet
GET /wallets → List user's wallets
GET /wallets/{wallet} → Get specific wallet (owner only)
GET /users/{user}/wallets → Get user's wallets (owner/admin/employee)
POST /wallets/freeze/{wallet}→ Freeze wallet (Admin only)
POST /wallets/activate/{wallet} → Activate wallet (Admin only)
```

### Transactions

```ma
POST /transactions/deposit → Deposit funds (Employee/Admin)
POST /transactions/withdraw → Withdraw funds (Employee/Admin)
POST /transactions/p2p → Peer-to-peer transfer (owner only, throttled: 5/1min)
GET /users/{user}/transactions → Get user transactions (participant/admin)
```

### User Management (Admin Only)

```ma
GET /users → List all users
POST /users/promote/{user} → Promote user to employee
POST /users/deactivate/{user} → Deactivate user account
POST /users/activate/{user} → Activate user account
```

## 🗄️ Database Schema

### Migrations

-   **Users** → UUID PK, role, username, email, password, phone, status
-   **Currencies** → UUID, code, name, decimal_places
-   **Wallets** → UUID, user_id, currency_id, balance, status
-   **Transactions** → UUID, user_id, wallet_id, related_wallet_id, amount, type, status, description, date
-   **Personal Access Tokens** → Sanctum authentication

### Seeders

-   **AdminUserSeeder** → default admin (email/password from .env)
-   **CurrencySeeder** → seeds default currencies (USD, EUR, etc.)
-   **DatabaseSeeder** → runs Admin + Currency seeders

## 🚀 Installation & Setup

1. **Create PostgreSQL database**:

```sql
CREATE DATABASE babipay;
```

2. **Install dependencies:**

```ma
composer install
```

3. **Configure environment:**

```ma
cp .env.example .env
# Update DB credentials, APP_URL, etc.
```

4. **Generate application key:**

```ma
php artisan key:generate
```

5. **Run migrations and seeders:**

```ma
php artisan migrate --seed
```

5. **Start development server:**

```ma
php artisan serve
```

## 📦 Technology Stack

-   Laravel 11 - PHP framework

-   Laravel Sanctum - API authentication

-   PostgreSQL - Database system

-   UUID primary keys - Secure identifier system

-   PHP Enums - Type-safe enumerations

-   Gates & Policies - Authorization system

## 👥 Default Roles & Access

-   Admin: Full system access, user management, wallet controls

-   Employee: Can process deposits and withdrawals

-   Customer: Can manage own wallets, perform P2P transfers
