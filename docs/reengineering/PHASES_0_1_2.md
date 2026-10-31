# TotalPlus — fases 0, 1 y 2: registro de ejecución

Fecha: 2026-10-25
Base backend: `41635ccdd8fd77babe38f9fd60b5d5b9d2f0fad1`
Base frontend: `b81f6c5b648c852ad8eaeaba32385e416d07182d`

## Fase 0 — línea base y recuperación
- Ramas `reengineering/phase0-2-20261025` creadas en los dos repositorios, sin cambios en `main`.
- La copia del código queda conservada en las referencias inmutables de los commits base mencionados.
- No se dispone en esta sesión de acceso a base de datos de producción, a sus respaldos ni a un entorno operativo.
- **Pendiente obligatorio antes de desplegar:** backup completo de base de datos, verificación de restauración, inventario de migraciones ejecutadas, configuración de variables de entorno, ejecución de tests y revisión de logs de aplicación.
- No usar `migrate:fresh` ni `migrate:rollback` sobre producción.
- Verificar cierre de caja, cartera y stock antes y después de cualquier migración.

## Fase 1 — aislamiento entre empresas
- Reportes de ventas: consulta limitada al tenant; reporte por producto consulta `sales_details` y no `sales.product_id`.
- Pagos de ventas a crédito: búsqueda y borrado vinculados a ventas autorizadas; validación del saldo.
- Pagos directos de clientes: listado, creación, búsqueda por fechas y borrado limitados al tenant.
- Pruebas de regresión añadidas; **pendientes de ejecución** en un entorno con base de datos de pruebas.
- **Cobertura pendiente:** otras rutas heredadas (caja, categorías, proveedores, permisos y menús) y pruebas completas de roles globales.

## Fase 2 — protección financiera inicial
- Se impide anular una venta con pagos, evitando que el `ON DELETE CASCADE` elimine `credit_details`.
- El total de venta no puede modificarse mediante la actualización de la cabecera; se calcula en `SaleTransactionService`.
- La respuesta 500 del controlador de ventas no devuelve excepciones internas al consumidor.
- **Pendiente:** implementar un estado de anulación no destructiva, auditoría de reversos, procedimientos de notas de crédito y conciliación integral de pagos.
- La cancelación sin pagos **continúa usando borrado físico**, de acuerdo al comportamiento heredado; no desplegar como solución final de auditoría fiscal.
- Los procesos de pago necesitan validación de integración y revisión de concurrencia bajo carga.

## Checklist para revisión
1. Instalar dependencias usando lockfiles correspondientes.
2. Preparar base de pruebas separada, ejecutar migraciones y seeds de seguridad.
3. Ejecutar `php artisan test` (backend) y `npm run build` / `npm run lint` (frontend).
4. Revisar transacciones repetidas, errores HTTP 403/404/422, rechazo de pagos cruzados y concurrencia.
5. Probar navegación de ventas, cartera, reportes y notas de crédito con dos empresas y perfiles distintos.
6. Aceptar cambios solo mediante revisión de pull request, pruebas verdes y respaldo restaurable.

> Ninguna ejecución de pruebas, restauración de respaldos o despliegue a producción se da por realizada en este registro.
