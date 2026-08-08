<?php

use Illuminate\Database\Migrations\Migration;

class RemoveWarehouseIdFromInventoriesTable extends Migration
{
    /**
     * Migración histórica neutralizada.
     *
     * La tabla `inventories` fue creada originalmente sin `warehouse_id`
     * (2023_08_03_220522_create_inventories_table.php).
     *
     * Por lo tanto, esta migración no puede eliminar una columna ni una
     * clave foránea que todavía no existen durante una reconstrucción limpia.
     *
     * La migración siguiente:
     * 2024_12_13_190801_add_warehouse_id_to_inventories_table.php
     * es la responsable de crear `warehouse_id`.
     */
    public function up()
    {
        //
    }

    /**
     * No debe recrearse warehouse_id aquí.
     * Su ciclo de vida pertenece a la migración que lo agrega después.
     */
    public function down()
    {
        //
    }
}
