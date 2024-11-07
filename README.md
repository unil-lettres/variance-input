# Variance Laravel Project (Development Server)

This project sets up a Laravel application for the Variance project using Docker and Docker Compose. This setup includes the Laravel application, MariaDB, and phpMyAdmin for database management.

### Copy .env.example file and configure Environment Variables
cp app/.env.example app/.env

### Edit Laravel Dockerfile so that container keeps running while running install commands
#CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
CMD ["tail", "-f", "/dev/null"]

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

### Revert Laravel Dockerfile to serving app on port 8000 and restart container
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
#CMD ["tail", "-f", "/dev/null"]

docker-compose up -d laravel
