# Interaktive Lernmodule – Portal

The server root of `https://interapt.uni-goettingen.de/`: the landing page, the **login shared by all learning modules**, and the **PHP API and database schema** they save their progress through. The modules themselves ([Ilias-Pohl-Module](https://github.com/CVC97/Ilias-Pohl-Module), [Ilias-Precession-Module](https://github.com/CVC97/Ilias-Precession-Module)) live below it (`/pohl/`, `/precession/`) and are deployed from their own repositories.

Why it is built this way: `design_notes.md`.


## Structure

```
index.html                  landing page: the modules, the login state
login/
    index.html              login page: course key, username, personal code, consent (/login/?next=pohl)
    codename.js             the personal code's scheme (PLACEHOLDER until the surveys' scheme is fixed)
assets/
    portal.css              colours, header, footer, buttons and fields of both pages
api/
    config.php              DB credentials, course keys, cookie secret, module list (gitignored, server only)
    config.example.php      template for config.php
    lib.php                 shared by all endpoints: responses, input checks, course keys, login cookie
    db.php                  PDO connection (session in UTC)
    router.php              local development router (php -S only)
    schema.sql              database schema
    auth/                   check, register, login, logout, me
    progress/               load.php (GET /api/progress), save.php
.htaccess                   production routing of /api/* at the server root
docker-compose.yml          local MySQL (development only)
.github/workflows/deploy.yml   upload to the server root on push to master
```


## API

All JSON. The login cookie (`interapt_auth`, HttpOnly, `Path=/`) is set by `register` and `login` and identifies the student in every other call.

| Method | Endpoint | Body / query | Answer |
|---|---|---|---|
| POST | `/api/auth/check` | `{key, username}` | `{exists}`; 401 wrong key, 400 invalid username |
| POST | `/api/auth/register` | `{key, username, codename, consent: true}` | 201 `{username, cohort}` + cookie; 409 username taken |
| POST | `/api/auth/login` | `{key, username, codename}` | 200 `{username, cohort}` + cookie; 401 no match |
| POST | `/api/auth/logout` | – | clears the cookie |
| GET | `/api/auth/me` | – | `{username, cohort}`; 401 = not logged in (guest) |
| GET | `/api/progress?module=pohl` | – | `{pageVisits, attempts}` of that module, the latest attempt per question |
| POST | `/api/progress/save` | `{module, visits, attempts}` | `{ok, skipped}`; a row sent twice is stored once |

`module` must be listed under `modules` in `api/config.php` (400 otherwise). The usernames' allowed characters: letters, digits and `. _ - @`.


## Running locally

```bash
cp api/config.example.php api/config.php       # first time: fill in a course key and an auth_secret
sudo docker compose up -d                      # MySQL with the schema (port 3306)
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 api/router.php
```

Open `http://localhost:8000/` (landing page) or `http://localhost:8000/login/?next=pohl`. The modules run with their own `ng serve` (Pohl on 4200, precession on 4201, as `MODULES` in `login/index.html` expects); their dev proxies forward `/api` to port 8000. Cookies don't depend on the port, so the login on :8000 also counts on :4200 and :4201.

`PHP_CLI_SERVER_WORKERS`: `php -S` alone is single-threaded and hangs on an idle connection that Chrome keeps open; the login page's requests then never return.


## Deploying

Pushing to `master` runs `.github/workflows/deploy.yml`: it uploads `index.html`, `.htaccess`, `assets/`, `login/` and `api/` (without `config.php` and the development files) to the server root, and never touches the modules' folders. Secrets: `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASS` as for the modules, and `SFTP_ROOT_DIR`, the server root's path.

### First-time server setup (once, before the modules' next deploy)

The modules' deploys now remove their old `pohl/api/` and `precession/api/` from the server, including the `config.php` with the database credentials. So, in this order:

1. Copy the database credentials from the server's `pohl/api/config.php` into a new `api/config.php` at the server root (from `config.example.php`). Add the course keys (`cohort_keys`), a new `auth_secret` (`php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`) and the module list.
2. In phpMyAdmin, database `interapt`: drop the old tables (only test data, decided 2026-11-17), then run `api/schema.sql` without its first two lines:
   ```sql
   DROP TABLE IF EXISTS question_attempts, page_visits, users, modules;
   ```
3. Deploy the portal (push or run the workflow), then check `https://interapt.uni-goettingen.de/api/auth/me` answers `{"error":"not logged in"}`.
4. Deploy the modules. Links from ILIAS go to `https://interapt.uni-goettingen.de/` or straight to `https://interapt.uni-goettingen.de/login/?next=pohl` (and `next=precession`).


## Maintenance

**A new semester:** add its key to `cohort_keys` in the server's `api/config.php`, e.g. `'ss27' => '…'`. Accounts belong to the semester whose key created them; the same username can be taken again in the next semester (repeating students start fresh). Remove an old key once its students no longer need to log in on a new device; existing logins (cookies) keep working until they expire (200 days).

**A new module:** add its name to `modules` in `api/config.php` (locally and on the server), to `MODULES` in `login/index.html` (title, local port), and a card on `index.html`; the module sets the same name as `MODULE_NAME`.

**Delete a student and all their data** (visits and attempts cascade):
```sql
DELETE FROM users WHERE cohort = 'ws2627' AND username = 'resonanz42';
```

**Match with the surveys:** `users.codename` is the survey code; one student's data across modules:
```sql
SELECT u.codename, m.name AS module, a.unit_id, a.question_id, a.attempt_no, a.is_correct, a.answered_at
FROM question_attempts a
JOIN users u ON u.id = a.user_id
JOIN modules m ON m.id = a.module_id
WHERE u.cohort = 'ws2627'
ORDER BY u.codename, a.answered_at;
```
Codes are not unique by design (few combinations); check duplicates within a cohort with `SELECT codename, COUNT(*) FROM users WHERE cohort = 'ws2627' GROUP BY codename HAVING COUNT(*) > 1`.
