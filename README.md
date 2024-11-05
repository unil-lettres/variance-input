# Variance Laravel Project (Development Server)

This project sets up a Laravel application for the Variance project using Docker and Docker Compose. This setup includes the Laravel application, MariaDB, and phpMyAdmin for database management.

### Copy .env.example file and configure Environment Variables
cp app/.env.example app/.env

### Start containers
docker-compose -d --build up

### Access Laravel container
docker-compose exec laravel bash

### Install composer dependecies
composer install

### Generate application key
php artisan key:generate

### Run migrations
php artisan migrate
