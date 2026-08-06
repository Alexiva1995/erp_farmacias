<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TelegramModuleEnum;
use App\Models\TelegramChannel;
use App\Models\TelegramCommand;
use App\Models\TelegramConfig;
use Illuminate\Database\Seeder;

class TelegramCommandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpiar comando redundante previo '/cierres' si existiera
        TelegramCommand::where('command', '/cierres')->delete();

        // 2. Configuración global por defecto si no existe
        $config = TelegramConfig::firstOrCreate(
            ['id' => 1],
            [
                'bot_token' => config('services.telegram.bot_token'),
                'chat_id' => config('services.telegram.chat_id'),
                'admin_chat_id' => config('services.telegram.admin_chat_id'),
                'webhook_url' => config('app.url') . '/api/public/telegram/webhook',
                'is_active' => true,
            ]
        );

        // 3. Crear el Canal General por defecto si no existen canales registrados
        $defaultChatId = $config->chat_id ?: (config('services.telegram.chat_id') ?: '-100123456789');
        $defaultChannel = TelegramChannel::firstOrCreate(
            ['telegram_config_id' => $config->id, 'module' => 'general'],
            [
                'name' => 'Canal General Principal',
                'chat_id' => $defaultChatId,
                'description' => 'Canal principal asignado a las notificaciones globales y del sistema.',
                'is_active' => true,
            ]
        );

        $commands = [
            // ==================== GENERALES ====================
            [
                'module' => TelegramModuleEnum::GENERALES->value,
                'command' => '/tasas',
                'alias' => 'Notificación de Tasas Actualizadas',
                'description' => 'Notificación automática enviada a los canales de Telegram cuando se actualizan las tasas cambiarias (BCV / COP).',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::GENERALES->value,
                'command' => '/cierre_individual',
                'alias' => 'Notificación de Cierre Individual (Cajero)',
                'description' => 'Notificación enviada a Telegram cada vez que un cajero o vendedor realiza su entrega de turno o cierre de caja ciego.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::GENERALES->value,
                'command' => '/cierre_general',
                'alias' => 'Notificación de Cierre General Diario (Medianoche)',
                'description' => 'Notificación automática del consolidado total del día enviada a medianoche tras el cierre de jornada del sistema.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::GENERALES->value,
                'command' => '/cancelar',
                'alias' => 'Cancelar Flujo Activo',
                'description' => 'Permite abortar o limpiar cualquier proceso conversacional o flujo interactivo activo en el bot.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],

            // ==================== FARMACIA ====================
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/facturas_cargadas',
                'alias' => 'Revisión & Aprobación de Facturas Cargadas',
                'description' => 'Muestra las facturas cargadas una a una alertando discrepancias de costo contra la auto-orden con botones para aprobar, devolver o ver detalle.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/pagos',
                'alias' => 'Gestión de Pagos Pendientes',
                'description' => 'Abre la cola interactiva para gestionar y registrar pagos a proveedores.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/pagos_pendientes',
                'alias' => 'Reporte Pagos 7 Días',
                'description' => 'Muestra el resumen de facturas por vencer en los próximos 7 días.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/deudas',
                'alias' => 'Listado Detallado de Deudas',
                'description' => 'Muestra el total adeudado desglosado por proveedor.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/pedido',
                'alias' => 'Pedido Automático Inteligente (IA)',
                'description' => 'Genera y envía los pedidos automáticos basados en el motor de Asistente de IA de Reabastecimiento.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::FARMACIA->value,
                'command' => '/fallas',
                'alias' => 'Gestión & Alertas de Fallas de Stock',
                'description' => 'Notifica y gestiona interactivamente las fallas de stock detectadas en mostrador o ventas sin inventario.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],

            // ==================== RESTAURANTE ====================
            [
                'module' => TelegramModuleEnum::RESTAURANTE->value,
                'command' => '/registrar_factura',
                'alias' => 'Registrar Factura / Foto',
                'description' => 'Inicia el flujo de lectura e ingreso de facturas con escaneo por Gemini IA.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::RESTAURANTE->value,
                'command' => '/registrar_productos',
                'alias' => 'Registro Rápido de Productos',
                'description' => 'Permite dar de alta una lista de productos en lote.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::RESTAURANTE->value,
                'command' => '/registrar_frutas',
                'alias' => 'Carga Ultra Rápida de Frutas',
                'description' => 'Permite ingresar compras de insumos perecederos y frutas rápidamente.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],

            // ==================== COSMÉTICOS ====================
            [
                'module' => TelegramModuleEnum::COSMETICOS->value,
                'command' => '/catalogo_promociones',
                'alias' => 'Catálogo & Promociones',
                'description' => 'Notifica las promociones activas y ofertas destacadas de productos cosméticos.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::COSMETICOS->value,
                'command' => '/consultar_stock_cosmeticos',
                'alias' => 'Consulta de Stock Cosméticos',
                'description' => 'Permite verificar la disponibilidad en tiempo real de productos de belleza.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],

            // ==================== ALQUILERES ====================
            [
                'module' => TelegramModuleEnum::ALQUILERES->value,
                'command' => 'cancelar reserva',
                'alias' => 'Cancelar Reserva de Espacio',
                'description' => 'Permite buscar y cancelar reservaciones de canchas o espacios.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::ALQUILERES->value,
                'command' => '/fijos',
                'alias' => 'Consulta Horarios Fijos',
                'description' => 'Muestra la lista de turnos fijos programados para el día actual.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
            [
                'module' => TelegramModuleEnum::ALQUILERES->value,
                'command' => '/reservas_dia',
                'alias' => 'Notificación Diaria de Reservas (Mediodía)',
                'description' => 'Reporte consolidado automático enviado todos los días a mediodía con las reservaciones y turnos fijos programados para la jornada.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],

            // ==================== SYSTEM ====================
            [
                'module' => TelegramModuleEnum::SYSTEM->value,
                'command' => '/cancelar',
                'alias' => 'Cancelar Flujo Activo',
                'description' => 'Limpia el estado conversacional actual del bot y restablece el flujo.',
                'channel_id' => $defaultChannel->id,
                'is_active' => true,
            ],
        ];

        foreach ($commands as $cmdData) {
            TelegramCommand::updateOrCreate(
                [
                    'module' => $cmdData['module'],
                    'command' => $cmdData['command'],
                ],
                $cmdData
            );
        }
    }
}
