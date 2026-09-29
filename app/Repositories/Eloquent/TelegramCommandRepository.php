<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\TelegramCommandRepositoryInterface;
use App\Models\TelegramCommand;
use Illuminate\Database\Eloquent\Collection;

class TelegramCommandRepository implements TelegramCommandRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getByModule(string $module): Collection
    {
        $count = TelegramCommand::where('module', $module)->count();
        if ($count === 0) {
            $this->seedDefaultCommandsForModule($module);
        }

        return TelegramCommand::query()
            ->with(['channel:id,name,chat_id,is_active'])
            ->select([
                'id',
                'module',
                'channel_id',
                'command',
                'alias',
                'description',
                'is_active',
                'payload_template',
                'created_at',
                'updated_at',
            ])
            ->where('module', $module)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Sembrar automáticamente los comandos y notificaciones predeterminados del módulo.
     */
    protected function seedDefaultCommandsForModule(string $module): void
    {
        $defaultCommands = [
            'generales' => [
                [
                    'command' => '/tasas',
                    'alias' => 'Notificación de Tasas Actualizadas',
                    'description' => 'Notificación automática enviada a los canales de Telegram cuando se actualizan las tasas cambiarias (BCV / COP).',
                    'is_active' => true,
                ],
                [
                    'command' => '/cierre_individual',
                    'alias' => 'Notificación de Cierre Individual (Cajero)',
                    'description' => 'Notificación enviada a Telegram cada vez que un cajero o vendedor realiza su entrega de turno o cierre de caja ciego.',
                    'is_active' => true,
                ],
                [
                    'command' => '/cierre_general',
                    'alias' => 'Notificación de Cierre General Diario (Medianoche)',
                    'description' => 'Notificación automática del consolidado total del día enviada a medianoche tras el cierre de jornada del sistema.',
                    'is_active' => true,
                ],
                [
                    'command' => '/cancelar',
                    'alias' => 'Cancelar Flujo Activo',
                    'description' => 'Permite abortar o limpiar cualquier proceso conversacional o flujo interactivo activo en el bot.',
                    'is_active' => true,
                ],
            ],
            'farmacia' => [
                [
                    'command' => '/facturas_cargadas',
                    'alias' => 'Revisión & Aprobación de Facturas Cargadas',
                    'description' => 'Muestra las facturas cargadas una a una alertando discrepancias de costo contra la auto-orden con botones para aprobar, devolver o ver detalle.',
                    'is_active' => true,
                ],
                [
                    'command' => '/pagos',
                    'alias' => 'Gestión de Pagos Pendientes',
                    'description' => 'Abre la cola interactiva para gestionar y registrar pagos a proveedores.',
                    'is_active' => true,
                ],
                [
                    'command' => '/pagos_pendientes',
                    'alias' => 'Reporte Pagos 7 Días',
                    'description' => 'Muestra el resumen de facturas por vencer en los próximos 7 días.',
                    'is_active' => true,
                ],
                [
                    'command' => '/deudas',
                    'alias' => 'Listado Detallado de Deudas',
                    'description' => 'Muestra el total adeudado desglosado por proveedor.',
                    'is_active' => true,
                ],
                [
                    'command' => '/pedido',
                    'alias' => 'Pedido Automático Inteligente (IA)',
                    'description' => 'Genera y envía los pedidos automáticos basados en el motor de Asistente de IA de Reabastecimiento.',
                    'is_active' => true,
                ],
                [
                    'command' => '/fallas',
                    'alias' => 'Gestión & Alertas de Fallas de Stock',
                    'description' => 'Notifica y gestiona interactivamente las fallas de stock detectadas en mostrador o ventas sin inventario.',
                    'is_active' => true,
                ],
            ],
            'restaurante' => [
                [
                    'command' => '/registrar_factura',
                    'alias' => 'Registrar Factura / Foto',
                    'description' => 'Inicia el flujo de lectura e ingreso de facturas con escaneo por Gemini IA.',
                    'is_active' => true,
                ],
                [
                    'command' => '/registrar_productos',
                    'alias' => 'Registro Rápido de Productos',
                    'description' => 'Permite dar de alta una lista de productos en lote.',
                    'is_active' => true,
                ],
                [
                    'command' => '/registrar_frutas',
                    'alias' => 'Carga Ultra Rápida de Frutas',
                    'description' => 'Permite ingresar compras de insumos perecederos y frutas rápidamente.',
                    'is_active' => true,
                ],
            ],
            'cosmeticos' => [
                [
                    'command' => '/catalogo_promociones',
                    'alias' => 'Catálogo & Promociones',
                    'description' => 'Notifica las promociones activas y ofertas destacadas de productos cosméticos.',
                    'is_active' => true,
                ],
                [
                    'command' => '/consultar_stock_cosmeticos',
                    'alias' => 'Consulta de Stock Cosméticos',
                    'description' => 'Permite verificar la disponibilidad en tiempo real de productos de belleza.',
                    'is_active' => true,
                ],
            ],
            'alquileres' => [
                [
                    'command' => 'cancelar reserva',
                    'alias' => 'Cancelar Reserva de Espacio',
                    'description' => 'Permite buscar y cancelar reservaciones de canchas o espacios.',
                    'is_active' => true,
                ],
                [
                    'command' => '/fijos',
                    'alias' => 'Consulta Horarios Fijos',
                    'description' => 'Muestra la lista de turnos fijos programados para el día actual.',
                    'is_active' => true,
                ],
                [
                    'command' => '/reservas_dia',
                    'alias' => 'Notificación Diaria de Reservas (Mediodía)',
                    'description' => 'Reporte consolidado automático enviado todos los días a mediodía con las reservaciones y turnos fijos programados para la jornada.',
                    'is_active' => true,
                ],
            ],
            'system' => [
                [
                    'command' => '/cancelar',
                    'alias' => 'Cancelar Flujo Activo',
                    'description' => 'Limpia el estado conversacional actual del bot y restablece el flujo.',
                    'is_active' => true,
                ],
            ],
        ];

        if (!isset($defaultCommands[$module])) {
            return;
        }

        $defaultChannelId = \App\Models\TelegramChannel::where('is_active', true)->value('id');

        foreach ($defaultCommands[$module] as $item) {
            TelegramCommand::updateOrCreate(
                [
                    'module' => $module,
                    'command' => $item['command'],
                ],
                [
                    'module' => $module,
                    'command' => $item['command'],
                    'alias' => $item['alias'],
                    'description' => $item['description'],
                    'channel_id' => $defaultChannelId,
                    'is_active' => $item['is_active'],
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): TelegramCommand
    {
        return TelegramCommand::query()
            ->with(['channel:id,name,chat_id,is_active'])
            ->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    public function update(TelegramCommand $command, array $data): TelegramCommand
    {
        $command->update($data);
        return $command->fresh(['channel:id,name,chat_id,is_active']);
    }

    /**
     * {@inheritdoc}
     */
    public function toggleActive(TelegramCommand $command, bool $isActive): TelegramCommand
    {
        $command->update(['is_active' => $isActive]);
        return $command->fresh(['channel:id,name,chat_id,is_active']);
    }
}
