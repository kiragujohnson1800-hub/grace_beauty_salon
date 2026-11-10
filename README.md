# Grace Beauty Salon

A responsive salon booking application with customer, salonist, and administrator accounts.

## Local setup (XAMPP)

1. Put the project in `C:\xampp12\htdocs\grace_beauty_salon`.
2. Start Apache and MySQL in the XAMPP Control Panel. Make sure PHP has `pdo_mysql` enabled.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `database/grace_beauty_salon.sql`. The script creates the database, tables, and a few sample services.
4. Open the ignored `backend/config.php` and set your MySQL and Gmail settings. Never commit this file. OTP codes are sent to the email address provided and are never shown in the browser.
   The email typed into the registration form is the **recipient**. Separately, `mail.username` and `mail.from_email` must be your sending Gmail account and `mail.password` must be its Google App Password.
   - Set `username` and `from_email` to the Gmail account that will send verification messages.
   - Set `password` to that account's Google App Password, not its normal password. Google requires 2-Step Verification to create an App Password.
   - Gmail uses `smtp.gmail.com`, port `587`, and `tls`; these defaults are already set.
5. PHPMailer is stored in `phpmailer/src/`. SMTP credentials are required; the sample values will not send email. The sender address must be authorized by Gmail.
6. Create the first admin account from PowerShell in the project directory:

   ```powershell
   $env:GRACE_ADMIN_NAME = "Salon Administrator"
   $env:GRACE_ADMIN_EMAIL = "admin@example.com"
   $env:GRACE_ADMIN_PASSWORD = "Use-a-long-unique-password"
   & "C:\xampp12\php\php.exe" .\backend\setup_admin.php
   Remove-Item Env:\GRACE_ADMIN_NAME, Env:\GRACE_ADMIN_EMAIL, Env:\GRACE_ADMIN_PASSWORD
   ```

   Use your own name, email, and a unique password of at least 12 characters. The setup script only runs from the command line.

7. Visit `http://localhost/grace_beauty_salon/`. Do not open the HTML files using `file://`; the browser must reach PHP through Apache for API requests and sessions.

## Included features

- Customer account creation and email OTP verification.
- Role-selected sign-in for customers, salonists, and administrators.
- Customer service browsing and appointment requests/cancellation.
- Salonist appointment schedule and status updates.
- Admin service management, staff/customer listing, salonist creation, appointment assignment, and appointment management.
- Responsive layouts and sample salon photographs from Unsplash.

For production, use HTTPS, configure a real SMTP provider, replace all sample credentials, and keep `backend/config.php` and PHPMailer's source files out of version control.
