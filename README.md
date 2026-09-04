# shortener

A URL shortener, written as a learning project: a form takes a link, a short code
comes back, and that code redirects to the original address.

PHP 8.4 · Symfony 7.4 LTS · MySQL 8.4 · nginx · Docker Compose

## Running it

```
cp .env.example .env    # ports, credentials, and your own UID/GID from `id -u`, `id -g`
make up                 # first run also needs: make build
make console c="doctrine:migrations:migrate --no-interaction"
```

The application is then at http://localhost:8080.

## Tests

```
make test-db            # migrations for the test database; re-run after every new one
make test
```

`make lint` runs the checks worth doing before a commit. `make help` lists every target.

## Layout

```
app/        the Symfony application
docker/     images and configuration for php-fpm, nginx and MySQL
```

Nothing is installed on the host: PHP, Composer and MySQL all live in containers,
and everything that writes into `app/` runs as `www-data` with the host UID/GID,
so the files stay editable from the IDE.
