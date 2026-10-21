<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleEnum: int
{
    case ADMIN = 1;
    case SUPERVISOR = 2;
    case EMPLOYEE = 3;
    case ADMINISTRADOR = 4;
    case CONTADOR = 5;

    /**
     * Obtener el nombre para presentación.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::SUPERVISOR => 'Supervisor',
            self::EMPLOYEE => 'Employee',
            self::ADMINISTRADOR => 'Administrador',
            self::CONTADOR => 'Contador',
        };
    }

    /**
     * Lista de permisos de menú por defecto para el rol Contador.
     *
     * @return array<int, string>
     */
    public static function defaultFiscalMenuPermissions(): array
    {
        return [
            'module::fiscal',
            'fiscal-home',
            'fiscal-history',
            'fiscal-reporte-z',
            'fiscal-control',
            'iva-general',
            'islr-general',
            'fiscal-retenciones',
            'fiscal-contribuciones',
            'module::fiscal::libros',
            'fiscal-libros-compras',
        ];
    }
}
