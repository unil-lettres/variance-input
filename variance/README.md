# Introduction

Variance is a platform for the publication of works that have circulated in several versions. The objective is to offer a support which allows to gather all the versions of the same work, modern or ancient, and to assist the interpretation of the transformations, from one edition to another.

# Development with Docker

## Docker installation

A working [Docker](https://docs.docker.com/engine/install/) installation is mandatory.

## Environment files

Copy and rename the following files:

```sh
cp example.env .env
cp php/settings.inc.example.php php/settings.inc.php
cp dev/php/settings.inc.example.php dev/php/settings.inc.php
cp example.htpasswd .htpasswd
cp example.htpasswd dev/.htpasswd
```

You can replace the values if needed, but the default ones should work for development.

## Edit hosts file

Edit hosts file to point **variance.lan** to your docker host.

## Environment installation & configuration

Run the following docker commands from the project root directory.

Build & run all the containers for this project:

`docker-compose up` (add -d if you want to run in the background and silence the logs)

Data for the mysql service is persisted using docker named volumes. You can see what volumes are currently present with:

`docker volume ls`

If you want to remove a volume (e.g. to start with a fresh database), you can use the following command.

`docker volume rm volume_name`

## Frontends

To access the main application please use the following link.

[http://variance.lan:8282](http://variance.lan:8282)

To access the import script ([http://variance.lan:8282/upload.php](http://variance.lan:8282/upload.php)) you need to copy and rename the following file.

```sh
cp example.htpasswd .htpasswd
cp example.htpasswd dev/.htpasswd
```

The 'username' and 'password' in .htpasswd should be updated. You can find them on vlett (see Wiki for more details).

Note that '/upload.php' is used ONLY in local environment and is not secured. The goal here is to secure the access for the 'dev' and 'prod' environments.

# Deployment with Docker

Copy and rename the following files:

```sh
cp example.env .env
cp php/settings.inc.example.php php/settings.inc.php
cp dev/php/settings.inc.example.php dev/php/settings.inc.php
cp example.htpasswd .htpasswd
cp example.htpasswd dev/.htpasswd
```

You should replace the values since the default ones are not safe for production.

Please also make sure to copy & rename the **docker-compose.override.yml.prod** file to **docker-compose.override.yml**.

`cp docker-compose.override.yml.prod docker-compose.override.yml`

You can replace the values if needed, but the default ones should work for production.

Build & run all the containers for this project:

`docker compose up -d`

Use a reverse proxy configuration to map the url to port `8282`.
