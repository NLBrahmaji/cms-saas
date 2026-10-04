# SitePro Dashboard

Authenticated customer dashboard for SitePro (`apps/dashboard`).

## Local development

- **Dashboard:** [http://localhost:3001](http://localhost:3001)
- **Laravel API:** [http://localhost:8000](http://localhost:8000)

The dashboard calls the Laravel API **directly** from the browser (application
routes under `/v1`; Sanctum CSRF at `/sanctum/csrf-cookie`). Laravel Sanctum
session/CSRF and CORS are already configured to allow credentialed requests from
`http://localhost:3001`.

1. Copy environment configuration:

   ```bash
   cp .env.example .env.local
   ```

   Required in `.env.local`:

   ```dotenv
   NEXT_PUBLIC_API_URL=http://localhost:8000
   ```

2. Start the Laravel API from `backend/api` (for example `composer serve`).

3. Install dependencies and start the dashboard (defaults to port **3001**):

   ```bash
   npm install
   npm run dev
   ```

4. Open [http://localhost:3001](http://localhost:3001).

Use `localhost` consistently (not `127.0.0.1`) so Sanctum session cookies work with the API.

## Scripts

- `npm run dev` — development server on port 3001
- `npm run build` — production build
- `npm run start` — production server
- `npm run lint` — ESLint
