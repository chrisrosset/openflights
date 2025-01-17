# OpenFlights

Welcome to the code base for [OpenFlights](https://openflights.org), a tool that lets you map your flights around the world, search and filter them in all sorts of interesting ways, calculate statistics automatically, and share your flights and trips with friends and the entire world (if you wish).

## Data

Most people come here for the **free airport, airline and route data**. See the [documentation](https://openflights.org/data.html) or plunge straight into the [data itself](data/).

## User interface

See [`locale`](locale/) for supported languages and instructions for editing them or adding new ones.

## Code

I'll be upfront: this codebase is an unholy mess. The bulk of it was written in 2008, back when PHP seemed like a good idea and the only way to learn JavaScript was the hard way. Any vestiges of sanity you may encounter (eg. unit and integration tests or package management) were grafted on as an incomplete afterthought.

Basically, though, it's your classic [LNMP](https://en.wikipedia.org/wiki/LAMP_%28software_bundle%29) app. JavaScript frontend (mostly in the monolithic [`openflights.js`](openflights.js), some bits under [`js`](js/)) talking to a Nginx/PHP backend (in [`php`](php/)) that wraps around a MySQL database.

## Tests

Test coverage is woefully incomplete, but comes in three flavors:
- [`client`](test/client/): Client-side full-stack integration tests, require live DB & server
- [`server`](test/server/): Server-side (PHP) integration tests, require a live database
- [`unit`](test/unit/): Client-side JavaScript unit tests

## Installation

See [INSTALL](INSTALL) for system requirements and instructions.

# Development Docker

A basic Docker setup is available to simplify setting up a development environment but requires a
couple of manual steps to get the site up and running.

1. `cp php/config.sample.php php/config.php` and set `$host = "db";` so the host name matches the
   the database container host name.
2. Run `docker-compose up` to create the containers.
3. Install local PHP dependencies inside the container.
   ```
   # host shell
   user@host:openflights $ docker exec -it openflights-web-1 bash
   
   # container shell
   root@ee261e8f9103:/# cd /var/www/openflights/ && php /usr/local/bin/composer install
   ```
4. You should be able to access the site at `http://localhost:8008`.
