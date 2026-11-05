# Singlepage

Laravel 13 publisher for one-page client sites.

- Admin: `http://admin.jadidaring.test`
- Published page: `http://www.jadidaring.test/{subdomain}/`
- `http://{subdomain}.jadidaring.test/...` returns 301 to that path and keeps the path and query string
- GameHub directory: `http://www.jadidaring.test/gamehub/` (the live profile whose subdomain is `gamehub`). `http://gamehub.jadidaring.test/...` returns 301 to that path. Dedicated `/gamehub` routes stay ahead of the generic game path.
- The apex host is the marketing site and is not this app
- Unknown names return 404. Reserved labels (`www`, `admin`, `api`, `app`, `apps`, `reverb`, `up`, …) are not game paths
- Reverb stays at `ws://www.jadidaring.test/app/{key}` (nginx `/app` on the public host). It is not under `/{subdomain}/app`. Old subdomain `/app` upgrades still reach Reverb when that DNS name points at this stack; Laravel never sees them, so they are not 301s
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

Mahjong, Exploding Kittens, and every later game share one MySQL database and one Laravel Reverb websocket. The subdomain label is the isolation key: `www.jadidaring.test/mahjong` only sees `game.mahjong` rooms, and `www.jadidaring.test/explodingkitten` only sees `game.explodingkitten` rooms.

Published games discover their connection at `GET /{subdomain}/api/game/config`. That response includes the public Reverb key and channel prefix. The websocket URL points at `/app` on the www host. Database passwords and the Reverb secret stay in this app's environment. Per-tenant highscores use `GET`/`POST /{subdomain}/api/game/scores`.

Nginx proxies websocket upgrades on `/app` to the `reverb` container. The host port stays `${APP_PORT}` (8081 by default); Reverb is not published on 8080 because that port is already used by another project. The proxy in front of this stack must forward `Upgrade` and `Connection`.

Seeded logins (password: `password`):

- `admin@jadidaring.test` — administrator
- `client@jadidaring.test` — demo client (`http://www.jadidaring.test/demo/`)

## Admin client API

Machine-to-machine create/update of company clients lives on the admin host. See [docs/admin-api.md](docs/admin-api.md).
