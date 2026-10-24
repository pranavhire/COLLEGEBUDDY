# COLLEGE BUDDY

**Your College. Your Community. Your Buddy.**

College Buddy is a student-to-student web app for college life: buy and sell used academic items, share free study resources, post help requests, and calculate attendance and CGPA.

Built for a Web Technology lab with **HTML5, CSS3, Bootstrap 5, plain JavaScript, PHP, MySQL (mysqli) and XML**, running locally on **XAMPP**.

---

## 1. What is inside

| Area | Pages | Notes |
| --- | --- | --- |
| Home | `index.php` | Hero, 5 feature cards, 4-step "How it works", live statistics from MySQL, call to action |
| Accounts | `register.php`, `login.php`, `logout.php`, `profile.php` | `password_hash()` / `password_verify()`, sessions, CSRF tokens, login lock after 5 wrong passwords |
| Marketplace | `marketplace.php`, `product.php`, `sell.php`, `my-listings.php` | Search, category / condition / price / status filters, sorting, pagination, Mark as Sold, Edit, Delete. **No online payments.** |
| Study Hub | `study.php`, `resource.php`, `download.php`, `upload-resource.php`, `my-resources.php` | **Free only, never sold.** Filters: branch, year, semester, subject, type. Login needed to open or download. |
| Request board | `requests.php`, `request.php`, `create-request.php`, `my-requests.php` | Open / Closed status, respond to a request, close or delete your own |
| Messages | `messages.php` | Contact seller, respond to requests, inbox / sent, unread badge. E-mail addresses are never shown. |
| Reports | `report.php` | Spam, Wrong Information, Inappropriate Content, Fraud / Suspicious, Other |
| Calculators | `attendance.php` (+ `js/attendance.js`), `cgpa.php` (+ `js/cgpa.js`) | Calculations run in the browser; nothing is hard-coded |
| Dashboard | `dashboard.php` | Personal counts, quick actions, recent listings / resources / requests |
| Admin | `admin/dashboard.php`, `users.php`, `listings.php`, `resources.php`, `requests.php`, `reports.php` | Protected by `require_admin()`. Remove, resolve, dismiss and reopen reports |
| Info | `about.php` | Community rules and technology used |

---

## 2. Setup on XAMPP (step by step)

1. **Install XAMPP** (PHP 8.0 or newer) from https://www.apachefriends.org
2. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.
3. **Copy the project folder** `CollegeBuddy` into the XAMPP web folder:
   - Windows: `C:\xampp\htdocs\CollegeBuddy`
   - macOS: `/Applications/XAMPP/htdocs/CollegeBuddy`
   - Linux: `/opt/lampp/htdocs/CollegeBuddy`
4. **Import the database**:
   - Open http://localhost/phpmyadmin
   - Click **Import** in the top menu, choose `database/college_buddy.sql`, then click **Go**.
   - This creates the database `college_buddy`, all tables, and sample data.
5. **Check the database connection** in `includes/db.php`. The default XAMPP settings are already set:
   ```php
   const DB_HOST = 'localhost';
   const DB_USER = 'root';
   const DB_PASS = '';          // XAMPP default: empty password
   const DB_NAME = 'college_buddy';
   ```
   Change these only if you have set a MySQL password.
6. **Open the app** in your browser: http://localhost/CollegeBuddy/

### Troubleshooting

| Problem | Fix |
| --- | --- |
| "Could not connect to the database" | Start MySQL in XAMPP. Check the values in `includes/db.php`. |
| Page not found (404) | Check the folder name is exactly `CollegeBuddy` inside `htdocs`. |
| Upload fails for a large file | Resources allow 10 MB and photos allow 3 MB. Check `upload_max_filesize` and `post_max_size` in `php.ini`. |
| Images missing | Make sure the whole folder was copied, including `images/` and `uploads/`. |

---

## 3. Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Student | `student@collegebuddy.com` | `Student@123` |
| Admin | `admin@collegebuddy.com` | `Admin@123` |

The login page also has **Fill student login** and **Fill admin login** buttons. All sample passwords are stored as `password_hash()` values in the database, never as plain text.

---

## 4. Folder structure

```
CollegeBuddy/
├── index.php, about.php, login.php, register.php, logout.php
├── marketplace.php, product.php, sell.php, my-listings.php
├── study.php, resource.php, download.php, upload-resource.php, my-resources.php
├── requests.php, request.php, create-request.php, my-requests.php
├── messages.php, report.php
├── attendance.php, cgpa.php, dashboard.php, profile.php
├── admin/            admin pages (dashboard, users, listings, resources, requests, reports)
├── includes/         db.php, functions.php, auth.php, admin-auth.php, header.php, footer.php
├── css/style.css     custom theme (Bootstrap is in css/vendor/)
├── js/main.js, validation.js, attendance.js, cgpa.js   (Bootstrap bundle in js/vendor/)
├── images/           logo and sample item pictures
├── uploads/listings/ marketplace photos (uploaded by students)
├── uploads/resources/ study resource files (served only through download.php)
├── xml/college_buddy_data.xml   static lists: branches, years, subjects, categories, grade scale
├── database/college_buddy.sql   database, tables and sample data
└── README.md
```

---

## 5. Tables

| Table | Main columns |
| --- | --- |
| `users` | id, full_name, email (unique), password (hash), branch, year, semester, role (student / admin), created_at |
| `listings` | id, user_id, title, category, description, price, item_condition, location, image, status (Available / Sold) |
| `resources` | id, user_id, title, subject, branch, year, semester, resource_type, file_name, stored_name, file_size, file_ext, download_count |
| `requests` | id, user_id, title, description, category, status (Open / Closed) |
| `messages` | id, sender_id, receiver_id, context_type (listing / request), context_id, body, is_read, created_at |
| `reports` | id, user_id, item_type, item_id, reason, description, status (Pending / Resolved / Dismissed), created_at |

Foreign keys cascade on delete. When a user is deleted, their listings, resources, requests and messages are removed too, and their uploaded files are deleted from the server.

---

## 6. Security checklist (what the code does)

- **Passwords**: `password_hash()` on register, `password_verify()` on login. Never stored or shown in plain text.
- **SQL injection**: every query with user input uses prepared statements (`mysqli`) with bound parameters.
- **XSS**: all output goes through `e()` (`htmlspecialchars`).
- **CSRF**: every POST form has a token (`csrf_field()`) that `verify_csrf()` checks.
- **Sessions**: strict mode, HttpOnly cookies, session id regenerated at login, `logout.php` destroys the session.
- **Access control**: `require_login()` on member pages, `require_admin()` on every admin page. The role is read from the database on every request.
- **Uploads**: a file extension whitelist, real MIME check with `finfo`, random file names, size limits, and no PHP or executable files. `.htaccess` blocks script execution in the upload folders.
- **Downloads**: study files are found by database id, so the URL never contains a file path. Login is required.
- **Errors**: database errors are logged to the server log. Users only see friendly messages.

---

## 7. XML data

`xml/college_buddy_data.xml` holds **static reference data only**: branches, years, semesters, subjects (with branch and semester), resource types, listing and request categories, item conditions, report reasons, and the grade scale. Dynamic data (users, listings, etc.) is stored only in MySQL.

---

## 8. Testing checklist

1. Home page loads and the live statistics show numbers from the database.
2. Register with an invalid email, mismatched passwords, or a semester that does not match the year. Each shows an error.
3. Login with the demo accounts. Login with a wrong password shows an error.
4. Marketplace: filter by category, price and condition; sort by price; open a product; contact the seller (login needed).
5. Sell an item, edit it, mark it as sold, then delete it.
6. Study Hub: filter resources; open a resource; download a PDF (login needed).
7. Upload a `.pdf` file. Try uploading a `.php` file and confirm it is rejected.
8. Post a request, respond to a request from another account, then close the request.
9. Attendance and CGPA calculators with normal and edge-case values.
10. Admin: sign in as the admin, open each admin page, resolve a report, remove a test item.
11. Resize the browser to phone width: the navbar collapses into a menu and tables scroll.

---

## 9. Notes

- College Buddy does not process payments. Students arrange payment and pickup themselves.
- Study resources can never be sold. The Study Hub has no price fields.
- The app is for local learning on XAMPP. For a public server, enable HTTPS and set `APP_DEBUG` to `false` (already the default).
