# TotalPlus — Fases 0 a 2: acta técnica y puerta de cierre

**Fecha:** 26 de octubre de 2026
**Rama QA backend:** `reengineering/phase0-2-20261025` — PR #12 (borrador)
**Rama QA frontend:** `reengineering/phase0-2-20261025` — PR #11 (borrador)

## Resultado remoto — APROBADO

La verificación automatizada se realizó sobre bases SQL efímeras de GitHub Actions, sin conectarse a la base local, a la producción ni a `main`.

| Verificación | Resultado | Evidencia |
|---|---|---|
| Backend completo, MySQL 8 | 286/286 PASS | https://github.com/yamartej/totalplus_api/actions/runs/40322430247 |
| Backend reingeniería y seguridad, MySQL 8 | 140/140 PASS | misma ejecución anterior |
| Backend completo, MariaDB 10.11 | 286/286 PASS | https://github.com/yamartej/totalplus_api/actions/runs/40322764119 |
| Frontend `npm ci`, TypeScript y Next.js build | PASS | https://github.com/yamartej/sitea/actions/runs/40316750226 |
| Lint PHP de backend | PASS | Workflow 'Backend static validation' del PR #12 |

Las pruebas heredadas se adecuaron a los contratos **actuales** (autorización, pertenencia a empresa, almacenes y estructura de ventas). Se conservaron controles de acceso, no se desactivó middleware ni se marcaron pruebas como omitidas para aprobar CI.

Se validó la preservación del historial comercial en anulaciones y al retirar detalles. La venta anulada debe conservar fecha, importe, detalles originales y marcadores de auditoría; su inventario se revierte una sola vez. Los reportes de ventas vigentes no contabilizan anulaciones.

La corrección de productos garantiza cantidad inicial cero cuando se omite, y conserva existencias previas cuando una edición no cambia la cantidad.

## Pendiente para cierre formal — INSTALACIÓN LOCAL XAMPP

**No se han ejecutado estos pasos remotos sobre la computadora del usuario.** No firmar cierre formal, no fusionar PR ni desplegar hasta contar con los controles siguientes:

1. Verificar que el backend esté detenido, la rama sea la aislada y la copia de trabajo no tenga modificaciones inesperadas (`.env.testing` ya contiene cambios locales que deben preservarse).
2. Crear **y verificar** un respaldo SQL completo de `totalplus_phase02_testing`, desde phpMyAdmin, fuera de `htdocs`. Mantener una segunda copia del archivo. No tocar base de producción.
3. Ejecutar `C:\xampp\php\php.exe -v` y comprobar compatibilidad con las dependencias del `composer.lock` (CI utilizó PHP 8.2).
4. Hacer `git pull --ff-only origin reengineering/phase0-2-20261025` en los clones aislados backend y frontend, conservando los `.env` locales. No actualizar `main`.
5. Inspeccionar por lectura los nombres de base activos:
   ```powershell
   Select-String -Path .env,.env.testing -Pattern '^\s*(APP_ENV|DB_CONNECTION|DB_DATABASE)\s*='
   ```
6. Comprobar que **no existe configuración cacheada** `bootstrap/cache/config.php`; de existir, detener el proceso y resolverlo antes de migrar.
7. Con el respaldo verificado, establecer explícitamente en el proceso PowerShell `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=totalplus_phase02_testing`, y verificar el destino real con una consulta de Laravel **solo lectura**. Solo entonces ejecutar la migración aditiva `2026_10_25_000001_add_sale_void_audit_fields.php` con `artisan migrate --path=... --env=testing`, sin `migrate:fresh`.
8. Recuento de ventas, detalles e inventarios **antes y después**. El cambio de esquema no debe borrar ventas previas ni modificar existencias.
9. Verificar en el frontend local el historial antiguo, el indicador "Anulada", los detalles históricos, los reportes de ventas vigentes, la devolución a Kardex y el rechazo de segunda anulación. Utilizar una venta de prueba desechable, no anular ventas históricas para hacer la prueba.
10. Documentar resultado, respaldo, versión PHP y versión MySQL/MariaDB, y aprobar el cierre antes de considerar fusión a `main`.

### Restricciones obligatorias

- **Nunca** ejecutar `php artisan test` con `RefreshDatabase` apuntando a `totalplus_phase02_testing`; destruye/reconstruye tablas.
- Automatización de pruebas local, si se requiere, únicamente contra `totalplus_qa013_tests` y con variables de entorno verificadas.
- Ninguna migración ni prueba destructiva debe tener como destino la base de producción.
- Mantener ambos PR como **draft** hasta firma de cierre.

## Dictamen

**Aprobado:** implementación y regresión automatizada remota de las Fases 0–2.

**Pendiente de aprobación final:** despliegue en entorno QA funcional local, respaldo verificado, ejecución de migración aditiva y aceptación funcional posterior. La ausencia de acceso remoto al XAMPP impide afirmar que esos pasos ya ocurrieron.
