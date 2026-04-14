# Stocks 📦

April 2026  
University Degree Project

Stocks is a web-based stock control platform focused on inventory operations, user management, and secure authentication.
It provides a clean dashboard experience with role-based access and audit-ready workflows.

## Features ✨

### 📦 Inventory Management

- Product listing with search and filters
- Product creation and edition
- Dedicated history tracking for stock actions

### 👤 User & Role Management

- Admin and employee roles
- Employee creation and edition (admin only)
- Profile page with account update options

### 🔐 Authentication & Security

- Session-based authentication with CSRF protection
- Login lockout after repeated failed attempts
- Password recovery with reset token flow
- Account activity audit trail
- Profile photo upload with validation

### 🎨 UI/UX

- Responsive layout with sidebar/topbar navigation
- Light/Dark theme toggle with persistence
- Profile avatar in topbar for quick profile access

## How It Works ⚙️

1. Users authenticate through the login page.
2. Access is granted based on role permissions.
3. Inventory operations are performed through dedicated pages.
4. Critical account/auth events are recorded in audit logs.
5. JSON files are used as the storage layer for app data.

## Project Structure 📂

```text
Stocks/
│
├── assets/
├── css/
│   ├── style.css
│   └── partials/
├── data/
├── includes/
│   ├── auth/
│   ├── inventory/
│   ├── layout/
│   └── users/
├── pages/
│   ├── admin/
│   ├── app/
│   ├── auth/
│   └── stock/
├── uploads/
└── index.php
```

## Module Interaction Diagram 🗂️

```text
+------------------+
|     index.php    |  <-- Router / entry point
+------------------+
         |
         v
+------------------+
|  Auth Layer      |  (includes/auth)
+------------------+
         |
         v
+------------------+      +------------------+
|    Page Modules  |<---->|   Layout Layer   |
|  (pages/*)       |      | (includes/layout)|
+------------------+      +------------------+
         |
         v
+------------------+      +------------------+
| Inventory/User   |<---->|   JSON Storage   |
|  Data Handlers   |      |   (data/*.json)  |
+------------------+      +------------------+
```

## Technologies Used 🛠️

- PHP (server-side rendering)
- Bootstrap 5
- Vanilla JavaScript
- JSON file-based persistence

## Build & Run 🚀

This is a PHP web project without a required build step.

Run a local development server from the project root:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000
```

## Default Local Access (First Bootstrap) 🔑

If no user file exists yet, the app seeds a default admin account:

- Username: `admin`
- Password: `admin123`

Use only for local/dev usage and change credentials after first login.

## 👥 Author

- Duarte Lacerda
