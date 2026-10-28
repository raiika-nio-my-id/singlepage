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

## Multiplayer games

Mahjong, Exploding Kittens, and every later game share one MySQL database and one Laravel Reverb websocket. The subdomain is the isolation key: `mahjong.jadidaring.test` only sees `game.mahjong` rooms, and `explodingkitten.jadidaring.test` only sees `game.explodingkitten` rooms.

Published games discover their connection at `GET /api/game/config` on their own host. That response includes the public Reverb key and channel prefix. Database passwords and the Reverb secret stay in this app's environment. Per-tenant highscores use `GET`/`POST /api/game/scores` on the same host.

Nginx proxies websocket upgrades on `/app` to the `reverb` container. The host port stays `${APP_PORT}` (8081 by default); Reverb is not published on 8080 because that port is already used by another project. The proxy in front of this stack must forward `Upgrade` and `Connection`.

Seeded logins (password: `password`):

- `admin@jadidaring.test` — administrator
- `client@jadidaring.test` — demo client (`http://demo.jadidaring.test`)

## Admin client API

Machine-to-machine create/update of company clients lives on the admin host. See [docs/admin-api.md](docs/admin-api.md).
