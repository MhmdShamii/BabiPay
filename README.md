# BabiPay – Laravel API Project

BabiPay is a **multi-currency wallet and transaction API** built with Laravel 11, Sanctum, and UUIDs.
It supports role-based access control (Admin, Employee, Customer) and secure transactions (deposit, withdraw, P2P).

## 📂 Project Structure

### **Models**

-   **User.php** → UUID IDs, roles (`UserRole`), relations to wallets/transactions
-   **Wallet.php** → belongs to User + Currency, has balance + status (`WalletStatus`)
-   **Currency.php** → stores available currencies (`code`, `name`)
-   **Transaction.php** → logs deposits, withdrawals, P2P with `TransactionType` + `TransactionStatus`

### **Controllers**

-   **AuthController** → register, login, me, logout
-   **WalletController** → create wallet, show wallet(s)
-   **CurrencyController** → create/list currencies
-   **TransactionsController** → deposit, withdraw, peer-to-peer transfer
-   **UserController** → manage user data

### **Requests**

Form Request validation for each operation: register, login, wallet, currency, deposit, withdraw, P2P.

### **Enums**

-   `UserRole` → Admin, Employee, Customer
-   `UserStatus` → Active, Inactive, Suspended
-   `WalletStatus` → Active, Inactive, Suspended
-   `TransactionType` → Deposit, Withdraw, PeerToPeer
-   `TransactionStatus` → Pending, Complete, Failed, Cancelled
-   `UserTransactionRole` → Sender / Receiver

### **Authorization (Gates)**

Defined in `AppServiceProvider` and applied in routes:

-   `create-currency` → Admin only
-   `view-wallet` → Wallet owner
-   `view-wallets` → Owner, Admin, Employee
-   `canSendFromWallet` → Wallet owner
-   `deposit` → Admin or Employee
-   `withdraw` → Admin or Employee

### **Routes**

<pre class="overflow-visible!" data-start="1806" data-end="2708"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-text"><span><span>POST   /register               → AuthController@register
POST   /login                  → AuthController@login
GET    /me                     → AuthController@me (auth required)
POST   /logout                 → AuthController@logout (auth required)

GET    /currency               → CurrencyController@getAll
POST   /currency               → CurrencyController@create (Admin only)

POST   /wallets                → WalletController@create
GET    /wallets                → WalletController@showAll
GET    /wallets/{wallet}       → WalletController@show (owner only)
GET    /users/{user}/wallets   → WalletController@showUserWallets (owner/admin/employee)

POST   /transactions/deposit   → TransactionsController@deposit (Employee/Admin)
POST   /transactions/withdraw  → TransactionsController@withdraw (Employee/Admin)
POST   /transactions/p2p       → TransactionsController@p2p (owner only)
</span></span></code></div></div></pre>

### **Migrations**

-   **Users** → UUID PK, role, username, email, password, phone
-   **Currencies** → UUID, code, name
-   **Wallets** → UUID, user_id, currency_id, balance, status
-   **Transactions** → UUID, user_id, wallet_id, related_wallet_id, amount, type, status, description, date
-   **Personal Access Tokens** → Sanctum authentication

### **Seeders**

-   **AdminUserSeeder** → default admin (email/password from `.env`)
-   **CurrencySeeder** → seeds default currencies (e.g. USD, LBP)
-   **DatabaseSeeder** → runs Admin + Currency seeders

---

## ▶️ How to Start

1. Create a PostgreSQL database manually called **`babipay`**:
    <pre class="overflow-visible!" data-start="3356" data-end="3397"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-sql"><span><span>CREATE</span><span> DATABASE babipay;
    </span></span></code></div></div></pre>
2. Run migrations (this will also run the seeders):
    <pre class="overflow-visible!" data-start="3454" data-end="3498"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-bash"><span><span>php artisan migrate --seed
    </span></span></code></div></div></pre>
3. Start the Laravel development server:
    <pre class="overflow-visible!" data-start="3544" data-end="3579"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-bash"><span><span>php artisan serve
    </span></span></code></div></div></pre>
4. Use the seeded **Admin** account (set in `.env`) to login and manage the system.

---

## 📦 Tech Stack

-   Laravel 11
-   Sanctum for token-based auth
-   UUID primary keys
-   Role/Status/Transaction enums
-   Gates for route-level authorization

---

## ✅ Summary

BabiPay is a **role-based, multi-currency wallet API** with authentication, wallet management, and secure transactions (deposit, withdraw, P2P).

The codebase demonstrates:

-   Good Laravel practices (Form Requests, Gates, UUIDs, Seeders)
-   Separation of concerns (Models, Controllers, Enums, Requests)
-   A realistic e-wallet domain model suitable for learning or extension
