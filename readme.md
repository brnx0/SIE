# SIE — Guia de Deploy (Homologação / Novo Cliente)

Guia completo para subir o sistema **SIE** em uma máquina nova ou para provisionar um
**novo cliente**. Cobre arquitetura, pré-requisitos, configuração, build, proxy reverso
(Nginx do host + Cloudflare), seeds e troubleshooting dos problemas já conhecidos.

> Stack: **Laravel 12 (PHP 8.3-fpm)** + **PostgreSQL 16** + **Nginx (no HOST)** + **Vite**.
> Orquestração: `docker compose` via [`docker-compose.homolog.yml`](../docker-compose.homolog.yml).

---

## 1

**Pontos-chave do design:**

- **Não há Nginx no compose.** O Nginx é o do **host** e faz `fastcgi_pass` direto no
  php-fpm do container (`127.0.0.1:9000`).
- A pasta **`./public` é bind-mount** do host para o container. O `entrypoint` re-sincroniza
  os assets buildados (`public/build`) no boot, então o Nginx do host sempre serve assets atuais.
- O **php-fpm é exposto só no loopback** (`127.0.0.1:9000`). Nada do app fica exposto direto à internet.
- O **Postgres** fica na rede interna do compose (`db:5432`); dentro do container `DB_HOST=db`
  é sobrescrito pelo compose (ignora o `DB_HOST` do `.env`).
- A imagem é buildada com **`composer install --no-dev`** → **não há `fakerphp/faker`** em runtime.

---

## 2. Pré-requisitos da máquina

- Docker Engine + plugin `docker compose` (v2).
- **Nginx instalado no host** (`apt install nginx`).
- **Certbot** para TLS no origin (`apt install certbot python3-certbot-nginx`), se for usar HTTPS direto.
- Portas **80** e **443** liberadas no firewall do host e no provedor (Security Group/VCN).
- DNS do domínio do cliente apontando para o servidor (direto ou via Cloudflare).

---

## 3. Passo a passo — nova máquina / novo cliente

### 3.1 Clonar e preparar o `.env`

```bash
cd /opt
git clone <repo> sie_<cliente>        # ex.: sie_coruripe
cd sie_<cliente>
cp .env.example .env                  # ou copie um .env de referência
```

Edite o `.env` (ver §4). **Obrigatório por cliente:** `APP_URL`, credenciais de DB, e gerar `APP_KEY`.

### 3.2 Gerar a `APP_KEY` (CRÍTICO)

Sem `APP_KEY` o app retorna **HTTP 500** (`No application encryption key has been specified`).

```bash
# após subir o container (3.3) você pode gerar e gravar no .env do host:
KEY=$(docker compose -f docker-compose.homolog.yml exec -T app php artisan key:generate --show)
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
docker compose -f docker-compose.homolog.yml up -d app   # recria p/ recarregar o .env
```

> O `.env` **não é bind-mount** — é lido via `env_file` na criação do container.
> Sempre que mudar o `.env`, recrie o container app (`up -d app`).

### 3.3 Build e subir a stack

```bash
docker compose -f docker-compose.homolog.yml up -d --build
```

Isso sobe `db` (espera ficar *healthy*) e `app`. O `entrypoint` do app, no boot:

1. Re-sincroniza `public/` (assets).
2. Cria `storage:link`.
3. Roda `php artisan migrate --force` (se `RUN_MIGRATIONS=true`, que é o default).
4. Gera caches de produção (`config/route/view/event:cache`).

### 3.4 Rodar os seeders (dados de referência + admin)

As **migrations já rodam no boot**, mas os **seeders não** — rode manualmente:

```bash
docker compose -f docker-compose.homolog.yml exec app php artisan db:seed --force
```

Popula: municípios (5.571), segmentos, séries, cargos, disciplinas, conceitos, etc.,
e cria o usuário admin (ver §7). Em homolog/prod (sem faker) os usuários fictícios são
**pulados automaticamente** (guard `class_exists(\Faker\Factory::class)`).

> **Banco do zero:** `php artisan migrate:fresh --seed --force` (⚠️ **apaga todas as tabelas**).

### 3.5 Configurar o Nginx do host (§5) e o Cloudflare (§6).

---

## 4. Variáveis de ambiente (`.env`)

| Variável | Valor recomendado (homolog/prod) | Observação |
|---|---|---|
| `APP_ENV` | `production` | Em dev/local use `local`. |
| `APP_DEBUG` | `false` | `true` vaza stack traces — só em dev. |
| `APP_KEY` | *(gerar — §3.2)* | **Sem isto → HTTP 500.** |
| `APP_URL` | `https://<dominio-do-cliente>/` | **Trocar por cliente.** |
| `APP_LOCALE` | `pt_BR` | |
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` | `127.0.0.1` | Dentro do container é sobrescrito p/ `db`. |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `sie_<cliente>` | **Trocar por cliente.** |
| `DB_USERNAME` | `sie_<cliente>` | **Trocar por cliente.** |
| `DB_PASSWORD` | *(senha forte)* | **Trocar por cliente.** |
| `FORCE_HTTPS` | `true` | Middleware `ForceHttps` força https. |
| `SESSION_SECURE_COOKIE` | `true` | Requer HTTPS ativo. |
| `SESSION_DRIVER` / `QUEUE_CONNECTION` / `CACHE_STORE` | `database` | |
| `MAIL_*` | *(SMTP do cliente)* | Ajustar por cliente. |

> O compose lê `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` e `FPM_PORT`
> também para configurar o container do Postgres e a porta do php-fpm.
> Para rodar **vários clientes na mesma máquina**, dê a cada um um `FPM_PORT` diferente
> (ex.: 9000, 9001, ...) e ajuste o `fastcgi_pass` do respectivo `server` do Nginx.

---

## 5. Nginx do host

O projeto traz um **exemplo** em [`docker/nginx/default.conf`](../docker/nginx/default.conf).
Copie para o host e ajuste `server_name` / `root` / certificados.

```bash
# Ex.: editar /etc/nginx/sites-available/<cliente> e habilitar:
ln -s /etc/nginx/sites-available/<cliente> /etc/nginx/sites-enabled/<cliente>
nginx -t && systemctl reload nginx
```

Modelo de `server` (HTTPS no origin + guard anti-loop do Cloudflare):

```nginx
# Redireciona HTTP real -> HTTPS, mas SEM gerar loop quando vier do Cloudflare.
server {
    listen 80;
    listen [::]:80;
    server_name <dominio-do-cliente>;

    # Cloudflare em modo "Flexible" conecta no origin via :80 com
    # X-Forwarded-Proto: https. Um "return 301 https" cego vira LOOP infinito.
    # Só redireciona requisições http REAIS; do CF (https) serve a app.
    set $do_redirect 1;
    if ($http_x_forwarded_proto = "https") { set $do_redirect 0; }
    if ($do_redirect = 1) { return 301 https://$host$request_uri; }

    root /opt/sie_<cliente>/public;
    index index.php;
    charset utf-8;
    client_max_body_size 25M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;   # = FPM_PORT do cliente
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        # CRÍTICO: caminho DENTRO do container (= bind-mount ./public).
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   /var/www/html/public;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|map)$ {
        expires 30d; access_log off; try_files $uri =404;
    }
    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name <dominio-do-cliente>;

    ssl_certificate     /etc/letsencrypt/live/<dominio-do-cliente>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<dominio-do-cliente>/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /opt/sie_<cliente>/public;
    index index.php;
    charset utf-8;
    client_max_body_size 25M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   /var/www/html/public;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|map)$ {
        expires 30d; access_log off; try_files $uri =404;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

### Certificado TLS no origin (Certbot)

```bash
certbot --nginx -d <dominio-do-cliente>
```

---

## 6. Cloudflare

Se o domínio estiver atrás do Cloudflare (proxy laranja):

- **SSL/TLS → Overview → modo `Full (strict)`** (recomendado, pois o origin tem cert válido).
  - No modo **`Flexible`**, o Cloudflare conecta no origin via **:80** e o redirect cego
    `http → https` gera **loop infinito** (`ERR_TOO_MANY_REDIRECTS`). O guard anti-loop do §5
    evita isso, mas `Full (strict)` é o correto.
- Garanta que o app confia no proxy (já configurado em
  [`bootstrap/app.php`](../bootstrap/app.php) → `trustProxies(at: '*', ... X-Forwarded-Proto)`),
  para `$request->secure()` e o `ForceHttps` funcionarem corretamente.

---

## 7. Usuário administrador (seed)

O `DatabaseSeeder` cria:

| Campo | Valor |
|---|---|
| login | `admin` |
| email | `admin@sie.local` |
| senha | `123456` |
| role | `admin` (na tabela `user_roles`) |

> ⚠️ **Troque a senha do admin** após o primeiro acesso em qualquer ambiente exposto.

**Modelo de roles:** não existe coluna `role` em `users`. As roles ficam na tabela
**`user_roles`** (pivot). Use `$user->syncRoles(['admin', ...])`. Roles válidas em
`App\Models\User::ROLES`: `professor`, `professor_aee`, `coordenador`,
`coordenador_interno`, `diretor`, `secretaria_escola`, `admin`.

---

## 8. Comandos do dia a dia

```bash
# atalho: a partir da pasta do projeto
DC="docker compose -f docker-compose.homolog.yml"

$DC exec app php artisan migrate:status      # estado das migrations
$DC exec app php artisan migrate --force     # aplicar migrations
$DC exec app php artisan db:seed --force     # rodar seeders
$DC exec app php artisan optimize:clear      # limpar caches
$DC exec app php artisan tinker              # REPL (use -it sem o exec -T)
$DC exec app sh                              # shell no container
$DC logs -f app                              # logs do app
$DC down                                     # parar (volumes PERSISTEM)
$DC up -d --build                            # rebuild + subir

# por nome do container (de qualquer lugar):
docker exec -it sie_app_homolog php artisan migrate:status
```

### Backup / restore do banco

```bash
# backup
docker exec sie_pg_homolog pg_dump -U "$DB_USERNAME" "$DB_DATABASE" > backup_$(date +%F).sql
# restore
cat backup.sql | docker exec -i sie_pg_homolog psql -U "$DB_USERNAME" -d "$DB_DATABASE"
```

---

## 9. Troubleshooting (problemas já vividos)

| Sintoma | Causa | Solução |
|---|---|---|
| **HTTP 500** logo no primeiro acesso | `APP_KEY` vazia | Gerar e gravar no `.env` (§3.2) e recriar o container app. |
| **`ERR_TOO_MANY_REDIRECTS`** / 301 em loop pela web | Cloudflare em `Flexible` + redirect cego `:80` | Mudar Cloudflare p/ `Full (strict)` **ou** usar o guard anti-loop (§5). |
| Site responde 200 local mas **não externamente** | Loop do Cloudflare, firewall, ou DNS | Testar origin direto: `curl -sk -H "Host: <dom>" https://127.0.0.1/` (deve dar 200). Conferir portas 80/443 no firewall/provedor. |
| Seed falha: **`column "role" of relation "users" does not exist`** | Seeder/factory antigos inserindo `role` em `users` (coluna foi movida p/ `user_roles`) | Já corrigido: usar `syncRoles()`. Garantir que `DatabaseSeeder`/`UserFactory` atuais estejam na imagem (rebuild). |
| Seed falha: **`Call to undefined function fake()`** | Imagem `--no-dev` não tem `fakerphp/faker` | Já corrigido: factory de usuários fictícios só roda se faker existir. |
| Migrations "aplicadas" mas schema divergente | Migration **editada após já ter rodado** no banco | Em ambiente sem dados reais: `migrate:fresh --seed --force`. |
| Assets/CSS desatualizados | `public/build` velho no volume | `$DC up -d --build` (o entrypoint re-sincroniza no boot). |
| Mudei o `.env` e nada mudou | `.env` é lido na **criação** do container | `$DC up -d app` para recriar. |

---

## 10. Checklist rápido para novo cliente

- [ ] Clonar repo em `/opt/sie_<cliente>`
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB (`DATABASE`/`USERNAME`/`PASSWORD`), `MAIL_*`
- [ ] (multi-cliente) definir `FPM_PORT` único
- [ ] `docker compose -f docker-compose.homolog.yml up -d --build`
- [ ] Gerar `APP_KEY` e recriar o container app
- [ ] `php artisan db:seed --force`
- [ ] Configurar `server` no Nginx do host (com guard anti-loop) + `nginx -t && systemctl reload nginx`
- [ ] Emitir certificado (`certbot --nginx -d <dom>`)
- [ ] Cloudflare em `Full (strict)`
- [ ] Testar `https://<dom>/` → 200, sem redirect
- [ ] Trocar a senha do admin (`admin@sie.local` / `123456`)
