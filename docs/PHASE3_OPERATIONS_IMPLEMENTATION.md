# TotalPlus — Fase 3: inventario, compras y operación integrada

**Fecha de desarrollo remoto:** 26 de octubre de 2026 (hora de usuario); registros de GitHub en UTC 27 de octubre.  
**Estado:** implementación y CI aprobados; **aceptación funcional integrada local pendiente**.  
**Origen:** ramas QA aceptadas `reengineering/phase0-2-20261025`.  
**Ramas independientes:** `reengineering/phase3-operations-20261026` en backend y frontend.  
**PR de backend:** https://github.com/yamartej/totalplus_api/pull/13 (draft).  
**PR de frontend:** https://github.com/yamartej/sitea/pull/12 (draft).

## 1. Inventario de funcionalidades preexistentes

| Dominio | Implementación revisada | Estado al iniciar |
|---|---|---|
| Saldo por producto y almacén | `InventoryService`, `InventoryController`, `inventories` | Ya implementado |
| Entradas, salidas y ajustes | `InventoryService::receive/issue/adjust` | Ya implementado |
| Transferencias entre almacenes | `InventoryService::transfer` y `InventoryTransferController` | Ya implementadas con transacción |
| Kardex | `InventoryMovement`, `InventoryMovementController` | Movimientos inmutables, consulta filtrable y tenant-scoped |
| Inventario y ventas | `SaleTransactionService`, controlador de venta y pruebas Phase 3 | Descuento/restauración de cantidades, disponible |
| Órdenes y renglones de compra | `PurchaseOrderController`, `PurchaseOrderProductController` | API CRUD con acceso por empresa |
| Recepción completa | `PurchaseReceiptService` | Transaccional, una recepción única por orden |
| Protección contra doble contabilización | Bloqueo de orden, clave única en `purchase_receipts` | Cubierta en recepción completa |
| Inmutabilidad de compras ya recibidas | Controladores de orden y renglones | Ya implementada |
| Lotes, costos y precios | `BatchController`, `CostController`, `ProductController` | Existentes; pruebas de pertenencia y reglas de precios |
| Interfaz de compras | Vistas de lotes, costos, productos | No había pantalla completa para crear orden, recibir parcialmente o ver Kardex |

## 2. Mejoras implementadas en Fase 3

### 2.1 Recepciones parciales

- Nueva migración `database/migrations/2026_10_26_000001_support_partial_purchase_receipts.php`.
- Nueva tabla `purchase_receipt_lines` para registrar cantidades de cada renglón recibido.
- `purchase_receipts.idempotency_key` única por empresa para solicitudes parciales; se sustituye la antigua restricción de **una** recepción por orden por un índice de consulta.
- `POST /api/purchaseorders/{id}/receive` mantiene contrato anterior: sin `lines` recibe **solo el saldo restante**; una segunda petición tras completar toda la orden devuelve 409.
- Formato de recepción parcial: `{warehouse_id, receipt_key, lines:[{purchase_order_product_id,quantity}]}`.
- La solicitud parcial exige `receipt_key`, valida pertenencia de líneas y cantidades aún pendientes. El bloqueo de la orden serializa solicitudes concurrentes; recepción, Kardex y renglones se confirman o revierten en una única transacción.
- Reintentos con `receipt_key` repetida se rechazan sin postear stock de nuevo. Una colisión de clave única confirmada a nivel de SQL devuelve 409, no un error opaco.
- Los recibos antiguos que carecen de filas en `purchase_receipt_lines` se mantienen **históricos completos**, sin inventar sus detalles ni volver a recibir mercancía.
- `GET /api/purchaseorders/{id}/receipts`: historial de recepción por empresa, protegido con `purchaseorders.view`.

### 2.2 Creación transaccional de órdenes

- Nuevo `POST /api/purchaseorders/with-lines` con permisos `purchaseorders.create`.
- Valida proveedor perteneciente a la empresa y productos de empresa o legados legibles, cantidades enteras positivas, precios y costos no negativos.
- Orden y todos sus renglones se crean en una transacción: ante error, no quedan órdenes incompletas por fallo de escritura.
- La API antigua de crear cabecera y líneas individualmente se conserva.

### 2.3 Kardex y seguridad de transferencias

- Nueva pantalla `/pages/kardex`: movimientos, variación, saldo anterior/posterior, fecha, referencia y filtros por tipo, almacén y producto; consulta paginada y autenticada.
- Se limita `reference_type` aportada por el usuario en `POST /inventory/transfer` a valores de tipo `manual_*` para impedir falsificar origen de movimientos como `sale`, `sale_void` o `purchase_receipt`. Las referencias manuales previas del tipo `manual_transfer` siguen admitiéndose.
- Se mantiene registro de movimientos inmutable y validación de la empresa para ambos almacenes.

### 2.4 Interfaz de compra y recepción

- `/pages/purchase-orders`: alta de proveedor opcional, alta de orden con varios productos, cantidades, precios y envío.
- `/pages/purchase-receipts`: seleccionar orden y almacén, consultar recepciones anteriores, registrar renglones y cantidades pendientes, prevención de reenvíos inciertos mediante clave conservada durante la solicitud.
- Enlaces a las tres vistas operativas desde la pantalla de inventario: crear orden, recibir orden y ver Kardex.
- El frontend usa el token de sesión vigente y empresa de la sesión; los permisos siguen aplicándose en la API, no solo en controles visuales.

## 3. Evidencia automatizada

| Verificación | Resultado final | Ejecución |
|---|---|---|
| Suite completa backend MySQL 8 | 303/303 PASS | https://github.com/yamartej/totalplus_api/actions/runs/40427949542 |
| Suite completa backend MariaDB 10.11 | 303/303 PASS | https://github.com/yamartej/totalplus_api/actions/runs/40427949332 |
| Reingeniería + seguridad | 157/157 PASS | https://github.com/yamartej/totalplus_api/actions/runs/40427949542 |
| PHP estático | PASS | https://github.com/yamartej/totalplus_api/actions/runs/40427949322 |
| Frontend TypeScript y Next.js build | PASS | https://github.com/yamartej/sitea/actions/runs/40428104001 |

### Pruebas específicas nuevas

- Varias entregas parciales hasta completar una orden.
- Recepción del saldo restante después de una entrega parcial.
- Rechazo de recepción repetida por clave, y bloqueo de doble recepción completa.
- Rechazo de cantidades superiores al saldo restante.
- Rechazo de renglones ajenos a la orden o duplicados.
- Reversión transaccional completa de stock y Kardex ante un producto extranjero.
- Historial de recepciones accesible solo con permiso y dentro de empresa.
- Conservación de recibos históricos sin detalle por renglón.
- Creación atómica de órdenes con varios productos y rechazo de proveedores o productos de otras empresas.
- Control de permisos para crear órdenes y evitar que un usuario suplante orígenes de ventas/compras en transferencias.

## 4. Requisitos para integración local (NO EJECUTADOS)

**La fase 3 no se ha instalado ni probado en el XAMPP del usuario.** Su base QA anterior `totalplus_phase02_testing` debe permanecer intacta hasta un procedimiento separado.

1. Conservar los respaldos verificados del cierre de fases 0–2, que reflejan el estado **anterior** a Fase 3. Crear además **un respaldo nuevo completo** de la base QA actual justo antes de migrar y probar que puede restaurarse.
2. Probar primero con un clon **distinto** de la base QA funcional, si se dispone de él. No reutilizar `totalplus_qa013_tests` sin aprobación y sin confirmar su contenido.
3. Cambiar los clones de trabajo a las ramas de Fase 3 solamente después de comprobar que no hay cambios sin guardar. Conservar `.env.testing` y `.env.local` y verificar hashes.
4. Verificar conexión real de Laravel **antes** de cualquier migración. Aplicar exclusivamente `2026_10_26_000001_support_partial_purchase_receipts.php` en el entorno QA elegido. **Esta migración modifica el índice de unicidad de recibos e incorpora estructura nueva. No es una operación trivial de rollback**: una vez existan recepciones parciales, su `down()` se niega a destruir los históricos.
5. Verificar cantidad y contenido de órdenes, recibos, renglones, inventarios y movimientos antes/después. La migración no debería modificar saldos.
6. Con datos **desechables**, probar: alta de proveedor, alta de orden con varios renglones, recepción parcial en almacén autorizado, nueva recepción del restante, rechazo de entrega excedida, rechazo de replay, saldos/Kardex y segregación entre empresas y perfiles. Confirmar navegación, filtros y reportes.
7. La prueba local de anulación de venta pendiente desde QA-018 es una puerta independiente antes de promoción de Fases 0–2/3 a producción.
8. Registrar aceptación de usuario y evidencia local antes de decidir la fusión. **No fusionar ni desplegar desde los PR draft** sin decisión explícita.

## 5. Límites conocidos y evolución

- Las vistas nuevas se diseñaron para una sesión vinculada a una empresa; los operadores globales sin `company_id` necesitan un selector explícito y controlado de empresa para usarlas.
- Los permisos necesarios incluyen, según la vista, `suppliers.view/create`, `purchaseorders.view/create/update`, `purchaseorderproducts.view`, `products.view`, `warehouses.view` e `inventory.view`. Una cuenta sin ellos recibirá el 403 esperado.
- Lotes y estado `received` preexistentes no están automáticamente sincronizados con la recepción de una orden. Esto requiere definir primero una asociación confiable entre lote y orden antes de automatizar transiciones.
- No se ejecutaron pruebas de interfaz en navegador local para las pantallas nuevas, pruebas de carga ni simulaciones de fallas de red reales. Los checks de frontend validan tipos y compilación, **no** aceptación de UX.
- No se reemplazan datos ni se cambia el procedimiento de costos históricos de la tienda.
- La API clásica de órdenes admite alta de cabecera y renglones por separado; para flujo atómico nuevo utilizar `POST /purchaseorders/with-lines`.

## Dictamen

**Fase 3: desarrollo remoto implementado y suite automatizada aprobada.**  
**Pendientes de cierre integral:** migración controlada a la QA local, pruebas end-to-end de los módulos nuevos y aceptación del usuario.  
**Prohibido:** `migrate:fresh`, `db:wipe` y `RefreshDatabase` en bases QA funcionales o producción.
