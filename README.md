# TotalPlus API

La API TotalPlus es una aplicación que gestiona el inventario y las ventas de una tienda. Permite a los usuarios crear, actualizar y eliminar productos, así como registrar ventas y realizar un seguimiento del inventario disponible.

## Instalación

1. Clona este repositorio en tu máquina local.
2. Instala las dependencias utilizando Composer: `composer install`
3. Configura el archivo `.env` con la información de tu base de datos y otros ajustes necesarios.
4. Ejecuta las migraciones para crear las tablas de la base de datos: `php artisan migrate`
5. Inicia el servidor de desarrollo: `php artisan serve`

## Uso

La API TotalPlus ofrece los siguientes endpoints:

- `GET /api/products`: Obtiene la lista de todos los productos en el inventario.
- `POST /api/products`: Crea un nuevo producto en el inventario.
- `PUT /api/products/{id}`: Actualiza los detalles de un producto específico.
- `DELETE /api/products/{id}`: Elimina un producto del inventario.

- `GET /api/sales`: Obtiene la lista de todas las ventas registradas.
- `POST /api/sales`: Registra una nueva venta asociada a un cliente.
- `GET /api/sales/{id}`: Obtiene los detalles de una venta específica.

## Ejemplos

### Crear un nuevo producto:

POST /api/products

{
"name": "Camiseta",
"description": "Camiseta de algodón",
"quantity": 50,
"price": 20.99
}


### Registrar una venta:

POST /api/sales

{
"customer_id": 1,
"quantity": 5
}


## Contribución

¡Bienvenido a contribuir a la API TotalPlus! Si encuentras errores, tienes ideas para mejoras o deseas agregar nuevas características, no dudes en abrir un issue o enviar un pull request.

## Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo LICENSE para más detalles.

## Contacto

Si tienes alguna pregunta o comentario, puedes contactarme a través de [correo electrónico](correo@ejemplo.com) o en Twitter [@usuario](https://twitter.com/usuario).



