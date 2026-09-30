# GraamSetu — Full-Stack Rural Digital Services Platform

**GraamSetu** ("Bridge of Villages") is a production-quality, civic-tech digital web platform designed specifically for Indian rural ecosystems. It connects rural citizens, local merchants (Kirana stores, medical pharmacies, CSC centers), skilled service providers (electricians, plumbers, mechanics, tractor operators), and Gram Panchayat administrators through one unified digital platform.

---

## 🌟 Key Pillars & Features

### 1. 🌾 Rural Citizen Portal
- **Service & Shop Discovery**: Search nearby Kirana availability, emergency medical supplies, and CSC digital services.
- **Service Request Booking**: Request home visits from verified electricians, plumbers, or tractor haulage operators with preferred dates.
- **Village Grievance Redressal**: Lodge infrastructure complaints (drinking water pipeline leaks, electricity voltage fluctuations, shop overcharging) directly with Gram Panchayat.
- **Notice Board & Emergency Helplines**: View official Panchayat scheme notices, weather alerts, and 24x7 emergency helpline numbers (112, 108, Kisan Helpline 1800-180-1551).

### 2. 🏪 Local Merchant / Shopkeeper Portal
- **Store Digitalization**: Manage shop profile, address, opening hours, contact details, and location coordinates.
- **Inventory Management**: Add and manage products with prices per unit and real-time stock toggling (*In Stock / Out of Stock*).
- **Citizen Enquiries**: Receive direct item/stock inquiries from local citizens and send price quotes/replies.

### 3. 🛠️ Skilled Service Provider Portal
- **Service Catalog**: Manage hourly rates and fixed-price service packages (e.g. submersible motor repair, field rotavator tilling).
- **Live Duty Status Toggle**: Switch status in real-time between **Available**, **Busy**, and **Off Duty**.
- **Booking Call Pipeline**: Accept or update inbound booking calls, schedule visits, and write completion notes.

### 4. 🏛️ Gram Panchayat Admin Control Center
- **Dynamic Analytics Dashboard**: Interactive Chart.js charts (Line trend of monthly service calls, Bar chart of listings by category, Doughnut chart of verification breakdown).
- **Merchant & Provider Verification Queue**: Review and verify new local shop and technician registrations before public listing.
- **Grievance Resolution Desk**: Update civic complaint status (*Open*, *Under Review*, *Resolved*) and post official Panchayat resolution notes.
- **Notice Publisher**: Publish village announcements, PM Kisan scheme updates, cattle vaccination camps, and urgent red alerts.
- **Audit Log Inspector**: Track administrative action logs and IP addresses.

---

## 🛠️ Technology Stack

- **Frontend**: HTML5, Custom Responsive CSS (`style.css`, `dashboard.css`), Bootstrap 5, Font Awesome 6, **Leaflet.js** + OpenStreetMap, **Chart.js 4**.
- **Backend**: **PHP 8.2+** (Modular OOP architecture, PDO prepared statements, Session auth, Role middleware, CSRF guard).
- **Database**: **MySQL / MariaDB** (15 normalized tables, foreign key constraints, indexes, triggers).

---

## 🔐 Security Standards Implemented

1. **Authentication**: Passwords stored using BCRYPT (`password_hash()`) and verified via `password_verify()`. Session ID regenerated upon login.
2. **SQL Injection Prevention**: 100% database access powered by PDO Prepared Statements with bound parameters.
3. **Role-Based Access Control (RBAC)**: Strict server-side route protection (`require_role('admin')`, `require_role('merchant')`, etc.).
4. **CSRF Protection**: Dynamic per-session tokens inserted in all forms and validated on POST handlers.
5. **XSS Prevention**: Safe output sanitization wrapper function `e()` rendering `htmlspecialchars()`.
6. **Upload Security**: MIME-type validation, extension whitelist (`jpg`, `jpeg`, `png`, `webp`), size limits (2MB), and random filename hashing.

---

## 🔑 Demo Account Credentials (For Viva & Evaluation)

| Role | Name | Email | Password | Access Dashboard |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | Sarpanch Rajesh Sharma | `admin@graamsetu.in` | `password123` | `/admin/dashboard.php` |
| **Citizen** | Ramesh Kumar Patel | `ramesh@example.com` | `password123` | `/citizen/dashboard.php` |
| **Merchant** | Gupta Kirana Store | `gupta.kirana@example.com` | `password123` | `/merchant/dashboard.php` |
| **Provider** | Suresh Electricals | `suresh.electric@example.com` | `password123` | `/provider/dashboard.php` |

*(Note: On the login page, you can also use the one-click **Viva / Demo Quick Sign-in Presets** buttons).*

---

## 🚀 Quick Setup Instructions

### 1. Database Setup (XAMPP / MariaDB)
Ensure MariaDB/MySQL is running, then execute in terminal or phpMyAdmin:
```sql
CREATE DATABASE graamsetu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Import the schema and seed SQL files:
```bash
mysql -u root graamsetu_db < database/schema.sql
mysql -u root graamsetu_db < database/seed.sql
```

### 2. Run PHP Web Server
Navigate to the project root directory and start the built-in PHP development server:
```bash
cd C:\Users\Student\.gemini\antigravity\scratch\graamsetu
C:\xampp\php\php.exe -S localhost:8000
```
Open your browser and visit: `http://localhost:8000`

---

## 🎓 Viva & Project Defense Guide

1. **Why not a generic single-page app or heavy JavaScript framework?**
   - GraamSetu is designed for rural India where mobile bandwidth can be low and device hardware basic. A modular PHP + HTML5 server-rendered architecture ensures fast initial load times, low data consumption, and optimal accessibility.
2. **How does role authorization work?**
   - User roles are stored in the `roles` table and checked upon authentication. Every protected module script calls `require_role('role_name')` before rendering, verifying `$_SESSION['user_data']` on the server side.
3. **How is data integrity maintained?**
   - Primary and foreign keys enforce referential integrity across users, profiles, products, service calls, and complaints. InnoDB engine guarantees ACID transactions.
