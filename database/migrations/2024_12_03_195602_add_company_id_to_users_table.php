<?php

use Illuminate\Database\Migrations\Migration;

class AddCompanyIdToUsersTable extends Migration
{
    /**
     * Esta migración se conserva por compatibilidad con el historial.
     *
     * `company_id` ya fue creado en:
     * 2024_11_20_194654_create_companies_table.php
     *
     * Por lo tanto, esta migración debe ser un no-op para permitir
     * reconstruir una base de datos limpia sin intentar crear la
     * misma columna dos veces.
     */
    public function up()
    {
        //
    }

    /**
     * No se elimina `company_id` aquí porque la columna pertenece
     * a la migración anterior que crea la tabla `companies`.
     */
    public function down()
    {
        //
    }
}
