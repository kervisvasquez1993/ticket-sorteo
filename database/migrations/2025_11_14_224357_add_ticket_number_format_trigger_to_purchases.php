<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION format_ticket_number()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Solo formatear si no es NULL y no empieza con 'RECHAZADO'
                IF NEW.ticket_number IS NOT NULL
                   AND NEW.ticket_number NOT LIKE 'RECHAZADO%'
                THEN
                    -- Convertir a entero y formatear con LPAD
                    BEGIN
                        NEW.ticket_number := LPAD(
                            CAST(NEW.ticket_number AS INTEGER)::TEXT,
                            4,
                            '0'
                        );
                    EXCEPTION
                        WHEN OTHERS THEN
                            -- Si no se puede convertir a entero, dejar como está
                            -- (esto permite que RECHAZADO-* pase sin problemas)
                            NULL;
                    END;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER format_ticket_number_before_insert
            BEFORE INSERT ON purchases
            FOR EACH ROW
            EXECUTE FUNCTION format_ticket_number();
        ");

        DB::statement("
            CREATE TRIGGER format_ticket_number_before_update
            BEFORE UPDATE ON purchases
            FOR EACH ROW
            WHEN (OLD.ticket_number IS DISTINCT FROM NEW.ticket_number)
            EXECUTE FUNCTION format_ticket_number();
        ");
    }

    public function down(): void
    {

        DB::statement("DROP TRIGGER IF EXISTS format_ticket_number_before_insert ON purchases;");
        DB::statement("DROP TRIGGER IF EXISTS format_ticket_number_before_update ON purchases;");
        DB::statement("DROP FUNCTION IF EXISTS format_ticket_number();");
    }
};
