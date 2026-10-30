# Deploying the public demo

One small Linux server runs everything: the app (PHP-FPM and nginx), a scheduler that rebuilds the demo every night,
MySQL 8, and Caddy, which gets the HTTPS certificate on its own. The demo holds only the fictional company, so there
is nothing to back up: `hris:demo --fresh` recreates it from scratch.

## 1. A server

**Oracle Cloud Always Free** (no monthly cost; signing up needs a card for verification):

1. Create a Compute instance: image **Ubuntu 24.04**, shape **VM.Standard.A1.Flex** (Arm). 1 OCPU and 6 GB of memory
   is plenty. Add your SSH public key.
2. In the instance's subnet, open the **security list** and add ingress rules for TCP **80** and **443** from
   `0.0.0.0/0`.
3. Oracle's Ubuntu images also block those ports in the server's own firewall. On the server:

   ```sh
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
   sudo netfilter-persistent save
   ```

If Oracle has no Arm capacity in your region, any small VPS works the same way from step 2 below (for example a
Hetzner CX22, about EUR 4 a month); there, only the provider's firewall needs ports 80 and 443 open.

## 2. Docker

```sh
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # then log out and back in
```

## 3. The app

```sh
git clone https://github.com/vincealwynsibay/human-resource-information-system.git talia
cd talia/deploy
cp .env.example .env
docker compose build
docker compose run --rm --no-deps -e AUTORUN_ENABLED=false app php artisan key:generate --show   # paste the output into APP_KEY
```

Fill in `deploy/.env`:

| Setting | Value |
|---|---|
| `DOMAIN` | Your domain, or the server's public IP with sslip.io: `203.0.113.7.sslip.io` |
| `APP_URL` | `https://` followed by the same address |
| `SANCTUM_STATEFUL_DOMAINS` | The same address, without `https://` |
| `DB_PASSWORD` | A long random password |
| `APP_KEY` | From the command above |

Then start it and load the demo company (about a minute):

```sh
docker compose up -d
docker compose exec app php artisan hris:demo --fresh
```

Open `https://<DOMAIN>`. The sign-in page offers the admin, approver and employee logins.

## Updating

```sh
cd talia && git pull
cd deploy && docker compose up -d --build
```

## Notes

- **Nightly rebuild.** The `scheduler` container runs `hris:demo --fresh` at 03:00 Manila time (`HRIS_DEMO_RESET_AT`).
  While `HRIS_DEMO=true`, registering, password changes and resets, and deleting logins are refused, so one visitor
  cannot lock the others out.
- **Database user.** The HR schema's 156 stored procedures are defined by `root`, so the app connects as root. MySQL is
  reachable only inside the Docker network. This goes away as the HR side moves to Eloquent and the procedures are retired.
- **PDFs** are rendered by the Chromium installed in the image (`LARAVEL_PDF_*` settings in the Dockerfile).
- **Logs** go to the container output: `docker compose logs -f app`.
