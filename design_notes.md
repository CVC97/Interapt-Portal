# Design notes — Portal (landing page, login, API)

What was built and why: the decisions behind the shared login and backend of the learning modules, for anyone (or any Claude session) who extends it. **How to** run, deploy and maintain it is in `README.md`.

Built by Carlo (physics education group, Göttingen) with Claude, 2026-11-17, from the backend of the Pohl module (`../ilias-pohl-module/`, its `design_notes.md` section 6 has the data rules this keeps: events not snapshots, idempotent saves, UTC times). The landing page was `../index.html` before.


## 1 Status (2026-11-17)

Built and tested locally (API with curl, the whole flow in headless Chrome with both modules); not deployed. The codename scheme and the consent text are placeholders (section 6).


## 2 Decisions

| Decision | Why |
|---|---|
| **One login for all modules** (Carlo) | Before, each module had its own users and its own bridge page: a student registered twice, and nothing tied the two accounts together. All modules run on one origin, so one cookie with `Path=/` covers them. |
| **One backend in its own repository, at the server root** | The PHP and the schema were byte-identical copies in both modules and had to be kept in sync by hand; the login page and the landing page needed a home of their own anyway (the landing page wasn't under version control). The modules call `../api/` relative to their base href. |
| **Course key per semester (cohort), not per module** | The key keeps external users from creating tracked accounts; per semester it also stores the cohort with the account. Usernames are unique per cohort, so a repeating student starts a fresh account next year instead of finding last year's answers. |
| **Guests stay**: no key, or no consent, means guest | External users must be able to use the modules without being tracked. Guests get a generated id in the browser; nothing of theirs reaches the server. |
| **Free username plus a personal code (codename)** | The surveys identify students by a code built from personal facts (birth month, …). The modules' data must match the surveys without names. The code can't be the account's key: with ~10⁴ combinations, codes collide within a cohort of 150–200 (birthday problem), so the free username stays unique and the code is stored with it. |
| **The code is built in the browser; only the code is sent** | The facts it is made of (birth month, place, the mother's birth month) never reach the server. |
| **No password; the code confirms a login on a new device** (Carlo: no passwords stored) | The code doubles as a light PIN. Carlo's first idea was to show the stored code's meaning to a returning user ("Ihre Mutter ist im März geboren – sind Sie das?"); rejected because anyone typing a classmate's username would see their personal facts. Instead a new account reads back *what the student just typed*, and a login on a new device asks for the code again; the server only compares (`hash_equals`), never sends it. A wrong username and a wrong code give the same answer. |
| **Consent on account creation, stored with a time** (`users.consent_at`) | The data is research data matched to surveys. Without consent: no account, the guest way. |
| **Signed cookie, no session table** | `userId.issuedAt.HMAC(auth_secret)`, HttpOnly, SameSite=Lax, 200 days (a semester and a bit). `load` and `save` need it, so knowing a username no longer opens anyone's data, and usernames left the URLs (`?session_id=`, `/api/progress/{username}`). Changing `auth_secret` logs everyone out. |
| **The module asks `api/auth/me` on startup, before its tracking services exist** | The modules' services read their stored state once, keyed by the student id. An app initializer settles the id first (`Session.resolve()`); a `localStorage` hand-over from the login page wouldn't work locally, where login (:8000) and module (:4200) are different origins. Server unreachable: the module keeps the id it had, so answers are queued and sent later. |
| **Landing page cards go through the login page** unless logged in | Students should not end up as guests by accident; the login page offers the guest way right there. |
| **Same look as the modules** | Tokens, header, footer and buttons as in the modules (`assets/portal.css`, shared by both pages). The login is one narrow column with one step at a time; the main button is greyed out, never hidden, until the step is complete. |


## 3 The login flow

1. Course key + username → `auth/check` (wrong key: on the key field; invalid characters: on the username).
2. The code's parts as the surveys ask them (`login/codename.js`), with the resulting code shown live.
3. Known username → `auth/login`. New username → read-back of the answers as sentences ("Sie sind im Juli geboren.") and the code → consent → `auth/register` (409 if the name was taken meanwhile: back to step 1).
4. On to `next` (the module) or the landing page. Already logged in with `next`: straight on.


## 4 Data

Schema in `api/schema.sql`. `users` is global (cohort, username, codename, consent_at); `page_visits`, `question_attempts` and `effort_ratings` carry `module_id` in their unique keys. `effort_ratings` (2026-11-24) holds the modules' mental-effort pop-up: one row per unit and student (the first answer counts), rating 1–9 or NULL for „Keine Angabe“, `shown_at`/`answered_at` for the response time, `context` JSON with the way the unit was taken. `progress/save` takes them as `ratings`, `progress` returns the rated units as `ratedUnits`. It is a table of its own because a rating has no `is_correct`; mixed into `question_attempts` it would distort the analysis. A module saves only when listed under `modules` in `config.php`; its `modules` row is created on its first save. `last_active` is set explicitly on save and login, not `ON UPDATE` (a failed login is no activity).


## 5 Pitfalls

- **`php -S` is single-threaded:** Chrome keeps an idle connection open and the next request hangs. Run it with `PHP_CLI_SERVER_WORKERS=4`.
- **Puppeteer and 401:** a page whose request got a 401 (a guest's `api/auth/me`) never reaches `networkidle0`; wait for `load` and the element.
- **The root `.htaccess` doesn't reach the modules:** their own `.htaccess` turns the rewrite engine on and replaces it below `/pohl/`. Direct requests to `api/*.php` are refused (403); only the routes reach the PHP.


## 6 Open items

- **Codename scheme (before the first course use):** `login/codename.js` holds Carlo's example. Replace it with the surveys' scheme and wording, part by part. In the example, the first two letters of month names don't tell Juni/Juli and März/Mai apart.
- **Consent text:** placeholder in `login/index.html`; to be agreed with the data protection officer, with a link to a privacy notice, and a retention rule.
- **Guessing:** no rate limit on `auth/check` (course key) or on `auth/login` (code). The lock after 10 wrong codes was removed (2026-11-17), so with the key and a username, a code with ~10⁴ combinations can be tried through.
- **`effort_ratings` on the server:** create the table (`schema.sql`; `CREATE TABLE IF NOT EXISTS`, so running it again only adds it) and deploy `save.php` before the modules that send ratings: an older `save.php` ignores them and answers 200, so the modules would forget them as sent. Not tested against MySQL yet.
- **First deploy:** order and steps in the README (*First-time server setup*): the portal and its `config.php` before the modules' next deploy, which removes their old `api/`.
