# Bean

Sistema de ventas e inventario para pollos, embutidos, carnes y congelados.

## Backend Laravel

```bash
cd back
composer install
php artisan migrate
php artisan serve
```

La migración instala 100 productos de prueba en 10 categorías. Usuario inicial:

- Usuario: `admin`
- Contraseña: `admin`

## Frontend Quasar

```bash
cd front
npm install
npm run dev
```

La URL de la API se configura en `front/src/boot/axios.js`.
