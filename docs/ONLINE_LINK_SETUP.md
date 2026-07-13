# Casa Paraiso Temporary Online Link

## Purpose

This guide publishes the local Docker application through a free Cloudflare Quick Tunnel for demonstrations and remote testing.

The tunnel is not a production deployment. The application, database, and files remain on this computer, and the public link works only while Docker Desktop, the Casa Paraiso containers, the tunnel container, the computer, and the internet connection are running.

## Before Going Online

Open PowerShell in the project directory:

```powershell
cd C:\casa_paraiso
```

Use test data only. Do not expose real customer, payment, or medical information through a temporary development tunnel.

Keep the normal local URL in `.env` so localhost and tunnel requests can both be handled:

```env
APP_URL=http://localhost:8001
ASSET_URL=
```

For a shared online test, disable Laravel debug output:

```env
APP_DEBUG=false
```

Apply environment changes without modifying the database:

```powershell
docker compose exec -T laravel.test php artisan optimize:clear
```

## 1. Start And Prepare The Application

Start the normal Docker services:

```powershell
docker compose up -d
```

Build the frontend assets:

```powershell
docker compose exec -T laravel.test npm run build
```

Confirm that the application works locally before opening a tunnel:

```text
http://localhost:8001
```

Check container status if the local page does not open:

```powershell
docker compose ps
```

## 2. Start The Free Cloudflare Tunnel

The project includes an optional `cloudflared` service under the Compose `tunnel` profile. Start it with:

```powershell
docker compose --profile tunnel up -d cloudflared
```

Read its logs:

```powershell
docker compose logs cloudflared
```

Find the generated address ending in `.trycloudflare.com`, for example:

```text
https://random-words.trycloudflare.com
```

Open that HTTPS address in a browser. Share only this HTTPS address, never the MariaDB or Mailpit ports.

If the URL has not appeared yet, follow the live logs and press `Ctrl+C` after copying it:

```powershell
docker compose logs -f cloudflared
```

Stopping log output with `Ctrl+C` does not stop the tunnel container.

## 3. Configure Google Sign-In

Google requires every OAuth callback URL to match exactly. In Google Cloud Console, open **APIs & Services > Credentials > OAuth 2.0 Client IDs**, select the Casa Paraiso web client, and add the current tunnel callbacks under **Authorized redirect URIs**:

```text
https://random-words.trycloudflare.com/auth/google/callback
https://random-words.trycloudflare.com/profile/delete/google/callback
```

Replace `random-words` with the hostname printed in the tunnel logs. Keep the local callbacks registered as well:

```text
http://localhost:8001/auth/google/callback
http://localhost:8001/profile/delete/google/callback
```

If Google Cloud shows **Authorized JavaScript origins**, the tunnel origin may also be added without a path:

```text
https://random-words.trycloudflare.com
```

The application derives the Google callback from the active request, so `.env` can retain the local fallback:

```env
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

If the OAuth consent screen is in **Testing** status, ensure each tester's Google account is listed under **Test users**. Google configuration changes may take a few minutes to propagate.

Never put the Google client secret in this document, a screenshot, chat, or Git. Keep it only in the ignored local `.env` file.

## 4. Verify The Online Link

Check the following through the HTTPS tunnel URL:

1. The landing page is styled and images load.
2. Registration or Google sign-in reaches Google's consent screen.
3. Login returns to the tunnel URL rather than localhost.
4. Role-specific pages open for the intended test accounts.
5. Browser developer tools show no mixed-content or failed CSS/JavaScript requests.

If the page appears as unstyled HTML, rebuild assets and clear Laravel caches:

```powershell
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test php artisan optimize:clear
```

Then force-refresh the browser with `Ctrl+Shift+R`.

Inspect the application and tunnel logs when troubleshooting:

```powershell
docker compose logs --tail 100 laravel.test
docker compose logs --tail 100 cloudflared
```

## 5. Stop Public Access

Stop and remove only the optional tunnel container:

```powershell
docker compose --profile tunnel stop cloudflared
docker compose --profile tunnel rm -f cloudflared
```

The normal Laravel, MariaDB, and Mailpit containers can continue running locally. To stop the complete Docker stack instead, run:

```powershell
docker compose down
```

Restore local development debug behavior only after public access has stopped:

```env
APP_DEBUG=true
```

Then apply the change:

```powershell
docker compose exec -T laravel.test php artisan optimize:clear
```

## Quick Tunnel Limitations

- The random `trycloudflare.com` URL normally changes whenever the tunnel container is recreated.
- The URL works only while the local machine and tunnel are online.
- Cloudflare provides no uptime guarantee or service-level agreement for Quick Tunnels.
- Quick Tunnels are limited to 200 concurrent in-flight requests.
- Server-Sent Events are not supported.
- The link is public to anyone who knows or receives it.
- Quick Tunnels are intended for development and testing, not production.

Each new random hostname must be added to Google Cloud's authorized redirect URIs before Google sign-in will work through it.

## Stable URL Options

A free Quick Tunnel cannot retain its random hostname. For a stable address, use one of these options:

- Create a named Cloudflare Tunnel and connect a domain managed through Cloudflare.
- Deploy the Laravel application to the planned Hostinger shared/web hosting account.

A named tunnel gives a stable testing hostname, such as `testing.example.com`, but the local computer and Docker services must still remain online. Hostinger is the appropriate direction for an actual production deployment.

## Reference

- [Cloudflare Quick Tunnels](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/)
- [Casa Paraiso Docker workflow](DOCKER_WORKFLOW.md)
- [Casa Paraiso Google authentication setup](GOOGLE_AUTH_SETUP.md)
