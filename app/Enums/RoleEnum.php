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
    case ASESOR_COMERCIAL = 6;
    case TELEMARKETING = 7;
    case COBRANZAS = 8;
    case ALMACEN_DESPACHO = 9;

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
            self::ASESOR_COMERCIAL => 'Asesor Comercial',
            self::TELEMARKETING => 'Telemarketing',
            self::COBRANZAS => 'Cobranzas y Crédito',
            self::ALMACEN_DESPACHO => 'Almacén y Despacho',
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

    /**
     * Permisos de menú por defecto para los roles operativos de Droguería B2B.
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultDrogueriaMenuPermissions(): array
    {
        return [
            self::ASESOR_COMERCIAL->label() => [
                'module::droguería_b2b',
                'wholesale-portfolio',
                'wholesale-telemarketing-workstation',
                'wholesale-marketplace',
                'wholesale-collections',
            ],
            self::TELEMARKETING->label() => [
                'module::droguería_b2b',
                'wholesale-telemarketing-workstation',
                'wholesale-portfolio',
            ],
            self::COBRANZAS->label() => [
                'module::droguería_b2b',
                'wholesale-collections',
                'wholesale-portfolio',
                'module::finanzas',
                'finances-pending-payments',
                'finances-payment-history',
            ],
            self::ALMACEN_DESPACHO->label() => [
                'module::droguería_b2b',
                'wholesale-wms-dispatch-board',
                'module::inventario',
                'inventory-products',
                'inventory-expiration',
            ],
        ];
    }
}
