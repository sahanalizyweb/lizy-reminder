# Lizy Reminder: Laravel API

REST API for Lizy Reminder (Laravel 13, Sanctum token auth, MySQL). See the [project README](../README.md) for setup, accounts, endpoints and the date rules.

```
php artisan migrate --seed   # tables + staff + the three starting reminders
php artisan serve            # http://127.0.0.1:8000
php artisan test             # needs the lizy_reminder_test database (see project README)
```
