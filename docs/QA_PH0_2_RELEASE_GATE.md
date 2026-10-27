# TotalPlus — Acta de validación y cierre de las Fases 0–2 (entorno QA)

**Fecha de aceptación local:** 26 de octubre de 2026  
**Alcance:** ramas de reingeniería QA; **no es un despliegue a producción**.  
**Backend:** `reengineering/phase0-2-20261025` — PR #12 (borrador).  
**Frontend:** `reengineering/phase0-2-20261025` — PR #11 (borrador).

## 1. Verificación automatizada — APROBADA

Las ejecuciones usaron exclusivamente bases efímeras de GitHub Actions; no utilizaron los datos QA locales.

| Comprobación | Resultado | Ejecución |
|---|---|---|
| Backend completo — MySQL 8 | **286/286 PASS** | https://github.com/yamartej/totalplus_api/actions/runs/40323117765 |
| Reingeniería y seguridad — MySQL 8 | **140/140 PASS** | https://github.com/yamartej/totalplus_api/actions/runs/40323117765 |
| Backend completo — MariaDB 10.11 | **286/286 PASS** | https://github.com/yamartej/totalplus_api/actions/runs/40323117922 |
| Validación estática PHP | **PASS** | https://github.com/yamartej/totalplus_api/actions/runs/40323117771 |
| Frontend Next.js (TypeScript + build) | **PASS** | https://github.com/yamartej/sitea/actions/runs/40316750226 |

Las pruebas heredadas se modernizaron a los contratos vigentes sin desactivar middleware de autenticación o autorización. Se añadieron pruebas de cantidad inicial cero en producto y preservación de cantidad al editar. La suite automatizada cubre el proceso de anulación no destructiva, sus controles de auditoría y exclusión de informes activos.

## 2. Respaldo, restauración y migración locales — APROBADOS

Evidencia proporcionada por el responsable de ejecución local:

- PHP **8.2.12**.
- Rama backend actualizada al commit `472b261`; frontend al commit `aebf475`.
- Se preservaron los archivos locales de configuración; `.env.testing` apunta a la base QA `totalplus_phase02_testing`.
- Se creó un respaldo SQL y se verificó su integridad por SHA-256.
- Se restauró el respaldo con éxito **en una base temporal distinta**: 33 tablas presentes; **452 registros en origen y 452 en restauración**, sin diferencias en recuentos de ninguna de las 33 tablas.
- Se verificó la conexión real de Laravel en entorno `testing` antes de modificar el esquema.
- Se ejecutó únicamente la migración aditiva `2026_10_25_000001_add_sale_void_audit_fields.php` (**QA-018**): los cinco campos de auditoría están presentes y el registro de migraciones pasó de 68 a 69.
- Después de migrar permanecieron intactos los recuentos conocidos: 4 ventas, 4 detalles, 3 inventarios y 10 movimientos.
- El backend local respondió **401** a un acceso anónimo a ventas, como exige el control de autenticación.
- TypeScript local y Next.js build local: **PASS**. Se observaron advertencias ESLint no bloqueantes; se pueden resolver en mantenimiento posterior.
- Inicio de sesión en la interfaz local: **PASS**.

**Nota de alcance:** el cotejo de respaldo validó estructura, restauración y recuentos, no una comparación byte a byte o campo por campo de todos los datos.

## 3. Aceptación funcional de usuario — APROBADA

En `http://127.0.0.1:3000`, con API local en `http://127.0.0.1:8000`, el usuario informó explícitamente:

| Flujo de interfaz | Resultado |
|---|---|
| Reporte de ventas | **APROBADO** |
| Inventario | **APROBADO** |
| Registro de ventas | **APROBADO** |
| Observaciones del usuario | **Ninguna** |

Las pantallas fueron comprobadas en modo consulta; **no se ejecutó manualmente una nueva venta o anulación destructiva sobre los datos QA históricos**.

## 4. Límites del cierre y puerta a producción

**Dictamen:** **Fases 0–2 cerradas a nivel de desarrollo y aceptación de entorno QA** con la cobertura anterior. La aprobación significa que la suite automatizada y las tres pantallas revisadas funcionan y que la migración QA fue aplicada de forma controlada.

**La autorización para fusionar PR o desplegar en producción NO forma parte de este dictamen.** Ambos PR permanecen como borrador. Antes de producción o de afirmar una aceptación completa de QA-018 por interfaz, deben verificarse manualmente, **con una venta nueva desechable y un usuario autorizado**, la anulación, la reposición única de existencias/Kardex, la persistencia histórica y el rechazo a la segunda anulación. La interfaz actual de reporte presenta el estado, pero no incorpora un botón de anulación; las rutas de backend están cubiertas por pruebas automatizadas. Este control queda registrado como pendiente específico de promoción, no como un resultado manual aprobado.

### Seguridad de datos que se mantiene

- No ejecutar `migrate:fresh`, `db:wipe` ni pruebas `RefreshDatabase` contra `totalplus_phase02_testing`.
- Ejecutar pruebas destructivas únicamente contra `totalplus_qa013_tests` después de verificar la conexión real.
- Conservar el respaldo SQL probado y la base de restauración hasta terminar cualquier promoción.
- No cambiar la rama `main` ni producción sin una decisión de despliegue por separado.

**Estado resumido:** desarrollo y validación QA Fases 0–2: **cerrados**; promoción a producción y prueba manual end-to-end de anulación: **pendientes explícitos**.
