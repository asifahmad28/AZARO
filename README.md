# AZARO — Own Your Style

A fashion e-commerce project based on the previous marketplace project, rebranded and redesigned for AZARO.

## Categories
Shirts, Pants, Trousers, Combo, New Arrivals, Essentials.

## Local setup
1. Put the folder inside XAMPP `htdocs` as `azaro_fashion`.
2. Start Apache + MySQL.
3. Open `http://localhost/azaro_fashion/setup.php`.
4. Login with:
   - Admin: `admin@azaro.local` / `admin123`
   - Moderator: `moderator@azaro.local` / `moderator123`
5. Change the default passwords before real use.

## Email
Set the Gmail App Password in `config.php` under `SMTP_PASS`. Never commit a real App Password to GitHub.

## Notes
- Buyers do not see seller identities.
- Moderator is the internal catalog/order staff role.
- Product discounts are controlled with `price` + `compare_price`.
- The homepage shows a one-session promotional offer popup when a discounted product exists.
- The homepage styling takes broad inspiration from modern editorial fashion storefronts, not copied assets/code.
