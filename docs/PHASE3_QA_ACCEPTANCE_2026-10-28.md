# TotalPlus — Fase 3, acta de validación QA local

**Fecha:** 28 de octubre de 2026  
**Alcance:** Fase 3 operativa de reingeniería (proveedores, órdenes de compra, recepciones parciales, inventario y Kardex).  
**Estado:** **Aprobación funcional en QA**, según confirmación directa de la persona que ejecutó las pruebas en Windows/XAMPP. **No es autorización para producción ni para fusionar las ramas.**

## Entorno comprobado

- Backend Laravel: rama `reengineering/phase3-operations-20261026`; commit de corrección CSRF `1ad73921c1fff8fa90073ed303f367b0880ca83f`.
- Frontend Next.js: rama `reengineering/phase3-operations-20261026`; commit `67d596725f19653fb792dde0840421d7363be701`.
- Base de datos aislada: `totalplus_phase3_qa_20261028_184456`.
- Base anterior conservada: `totalplus_phase02_testing`.
- Respaldo previo: `totalplus_phase02_testing_pre_phase3_20261028_183235.sql`; SHA-256 `F4EBA4802452CA172F56B56B7FD24E244CDC047ECD63D8C698FD69CF5897DFA0`.
- Restauración previa: 33/33 tablas, 457 registros, 0 diferencias por recuentos de filas.
- Migración parcial: `2026_10_26_000001_support_partial_purchase_receipts` aplicada solo en la base QA de Fase 3, 34 tablas resultantes.

## Incidencias y resolución

1. **Error 419 al crear proveedor:** originado por `EnsureFrontendRequestsAreStateful` en la pila global de Laravel; interpretaba la solicitud Bearer del frontend localhost como solicitud con cookies y exigía CSRF. Corregido en rama QA eliminando el middleware stateful **solo de la pila global** y preservando `VerifyCsrfToken` en el grupo `web`. Persisten `auth:sanctum` y los permisos de API. La persona probó y confirmó que el proveedor se guardó.
2. **Bloqueo de proveedor por carga conjunta y empresa:** selector explícito para cuentas globales, lectura independiente de proveedores/productos, errores de permisos expresivos. Los permisos de la cuenta QA `Administrator` comprobados sin faltantes; no se amplió acceso a producción.
3. **Arranque Next.js tras cerrar PowerShell:** reconstrucción de `.next` con `npm run build`; servidor reiniciado correctamente según confirmación posterior.

## Pruebas aceptadas

La persona ejecutora confirmó por mensaje **«ahora sí, hace las 3 cosas»**, en respuesta al flujo funcional solicitado:

1. Registro de orden de compra.
2. Dos recepciones parciales (2 y 3 unidades según el guion de aceptación).
3. Actualización de existencias y movimientos en Kardex.

**Nivel de evidencia:** confirmación del usuario de la ejecución local; no se adjuntó en GitHub un log estructurado transaccional ni capturas individuales de estos tres pasos. No interpretar este registro como una inspección remota directa de la base QA.

## CI verificada para el último commit de código de backend

- Backend GitHub Actions, MySQL: **307/307 pruebas aprobadas**.
- Backend GitHub Actions, MariaDB: **307/307 pruebas aprobadas**.
- Pruebas enfocadas de reingeniería y seguridad: **161/161 aprobadas**.
- Validación PHP estática: aprobada.
- Frontend Next.js: TypeScript y compilación aprobados en commit `67d596725f19653fb792dde0840421d7363be701`.

## Control de cambios

- Pull request backend: https://github.com/yamartej/totalplus_api/pull/13
- Pull request frontend: https://github.com/yamartej/sitea/pull/12
- Ambos PR siguen **abiertos en borrador**, con base `reengineering/phase0-2-20261025`, sin fusionar con `main`.
- **No hubo despliegue en producción** ni alteración intencional de la base de Fases 0–2.
- La fase queda **validada funcionalmente en entorno QA local**; la decisión de integración, despliegue, capacitación y controles de salida a producción requiere autorización y validación independientes.
