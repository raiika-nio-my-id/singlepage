# Singlepage

Laravel 13 publisher for one-page client sites.

- Admin: `http://admin.jadidaring.test`
- Published page: `http://{subdomain}.jadidaring.test`
- Unknown tenant hosts return 404
- Admin session cookies are host-only (never `Domain=.jadidaring.test`)
- Client HTML is never rendered in the admin document; preview uses a sandboxed iframe

## Local stack

Infrastructure (`mysql-db`, `global-proxy`) must already be running:

```bash
cd ~/project/infrastructure && docker compose up -d
cd ~/project/comprof-cms && docker compose up -d
```

Then migrate and seed:

```bash
docker compose exec singlepage php artisan migrate --seed
```

Seeded logins (password: `password`):

- `admin@jadidaring.test` — administrator
- `client@jadidaring.test` — demo client (`http://demo.jadidaring.test`)
