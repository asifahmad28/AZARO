# AZARO Fashion — Order & Registration Update

Updated files:
- `register.php` — mobile number is now mandatory for new buyer registration.
- `admin.php` — buyer mobile is shown under the buyer name; the Orders page now has a status filter for All, Incoming, Sent to Courier, Delivered and Returned; page heading is now `Orders`.
- `functions.php` — redesigned the generated AZARO invoice PDF with a cleaner premium layout, customer mobile/email, delivery address, order/courier status, subtotal, discount (when applicable), total and branded footer.
- `assets/style.css` — added styling for the order filter and required mobile-number field.

No database migration is required for these changes because the existing `users.phone` field is already present. The registration form/application layer now requires a non-empty mobile number for new buyers.
