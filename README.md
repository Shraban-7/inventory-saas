# Inventory SaaS — Production-Grade Multi-Tenant ERP System

[![Laravel](https://img.shields.io/badge/Backend-Laravel_11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Next.js](https://img.shields.io/badge/Frontend-Next.js_15-000000?style=for-the-badge&logo=next.js&logoColor=white)](https://nextjs.org)
[![TypeScript](https://img.shields.io/badge/Language-TypeScript_5-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Tailwind CSS](https://img.shields.io/badge/Styling-Tailwind_CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

An enterprise-grade, multi-tenant Inventory, Sales, Purchasing, and General Ledger (GL) SaaS ERP application. Engineered with a **Laravel Clean Architecture / DDD API Backend** and a **Next.js 15 App Router Frontend**, providing real-time multi-branch inventory tracking, double-entry financial accounting, and append-only audit ledgers.

---

## 🌟 Key Features & Modules

### 🏢 Multi-Tenant & Multi-Branch Scoping
- **Tenant Isolation**: Global middleware & DB scopes (`tenant_id`) preventing cross-tenant data leakage.
- **Branch Context Switching**: Operational scoping per physical location/warehouse with global or branch-specific permissions.

### 🛡️ Role-Based Access Control (RBAC)
Granular security matrix across 4 pre-seeded user roles:
- **Admin**: Full system access, invoice voiding (`invoice.void`), credit note approvals, and accounting period locks.
- **Manager**: Inventory management, product catalog editing, PO/GRN processing, sales invoice creation.
- **Cashier**: Front-desk invoice issuance and customer payment receipt collection.
- **Accountant**: Financial reports, Chart of Accounts, manual double-entry journal posting (`accounting.post_manual_journal`), and period locking.

### 📦 Products & Catalog Management
- Multi-variant tracking (SKUs, barcodes, cost price, sale price, reorder points).
- Support for FIFO (First-In, First-Out) costing valuation methods.

### 🏭 Multi-Branch Inventory Operations
- **Stock Overview**: Real-time on-hand inventory levels across branches with low-stock alerts (`filter[low_stock]=true`).
- **Manual Adjustments**: Append-only stock movements (In/Out) with audit reason codes.
- **Inter-Branch Transfers**: Atomic stock transfers between authorized branches.
- **Bulk CSV Imports**: Asynchronous background file processor for catalog and stock updates with line-by-line error inspection drawer.

### 💼 Sales & Accounts Receivable (AR)
- **Customers Directory**: Client account management and branch associations.
- **Sales Invoices**: Cursor-paginated invoices with real-time status transitions (`draft`, `issued`, `paid`, `partially_paid`, `voided`).
- **Payment Receipts**: Multi-method receipt recording (`cash`, `bank_transfer`, `card`, `cheque`, `other`).
- **Credit Notes**: Sales returns and credit note issuance with formal approval workflows.

### 🛒 Purchasing & Accounts Payable (AP)
- **Vendor Suppliers**: Supplier profile management and contact representatives.
- **Purchase Orders**: Order lifecycle tracking (`draft`, `confirmed`, `partially_received`, `received`, `cancelled`).
- **Goods Receipt Notes (GRN)**: Physical shipment intake at warehouse level with automated FIFO cost batching.
- **Vendor Bills & Payments**: Accounts payable bill approvals (`draft`, `approved`, `partially_paid`, `paid`) and outgoing payment recording.

### ⚖️ Accounting & General Ledger (GL)
- **Chart of Accounts (CoA)**: Double-entry account structure (Assets, Liabilities, Equity, Revenue, Expenses).
- **Manual Journals**: Double-entry journal entry posting with balanced debit/credit validation and source reference linking.
- **Period Locking**: Accounting period locking to protect historical financial statements from retroactive alterations.

### 📊 Asynchronous Financial Reporting
- **Profit & Loss (P&L)**: Background job calculation engine for generating income statements across custom date ranges with real-time polling and report downloads.

### 🔌 Webhooks & Webhook Subscriptions
- Event subscription management (`invoice.created`, `stock.low`, `payment.received`, etc.).
- HMAC SHA-256 signature verification headers (`X-Signature`), manual test pinging, and execution delivery logs.

---

## 🛠️ Technology Stack

### Backend (REST API)
- **Framework**: Laravel 11 (PHP 8.2+)
- **Architecture**: Domain-Driven Design (DDD) / Clean Architecture
- **Authentication**: Laravel Sanctum (Token-Based Auth)
- **Validation**: Dedicated FormRequest classes
- **Error Standards**: RFC 7807 Problem Details (`application/problem+json`)
- **API Security**: `Idempotency-Key` header enforcement on state-modifying requests

### Frontend (Client SPA/SSR)
- **Framework**: Next.js 15 (App Router, Server & Client Components)
- **Language**: TypeScript (Strict Mode)
- **State & Data Fetching**: TanStack React Query v5 (Optimistic UI & Cache Invalidation)
- **Forms & Validation**: React Hook Form + Zod validation schemas
- **Styling & UI**: Vanilla CSS + Tailwind CSS, Lucide Icons, Sonner toasts
- **UI Design**: Modern glassmorphism, dark mode support, accessible data tables

---

## 📁 Repository Structure

```
inventory-saas/
├── app/                        # Laravel Clean Architecture Backend
│   ├── Application/            # Use cases, DTOs, Services
│   ├── Domain/                 # Entities, Enums, Value Objects
│   ├── Infrastructure/         # Models, Migrations, Repositories
│   └── Presentation/           # Controllers, FormRequests, API Resources
├── client/                     # Next.js 15 Frontend Workspace
│   ├── docs/                   # Frontend Specs, Architecture & UX Rules
│   ├── tasks/                  # Implementation Checklists (Phases 00–10)
│   ├── src/
│   │   ├── app/                # Next.js App Router Pages & Shell Layouts
│   │   ├── components/         # Design System Primitives & UI Shell
│   │   ├── features/           # Domain API Hooks & Zod Schemas
│   │   ├── lib/                # API Client, Auth Store & Utility Helpers
│   │   └── types/              # TypeScript Domain Contracts
├── api-collection.json         # Complete Postman v2.1 Collection
└── README.md                   # Project Documentation
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+ with `sqlite3` or `pdo_mysql` extensions
- Composer 2.x
- Node.js 18+ & npm 10+

---

### 1. Backend Setup

```bash
# Clone repository
git clone https://github.com/Shraban-7/inventory-saas.git
cd inventory-saas

# Install PHP dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Run database migrations and seed default tenant/roles
php artisan migrate:fresh --seed

# Start Laravel backend server (Runs on http://localhost:8000)
php artisan serve
```

---

### 2. Frontend Setup

```bash
# Navigate to Next.js client workspace
cd client

# Install Node dependencies
npm install

# Start Next.js development server (Runs on http://localhost:3000)
npm run dev
```

Open [http://localhost:3000](http://localhost:3000) in your browser.

---

## 🧪 Quality Assurance & Building

The frontend includes zero-error quality gates for TypeScript type checking, linting, and production builds:

```bash
cd client

# Type Check
npm run typecheck

# Lint Codebase
npm run lint

# Production Build Verification
npm run build
```

---

## 📡 API Idempotency Protocol

To prevent accidental double-billing or duplicate stock movements during network retries, the following endpoints require an `Idempotency-Key` header (UUID v4):

| Module | Endpoint | Required Header |
| :--- | :--- | :--- |
| **Sales** | `POST /api/v1/invoices` | `Idempotency-Key: <uuid>` |
| **Sales** | `POST /api/v1/invoices/{id}/receipts` | `Idempotency-Key: <uuid>` |
| **Sales** | `PUT /api/v1/invoices/{id}/void` | `Idempotency-Key: <uuid>` |
| **Sales** | `POST /api/v1/credit-notes` | `Idempotency-Key: <uuid>` |
| **Sales** | `PUT /api/v1/credit-notes/{id}/approve` | `Idempotency-Key: <uuid>` |
| **Inventory** | `POST /api/v1/stock-adjustments` | `Idempotency-Key: <uuid>` |
| **Inventory** | `POST /api/v1/stock-transfers` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `POST /api/v1/purchase-orders` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `PUT /api/v1/purchase-orders/{id}/confirm` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `PUT /api/v1/purchase-orders/{id}/cancel` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `POST /api/v1/goods-receipt-notes` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `POST /api/v1/bills` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `PUT /api/v1/bills/{id}/approve` | `Idempotency-Key: <uuid>` |
| **Purchasing** | `POST /api/v1/bills/{id}/payments` | `Idempotency-Key: <uuid>` |
| **Accounting**| `POST /api/v1/manual-journals` | `Idempotency-Key: <uuid>` |

---

## 📄 Postman Collection

Import `api-collection.json` located at the root of the project into Postman to explore all 47 REST API endpoints, pre-configured environment variables, and sample request/response payloads.

---

## 📜 License

This project is open-sourced under the [MIT License](LICENSE).
