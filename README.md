# BA Toolkit

BA Toolkit is a Business Analysis workspace for requirement intake, process analysis, data design, AI-assisted normalization, and consolidated reporting by department or project.

## Open Source Metadata

- License: Apache-2.0
- Security contact: `namxp2@gmail.com`
- Brand: `vannamdigital`
- Funding: see [FUNDING.yml](/d:/da_it/ba/.github/FUNDING.yml)

## Support the Project

If this project is useful for your team or your own work, you can support its maintenance and future templates:

- PayPal: `https://paypal.me/vannamdigital`
- Contact: `namxp2@gmail.com`

For supporters in Vietnam, you can use the QR code below:

![Donate QR for Vietnam](https://raw.githubusercontent.com/LamVAnAM/photo/main/2113021916710110173.jpg)

## Core Features

- User authentication, authorization, and user-level data isolation
- Department / project management
- Survey Form for structured requirement capture
- AI Intake, AI Normalize, AI Toolkit, and AI-assisted reporting
- Process Mapping for AS-IS / TO-BE workflows
- Data Architecture Designer with entities, attributes, relationships, dictionary, import/export
- Integration, Backlog, Reports, and supporting BA modules
- Section-based image uploads with metadata and private file serving

## Technology Stack

- Backend: PHP
- Database: SQLite in `database/ba.sqlite`
- Frontend: HTML, CSS, JavaScript
- Main Composer packages:
  - `vlucas/phpdotenv`
  - `ramsey/uuid`
  - `monolog/monolog`
  - `opis/json-schema`

## Local Setup

1. Install dependencies:

```bash
composer install
```

2. Create `.env` from `.env.example`. At minimum:

```env
APP_ENV=local
APP_KEY=replace_with_a_secret_key_at_least_32_characters
```

3. Run the local server:

```bash
php -S localhost:8000
```

4. Open:

```text
http://localhost:8000
```

## Running Under a Subpath

The application supports subpath deployment, for example:

```text
https://example.com/ba-toolkit/
```

You can place the full source code inside a subfolder such as `public_html/ba-toolkit/`, and the application will automatically resolve:

- `assets/`
- `modules/`
- `api/`
- verification and password reset links

No hardcoded URL rewrite is required.

## Running at Root Domain with Docker

Included files:

- [Dockerfile](/d:/da_it/ba/Dockerfile)
- [docker-compose.yml](/d:/da_it/ba/docker-compose.yml)

Start the stack:

```bash
docker compose up --build -d
```

Then open:

```text
http://localhost:8080
```

Notes:

- `database/`, `private_uploads/`, and `storage/` are mounted to the host for persistence
- set a real `APP_KEY` in `.env` or `docker-compose.yml`
- the entrypoint creates writable directories for SQLite and storage automatically

## Recommended Usage Flow

1. Register or sign in
2. Create a department / project in `Organization`
3. Open `Survey Form` and fill each section
4. Select solution modules in `Module Proposal`
5. Use AI to normalize or structure raw input when needed
6. Upload supporting images per section
7. Complete `Process Mapping`, `Data Architecture`, `Integration`, and `Backlog`
8. Generate and export reports from `Reports`

## AI Configuration

Supported providers:

- Groq API
- Ollama / local endpoint
- OpenAI-compatible endpoints

Users manage their own AI key and model in `AI Toolkit`.
Admins manage system-wide settings in `Configuration`.

Notes:

- In local environments without valid certificates, SSL verification can be disabled for AI requests
- Secrets such as `AI API key`, `Groq API key`, and `SMTP password` are encrypted at rest using `APP_KEY`

## Upload / Storage and S3 Readiness

Section images are stored in private local storage by default:

```text
private_uploads/files/{user_id}/{project_id}/{section_id}/{filename}
```

Files are no longer served directly from a public path. Access goes through an authenticated API layer.

Stored metadata in `section_files` includes:

- `user_id`
- `department_id`
- `section_id`
- `storage_disk`
- `storage_path`
- `original_name`
- `mime_type`
- `file_size`
- `width`
- `height`
- `checksum_sha256`
- `av_scanned`
- `av_status`
- `created_at`

S3-related settings are already prepared:

- `STORAGE_DRIVER`
- `S3_BUCKET`
- `S3_REGION`
- `S3_ENDPOINT`
- `S3_PREFIX`

## Security Notes

- CSRF protection is applied to state-changing APIs
- Sessions are hardened with `HttpOnly`, `SameSite=Lax`, and `Secure` under HTTPS
- `session_regenerate_id(true)` is applied on login
- Uploads only allow `jpg`, `png`, and `webp`
- Uploaded images are MIME-checked, validated as real images, and re-encoded/resized before storage
- ClamAV integration can be enabled for antivirus scanning
- Security headers are set in bootstrap:
  - `Content-Security-Policy`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy`
  - `X-Frame-Options`

## Important Directories

- `modules/`: UI modules
- `api/`: backend endpoints
- `assets/js/app.js`: main frontend logic
- `assets/css/style.css`: global styling
- `config/`: bootstrap, helpers, database, auth
- `private_uploads/`: private local uploads
- `database/ba.sqlite`: SQLite database

## Operational Notes

- All business data is isolated per user
- `Configuration` is only visible to admins
- If `APP_KEY` is missing, encrypted secret settings cannot be stored

## Template-First Extension Model

The project is organized around a template-first model so it can be shared on GitHub and extended by contributors.

- Templates live in `templates/<module>/<template_key>/template.json`
- Core code auto-loads templates through `api/templates.php`
- UI modules render templates without requiring hardcoded additions in JS/PHP for normal template contributions

Recommended contribution flow:

1. Create a new template under `templates/`
2. Commit using a clear convention, for example:
   - `feat(template): add erp integration hub template`
3. Open a Pull Request
4. Review payload, schema, screenshots, and compatibility before merge

See also:

- [CONTRIBUTING.md](/d:/da_it/ba/CONTRIBUTING.md)
- [templates/README.md](/d:/da_it/ba/templates/README.md)

## Public Repository Checklist

This repository already includes the basic files needed for a public GitHub release:

- [LICENSE](/d:/da_it/ba/LICENSE)
- [SECURITY.md](/d:/da_it/ba/SECURITY.md)
- [CONTRIBUTING.md](/d:/da_it/ba/CONTRIBUTING.md)
- [.env.example](/d:/da_it/ba/.env.example)
- [.gitignore](/d:/da_it/ba/.gitignore)
- [ci.yml](/d:/da_it/ba/.github/workflows/ci.yml)

Recommended before pushing:

1. Do not commit `.env`, real SQLite databases, uploads, logs, or temporary files
2. Use `.env.example` as the contributor starting point
3. Run `php scripts/validate_templates.php`
4. Run PHP syntax checks if you changed core files
