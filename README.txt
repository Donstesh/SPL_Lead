SPL LEAD INTELLIGENCE — STARTER BUILD
=====================================

1. Create a MySQL database/user in cPanel.
2. Import database.sql in phpMyAdmin.
3. Upload this folder to your web server.
4. Copy config.example.php to config.php.
5. Edit config.php with the MySQL database credentials and correct base URL.
6. Visit setup_admin.php and create the first administrator.
7. DELETE setup_admin.php from the server immediately after creating the administrator.
8. Open login.php.
9. Create your first campaign.

CURRENT BUILD
-------------
- Secure PDO database connection
- Session-based authentication
- Password hashing
- CSRF protection
- First-admin installer
- Dashboard
- Campaign creation
- Lead database
- Lead detail page
- Search interface placeholder
- Website audit schema
- Service opportunity schema
- Quote schema
- Suppression list
- API usage tracking

NEXT BUILD
----------
Google Places API integration:
- Search by category and area
- Handle pagination
- Import place/business data
- Detect missing website field
- De-duplicate businesses
- Assign initial lead score and priority
- Record API usage
