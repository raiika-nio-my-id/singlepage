# Admin client API

Machine-to-machine HTTP API for creating and updating company clients (`CompanyProfile`) on the **admin host**. It is not mounted on tenant game hosts.

## Base URL

| Environment | Base URL |
|---|---|
| Local | `http://admin.jadidaring.test/api/admin` |
| Production | `https://admin.jadidaring.com/api/admin` |

Path prefix: `/api/admin`

## Authentication

Send the shared secret on every request:

```
X-Admin-Api-Key: <ADMIN_API_SECRET>
```

| Item | Value |
|---|---|
| Header | `X-Admin-Api-Key` |
| Environment variable | `ADMIN_API_SECRET` |
| Config | `config('singlepage.admin_api.secret')` |

If the header is missing, the secret is empty, or the values do not match, the API returns **401**. Session login is not accepted as a substitute.

Rate limit: **60 requests per minute per IP**.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/clients` | Create a client + company profile |
| `PATCH` | `/clients/{id}` | Partial update by numeric profile id |
| `PATCH` | `/clients/subdomain/{subdomain}` | Partial update by **primary** subdomain |

`{id}` is the `company_profiles.id`. `{subdomain}` is the current primary subdomain (not an alias).

## Fields

Only fields that already exist on `CompanyProfile` / the client `User` / aliases. Omitted fields are left unchanged on PATCH.

| Field | Type | Create | Update | Notes |
|---|---|---|---|---|
| `name` | string (max 255) | required | optional | Client account name |
| `email` | string email (max 255) | required, unique | optional, unique | Client login email |
| `password` | string | optional | optional | Create: random 24-char password if omitted. Update: omit to keep the current password |
| `subdomain` | string (max 63, DNS label) | required | optional | Unique across primaries + aliases; reserved names rejected |
| `html` | string | required | optional | Published snippet / HTML |
| `status` | `"draft"` \| `"published"` | optional, default `draft` | optional | Published status |
| `expires_at` | date `YYYY-MM-DD` or `null` | optional | optional | Create default: today + `SUBSCRIPTION_YEARS` (usually 1). Update: `null` clears expiry |
| `hide_footer` | boolean | optional, default `false` | optional | Hide platform footer |
| `is_pinned` | boolean | optional, default `false` | optional | Dashboard pin |
| `sort_order` | integer ≥ 0 | optional, default `0` | optional | Dashboard sort |
| `aliases` | string[] | optional | optional | When sent, **replaces** the alias set. Each value is a subdomain |

Changing `subdomain` reuses the admin UI swap: the former primary is kept as an alias (so the old host 301s). If `aliases` is also sent in the same request, that array is the exact resulting set.

## Create example

```http
POST /api/admin/clients HTTP/1.1
Host: admin.jadidaring.com
X-Admin-Api-Key: <ADMIN_API_SECRET>
Accept: application/json
Content-Type: application/json

{
  "name": "Acme Corp",
  "email": "acme@example.com",
  "password": "A-strong-pass-123",
  "subdomain": "acme",
  "html": "<!doctype html><html><body><h1>Acme</h1></body></html>",
  "status": "published",
  "expires_at": "2027-10-27",
  "hide_footer": false,
  "is_pinned": false,
  "aliases": ["acme-old"]
}
```

**201** response:

```json
{
  "message": "Client created.",
  "data": {
    "id": 12,
    "subdomain": "acme",
    "name": "Acme Corp",
    "email": "acme@example.com",
    "status": "published",
    "expires_at": "2027-10-27",
    "hide_footer": false,
    "is_pinned": false,
    "sort_order": 0,
    "html": "<!doctype html><html><body><h1>Acme</h1></body></html>",
    "aliases": ["acme-old"],
    "public_url": "https://acme.jadidaring.com",
    "created_at": "2026-10-27T12:00:00+00:00",
    "updated_at": "2026-10-27T12:00:00+00:00"
  }
}
```

The password is never returned.

## Partial update example

```http
PATCH /api/admin/clients/12 HTTP/1.1
Host: admin.jadidaring.com
X-Admin-Api-Key: <ADMIN_API_SECRET>
Accept: application/json
Content-Type: application/json

{
  "html": "<!doctype html><html><body><h1>Updated</h1></body></html>",
  "status": "published",
  "expires_at": "2028-01-01"
}
```

Or by subdomain:

```http
PATCH /api/admin/clients/subdomain/acme HTTP/1.1
Host: admin.jadidaring.com
X-Admin-Api-Key: <ADMIN_API_SECRET>
Accept: application/json
Content-Type: application/json

{
  "is_pinned": true,
  "aliases": ["acme-old", "acme-id"]
}
```

**200** response: same `data` shape as create, with `"message": "Client updated."`

## Error codes

| Status | When | Body |
|---|---|---|
| `401` | Missing, empty, or wrong `X-Admin-Api-Key` | `{"message":"Unauthorized."}` |
| `404` | Unknown id or primary subdomain | `{"message":"Client not found."}` |
| `422` | Validation failure, reserved/taken subdomain, email taken, alias conflict, empty PATCH | `{"message":"...","errors":{...}}` |
| `429` | More than 60 requests/minute from the same IP | Laravel throttle JSON |

Example uniqueness conflict:

```json
{
  "message": "This subdomain is already in use.",
  "errors": {
    "subdomain": ["This subdomain is already in use."]
  }
}
```

Empty PATCH:

```json
{
  "message": "At least one updatable field is required.",
  "errors": {
    "payload": ["At least one updatable field is required."]
  }
}
```
