# TotalPlus — Fase 4: conciliación financiera y controles de cartera

**Plan operativo de esta secuencia:** luego de la aceptación local de Fase 3 (compras e inventario), revisar y proteger créditos, pagos y reportes **sin reescribir los módulos existentes**.

> **Nota de nomenclatura:** el repositorio contiene tests antiguos denominados `Phase4` (precios, costos, lotes). Este documento utiliza **Fase 4 operativa** del plan de reingeniería continuado tras la Fase 3 de compras. No significa que los tests antiguos deban repetirse o eliminarse.

**Base de desarrollo:** backend y frontend `reengineering/phase3-operations-20261026`.  
**Rama QA:** `reengineering/phase4-financial-reconciliation-20261028`.  
**PR backend:** https://github.com/yamartej/totalplus_api/pull/14 (draft).  
**PR frontend:** https://github.com/yamartej/sitea/pull/13 (draft).

## Auditoría de lo existente

| Función | Estado encontrado | Decisión |
|---|---|---|
| Venta y anulación no destructiva | `SaleTransactionService`, `SaleController` y tests | Conservar; no reemplazar |
| Pago aplicado a venta a crédito | `CreditDetailController`, validación de saldo y bloqueo transaccional | Conservar y reforzar precisión |
| Abonos directos al cliente | `CreditCustomerDetailController` | Mantener separados de pagos asociados a ventas |
| Informes por fecha, cliente, producto | `SalesReportController` tenant-scoped | Conservar |
| Notas de crédito del sistema | Formulario que crea un registro de deuda tipo `credit` mediante `creditCustomerRegister` | No confundir con devolución fiscal; prohibir importes negativos |
| Reporte de saldo consolidado | No había vista explícita que separara pagos aplicados a venta vs abonos directos | Nueva consulta de solo lectura |

## Entregables nuevos

1. `GET /api/credits/reconciliation?company_id=<id>`:
   - Autenticación Sanctum y `credits.view`.
   - Usuarios ligados a empresa quedan limitados a ella; usuarios globales con `tenant.cross_company` deben seleccionar explícitamente una empresa.
   - Incluye ventas a crédito no anuladas, con compatibilidad para ventas históricas sin `company_id` cuando pertenecen a clientes de la empresa.
   - Agrupa los pagos de cada venta mediante consulta separada para evitar multiplicación de totales por los movimientos.
   - Expone **separadamente** los abonos directos de cliente `credit_customer_details`, aún no asignados a una venta.
   - No imputa pagos, no edita saldos, no deduce automáticamente abonos directos del saldo por venta.
   - Filtro opcional `customer_id` perteneciente a la empresa.
2. Pantalla `/pages/financial-reconciliation`, con resumen por empresa, detalle por cliente, selector para cuentas globales, estados de error y enlace desde pagos de créditos.
3. Validación de montos en `SaleController::creditCustomerRegister`, `CreditDetailController` y `CreditCustomerDetailController`: deben ser positivos y con un máximo de dos decimales. La operación manual de crédito no es una nota de devolución de dinero.
4. Pruebas para segregación, pagos duplicados en un mismo registro de venta, ventas históricas, ventas anuladas, permisos y montos inválidos.

## Alcance y advertencias

- Sin nuevas migraciones de base de datos.
- Los registros históricos se leen pero no se modifican ni se corrigen automáticamente.
- `sale_balance` representa deuda por ventas después de pagos *vinculados a esas ventas*, no un saldo final conciliado tras abonos a nivel de cliente.
- Un abono directo no se vincula a un `sale_id`, por lo que ninguna imputación automática sería defendible sin diseñar primero el proceso contable.
- Los importes se muestran en las unidades monetarias registradas por TotalPlus; no se presupone código ISO de moneda.
- Reporte de apoyo operativo, **no estado financiero certificado**.
- Se mantienen intactos PR de Fase 3, `main`, producción y bases QA locales anteriores.

## Aceptación local requerida

Preparar **una nueva copia** de la base QA de Fase 3, respaldo íntegro y restauración validada. Sobre la copia de Fase 4:

1. Iniciar sesión en una empresa, comprobar consulta de conciliación sin movimientos.
2. Crear venta de crédito con importe 100 y pago vinculado de 30; verificar saldo por venta 70.
3. Registrar un abono directo a cliente de 10; verificar que aparece **aparte**, no que el saldo pasa de 70 a 60.
4. Comprobar fecha / saldo tras otra cuota; el total no debe duplicarse si hay varias cuotas.
5. Comprobar que otra empresa recibe 403/404 al intentar consultas ajenas y que no se exponen datos.
6. Intentar crear crédito con -1, 0 o 0.001: se debe obtener 422 sin persistencia.
7. Revisar las pantallas existentes de pagos e informes para detectar regresiones.

**No ejecutar** `migrate:fresh`, `db:wipe`, `migrate:rollback` ni un `RefreshDatabase` sobre la base QA compartida o producción. Conservar los PR como borradores hasta aceptar la fase localmente.
