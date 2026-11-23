# Student Marketplace Website

A modern, fully responsive, intra-campus marketplace built according to the **Student Marketplace Website Project Specification & Development Blueprint**.

---

## Project Overview
- **Audience:** Students within one educational institution.
- **Purpose:** Connect student sellers with buyers looking for textbooks, electronics, campus fashion, and hostel essentials without commissions or complex checkout overhead.
- **Platform Type:** Listing and contact platform. Buyers and sellers arrange pricing, inspection, and safe campus pickup independently.
- **Currency:** Ghana Cedis (**GH₵** / `GHS`).
- **Core Stack:** PHP 8.2+, MySQL (InnoDB, UTF-8 mb4) with automatic portable SQLite fallback, HTML5, modern responsive CSS3, and Vanilla JavaScript.

---

## Key Features

### 1. Public Marketplace & Browsing (No Login Required)
- **Homepage (`index.php`):** Hero section, prominent search input, category shortcuts with live counts, recently listed items, "How It Works" 3-step workflow, and campus safety reminders.
- **Browse & Filter (`browse.php`):**
  - Keyword search across product names and descriptions.
  - Filter by Category (*Books, Electronics, Fashion, Hostel Essentials, Stationery, Other*).
  - Filter by Condition (*New, Second-hand*).
  - Filter by Min and Max Price (GH₵).
  - Sort by Newest, Price Low-to-High, Price High-to-Low.
  - Reset filters option and helpful empty-state feedback.
  - **Sold products are strictly excluded** from active marketplace results.
- **Product Details (`product.php`):**
  - High-resolution photo gallery with thumbnail switcher.
  - Price in GH₵, condition and category badges, full description.
  - Verified student seller display name (**Student ID and private email are strictly kept private**).
  - **WhatsApp Click-to-Chat:** Direct link opening WhatsApp with a prefilled, URL-encoded message with product title and price.
  - **Direct Phone Call:** One-tap `tel:` dialer for mobile campus buyers.
  - **Buyer Interest Form:** Allows visitors to submit their name, contact details, and questions without creating an account; dispatches seller notification and records interest.

### 2. Seller Access & One-Time Activation Code System
- **Registration (`register.php`):**
  - Required fields: Full name, unique Student ID, unique school email, phone number, password, confirm password, and single-use seller activation code.
  - **Single-Use Code Validation:** Codes are generated with unpredictable cryptographically secure randomness, securely hashed with SHA-256, verified on the server, and redeemed atomically in a database transaction with account creation.
  - **Replay Protection:** Redeemed codes are immediately rejected.
  - **Rate Limiting:** Protects registration against repeated automated attempts.
  - Once activated, sellers have permanent advertising access and never need another code.

### 3. Personal Seller Dashboard
- **Dashboard Overview (`dashboard.php`):**
  - Metric cards: Active Listings, Sold Listings, Total Buyer Inquiries Received.
  - Recent listings table and recent buyer inquiry notifications.
- **My Listings (`my-listings.php`):**
  - Lists only products belonging to the logged-in seller.
  - **Mark as Sold / Relist:** Toggles listing status. Marking an item as sold instantly removes it from public search.
  - **Delete Listing:** Completely removes listing and cleans up associated image files from disk after user confirmation.
  - **Strict Server-Side Ownership Enforcement:** Every action verifies that `seller_id === current_user_id`. Direct URL manipulation or unauthorized edits trigger HTTP 403 Forbidden.
- **Add Product (`add-product.php`):**
  - Fields: Title, Category, Price (GH₵), Condition (New / Second-hand), Description, Contact Phone.
  - **Image Upload & Preview:** Supports drag-and-drop or file selection for up to 5 photographs (JPEG, PNG, WebP) with client-side thumbnail preview and individual removal before submission.
  - **Upload Security:** Server-side MIME-type inspection via `finfo`, randomized safe filenames, and `.htaccess` forbidding script execution in the uploads directory.
- **Edit Product (`edit-product.php`):**
  - Allows updating listing fields, managing existing photos, and adding new photos.

### 4. Modern E-Commerce Visual Design & Responsive Layout
- **Coordinated Palette:** Deep purple (`#2e1065`), midnight navy (`#1e1b4b`), soft lavender (`#ede9fe`), vibrant coral/orange accents (`#ea580c`, `#f97316`), and white.
- **Mobile 2-Column Grid:** Strict 2-column grid layout on mobile phones for both product categories and product listings. Multi-column on tablet and desktop.
- **Product Cards:** Uniform 4:3 aspect ratio image containers, condition badges (*New* in lavender/purple, *Second-hand* in coral/peach), seller line, bold prices in GH₵, and hover zoom effects.
- **Single-Row Modern Footer:** Clean, uncluttered footer bar with platform copyright, live database connection status badge, and quick navigation links.

---

## Testing Checklist Verification

The included automated test runner (`tests/test_suite.php`) tests all 16 checklist items:

| # | Checklist Item | Status | Verification Detail |
|---|---|:---:|---|
| 1 | Open homepage while logged out | **PASS** | Homepage loads public listings without blocking registration |
| 2 | Search for a product | **PASS** | Relevant active items matching keyword returned |
| 3 | Filter category / condition | **PASS** | Query filters accurately update result sets |
| 4 | Open product details & contact options | **PASS** | Correct images, price in GH₵, and WhatsApp URL rendered |
| 5 | Register with invalid code | **PASS** | Registration rejected with clear server error |
| 6 | Register with used code | **PASS** | Redeemed code rejected immediately |
| 7 | Register with valid unused code | **PASS** | Account created and code redeemed atomically in transaction |
| 8 | Duplicate student ID / email | **PASS** | Prevented by database unique constraints |
| 9 | Log in as seller | **PASS** | Authenticates securely with `password_verify` |
| 10 | Upload product and image | **PASS** | Listing and image path saved in database and shown publicly |
| 11 | Unsupported / oversized image | **PASS** | Server-side MIME check rejects invalid files |
| 12 | Edit another seller's product | **PASS** | Server denies access (HTTP 403 Forbidden) |
| 13 | Mark own product as sold | **PASS** | Status changes to sold; item disappears from active browse |
| 14 | Submit buyer interest | **PASS** | Recorded in `buyer_interest` table and notification logged |
| 15 | Click WhatsApp contact | **PASS** | Valid `wa.me` URL generated with prefilled message |
| 16 | Mobile & responsive layout | **PASS** | Responsive CSS grid, touch targets, and mobile hamburger navigation |

Run the test suite anytime via CLI:
```bash
C:\xampp\php\php.exe tests/test_suite.php
```

---

## Private Seller Access Code Manager

For authorized project team administrators managing one-time seller activation codes:
[admin-codes.php](admin-codes.php)
*(Requires authentication with an authorized project team account; non-admins receive HTTP 403 Forbidden).*

- **Role-Based Access Control:** Restricted exclusively to users with `role = 'admin'`.
- **Cryptographic Security:** Stores deterministic SHA-256 hashes with project salt; never exposes plaintext codes on public pages or APIs.
- **Full Code Lifecycle:** Real-time tracking of *Unused (Available in pool)*, *Distributed (Assigned to student)*, *Redeemed (Account active)*, and *Revoked (Voided)*.
- **Copying & Distribution:** Generate individual or batch codes (1-50) with instant one-click copy to clipboard for manual distribution.
- **Revocation & Concurrency Control:** Instant code revocation with recorded reason, and atomic transaction guards preventing simultaneous race-condition redemption.

---

## Administrator Credentials

To access the private Code Management Dashboard (`admin-codes.php` or `/admin`):
- **Username / Identifier:** `marketplace` (or `marketplace@st.ug.edu.gh`)
- **Password:** `marketplace123`
- **Role:** Administrator (Sole Project Team Account)

*(All other accounts, including student seller Kwame Mensah `kwame.mensah@st.ug.edu.gh`, are standard student sellers and cannot access the admin suite).*

---

## Database & InfinityFree Deployment Guide

### Step 1: Prepare InfinityFree Database
1. Log in to your **InfinityFree Client Area** and click **Manage** on your hosting account.
2. Open the **Control Panel (vPanel)** and navigate to **MySQL Databases**.
3. Create a new database (e.g. `student_marketplace`). InfinityFree will prefix it (e.g. `if0_12345678_student_marketplace`).
4. Note your credentials displayed in the control panel:
   - **MySQL Host Name:** (e.g., `sqlXXX.infinityfree.com` or `sqlXXX.epizy.com`)
   - **MySQL Database Name:** (e.g., `if0_12345678_student_marketplace`)
   - **MySQL User Name:** (e.g., `if0_12345678`)
   - **MySQL Password:** (Your hosting account password from the Client Area)

### Step 2: Import Database Schema
1. From vPanel, open **phpMyAdmin** and select your newly created database in the left sidebar.
2. Click the **Import** tab at the top.
3. Select `database/schema.sql` from your project folder and click **Go**.
   *(Note: The schema is prepared for InfinityFree; it does not contain forbidden `CREATE DATABASE` commands).*

### Step 3: Configure `.env` File
1. In your project root, make a copy of `.env.example` named `.env` (or edit `.env`).
2. Replace the placeholders with your actual InfinityFree MySQL credentials:
   ```ini
   DB_HOST=sqlXXX.infinityfree.com
   DB_NAME=if0_12345678_student_marketplace
   DB_USER=if0_12345678
   DB_PASSWORD=your_hosting_account_password
   DB_PORT=3306

   APP_ENV=production
   APP_URL=https://yourdomain.infinityfreeapp.com
   ENABLE_REAL_EMAIL=false
   NOTIFY_EMAIL_FROM=notifications@campusmarket.local
   ```
3. **Security Note:** `.env` is automatically ignored in `.gitignore` and direct web access is strictly blocked via `.htaccess`. Never commit real credentials to GitHub.

### Step 4: Upload Files via FTP / File Manager
1. Connect via FTP (e.g. FileZilla) or use the vPanel **Online File Manager**.
2. Upload the website files directly into the **`htdocs`** folder of your domain.
3. Ensure the `.env` file and `.htaccess` are uploaded into `htdocs/`.
4. Your website is immediately live!

---

## Notification Center

The project includes an in-app Notification Center (`notifications.php`):
- **Real-Time Badge:** Header bell icon displays live unread notification counts for logged-in sellers.
- **Notification Types:**
  - `Buyer Inquiry`: Dispatched automatically when a buyer clicks "I'm Interested" and submits contact details on any listing.
  - `Listing Update`: Triggered when an item is marked as sold or relisted.
  - `Account Alert`: Generated upon seller registration and account verification.
  - `Campus Announcement`: Broadcast notifications sent by administrators.
- **Read / Unread Tracking:** Visual highlight on unread items, one-click "Mark as Read", "Mark All Read", and "Clear Read" management.
- **Admin Control Suite:** Project team administrators can broadcast campus announcements to all registered sellers with customizable action links and review the global delivery log.

---

## Automated Test Runners

Run test suites anytime via CLI:
```bash
# Core Specification Acceptance Tests (FR-01 through FR-13)
C:\xampp\php\php.exe tests/test_suite.php

# Seller Access Code Manager & RBAC Security Suite
C:\xampp\php\php.exe tests/test_code_manager.php

# InfinityFree Hosting Compatibility & Notification Center Suite
C:\xampp\php\php.exe tests/test_infinityfree_and_notifications.php
```

