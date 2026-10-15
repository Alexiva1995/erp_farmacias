<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Mail\EcommerceOrderNotificationMail;
use App\Models\EcommerceOrder;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EcommerceMailService
{
    /**
     * Retorna las plantillas por defecto del sistema.
     */
    public function getDefaultTemplates(): array
    {
        return [
            'order_created' => [
                'enabled' => true,
                'subject' => '¡Hemos recibido tu pedido #{pedido_id}! - {tienda_nombre}',
                'body' => "Hola {cliente_nombre},\n\n¡Gracias por tu compra en {tienda_nombre}! Hemos recibido tu pedido exitosamente y se encuentra en estado de revisión y procesamiento.\n\nInformación del Pedido:\n• Número de Pedido: #{pedido_id}\n• Fecha: {pedido_fecha}\n• Método de Pago: {metodo_pago}\n• Método de Entrega: {metodo_envio}\n• Dirección de Entrega: {cliente_direccion}\n\nDetalle de Productos:\n{tabla_productos}\n\nMonto Total a Pagar: {pedido_total}\n\nTe notificaremos por este mismo medio tan pronto tu pedido sea confirmado.\n\nSaludos cordiales,\nEl equipo de {tienda_nombre}",
            ],
            'order_approved' => [
                'enabled' => true,
                'subject' => '¡Tu pedido #{pedido_id} ha sido confirmado y aprobado! - {tienda_nombre}',
                'body' => "Hola {cliente_nombre},\n\n¡Excelentes noticias! Tu pedido #{pedido_id} ha sido confirmado y aprobado con éxito. Ya estamos preparando tus artículos para su entrega.\n\nResumen de tu compra:\n{tabla_productos}\n\nMonto Total: {pedido_total}\n• Método de Entrega: {metodo_envio}\n• Dirección de Envío: {cliente_direccion}\n\n¡Muchas gracias por tu confianza!\n\nSaludos cordiales,\nEl equipo de {tienda_nombre}",
            ],
            'order_shipped' => [
                'enabled' => true,
                'subject' => '¡Tu pedido #{pedido_id} va en camino! - {tienda_nombre}',
                'body' => "Hola {cliente_nombre},\n\n¡Tu pedido #{pedido_id} ya ha sido despachado y va en camino a tu destino!\n\nDetalles del Despacho:\n• Método de Entrega: {metodo_envio}\n• Dirección de Entrega: {cliente_direccion}\n\n{tabla_productos}\n\nMonto Total: {pedido_total}\n\nSi tienes alguna pregunta o requieres asistencia adicional, no dudes en responder directamente a este correo.\n\nSaludos cordiales,\nEl equipo de {tienda_nombre}",
            ],
        ];
    }

    /**
     * Aplica dinámicamente la configuración SMTP guardada en GeneralSetting.
     */
    public function applySmtpConfiguration(?GeneralSetting $setting = null): void
    {
        $setting = $setting ?? GeneralSetting::first();

        if (!$setting || empty($setting->mail_host) || empty($setting->mail_username)) {
            return;
        }

        $mailer = $setting->mail_mailer ?: 'smtp';
        $host = $setting->mail_host;
        $port = (int) ($setting->mail_port ?: 587);
        $encryption = $setting->mail_encryption ?: 'tls';
        $username = $setting->mail_username;
        $password = $setting->mail_password;
        $fromAddress = $setting->mail_from_address ?: $username;
        $fromName = $setting->mail_from_name ?: ($setting->app_name ?: config('app.name'));

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        // Purgar instancia anterior del mailer para forzar recarga de configuración en tiempo de ejecución
        Mail::purge('smtp');
    }

    /**
     * Enviar correo de notificación para un pedido según la clave de plantilla.
     */
    public function sendOrderNotification(int|EcommerceOrder $order, string $templateKey): bool
    {
        try {
            $setting = GeneralSetting::first();
            $this->applySmtpConfiguration($setting);

            if (is_int($order)) {
                $orderModel = EcommerceOrder::with(['items.product', 'items.variant'])->find($order);
            } else {
                $orderModel = $order;
                $orderModel->loadMissing(['items.product', 'items.variant']);
            }

            if (!$orderModel || empty($orderModel->customer_email)) {
                return false;
            }

            // Obtener plantillas configuradas o las predeterminadas
            $templates = $setting?->ecommerce_email_templates;
            if (!is_array($templates) || empty($templates)) {
                $templates = $this->getDefaultTemplates();
            }

            $template = $templates[$templateKey] ?? null;
            if (!$template || empty($template['enabled'])) {
                return false;
            }

            $subject = $this->replaceShortcodes($template['subject'] ?? '', $orderModel, $setting);
            $rawBody = $this->replaceShortcodes($template['body'] ?? '', $orderModel, $setting);

            // Generar envoltorio HTML profesional y responsive
            $htmlContent = $this->wrapInHtmlTemplate($subject, $rawBody, $setting);

            Mail::to($orderModel->customer_email)->send(
                new EcommerceOrderNotificationMail($subject, $htmlContent)
            );

            return true;
        } catch (Throwable $e) {
            Log::error("[ECOMMERCE_MAIL] Error al enviar correo de notificación ({$templateKey}): " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Reemplaza todos los shortcodes en una cadena de texto.
     */
    public function replaceShortcodes(string $text, EcommerceOrder $order, ?GeneralSetting $setting = null): string
    {
        $setting = $setting ?? GeneralSetting::first();

        $clientName = $order->customer_name ?: 'Estimado/a Cliente';
        $clientEmail = $order->customer_email ?: '';
        $clientPhone = $order->customer_phone ?: 'No registrado';
        $clientAddress = $order->shipping_address ?: 'Retiro en tienda';
        $orderId = (string) $order->id;
        $orderDate = $order->created_at ? $order->created_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A');
        $storeName = $setting?->app_name ?: 'Nuestra Tienda';

        // Formato del total
        $orderTotal = '$' . number_format((float) $order->total_amount, 2) . ' USD';
        if (!empty($order->currency) && !empty($order->total_in_currency)) {
            $currency = strtoupper($order->currency);
            if (in_array($currency, ['VES', 'BS', 'BS.'])) {
                $orderTotal = 'Bs. ' . number_format((float) $order->total_in_currency, 2, ',', '.') . ' (' . $orderTotal . ')';
            } elseif ($currency === 'COP') {
                $orderTotal = 'COP$ ' . number_format((float) $order->total_in_currency, 0, ',', '.') . ' (' . $orderTotal . ')';
            }
        }

        // Método de pago amigable
        $paymentMethodNames = [
            'pago_movil' => 'Pago Móvil',
            'transferencia' => 'Transferencia Bancaria',
            'zelle' => 'Zelle',
            'binance' => 'Binance Pay / USDT',
            'contraentrega' => 'Pago Contraentrega / Efectivo',
            'cash' => 'Efectivo',
            'simulated' => 'Pago en Línea',
        ];
        $paymentKey = strtolower((string) $order->payment_method);
        $paymentMethod = $paymentMethodNames[$paymentKey] ?? ucfirst(str_replace('_', ' ', $paymentKey));

        // Método de envío amigable
        $shippingMethod = !empty($order->shipping_method) ? ucfirst($order->shipping_method) : 'Entrega / Envío Estándar';

        // Tabla HTML de productos
        $productsTableHtml = $this->buildProductsTableHtml($order);

        $replacements = [
            '{cliente_nombre}'    => $clientName,
            '{cliente_email}'     => $clientEmail,
            '{cliente_telefono}'  => $clientPhone,
            '{cliente_direccion}' => $clientAddress,
            '{pedido_id}'         => $orderId,
            '{pedido_fecha}'      => $orderDate,
            '{pedido_total}'      => $orderTotal,
            '{metodo_pago}'       => $paymentMethod,
            '{metodo_envio}'      => $shippingMethod,
            '{tienda_nombre}'     => $storeName,
            '{tabla_productos}'   => $productsTableHtml,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Genera la tabla HTML compatible con clientes de correo para los productos.
     */
    public function buildProductsTableHtml(EcommerceOrder $order): string
    {
        $items = $order->items;
        if (!$items || $items->isEmpty()) {
            return '<p style="color: #64748b; font-style: italic;">No hay ítems registrados.</p>';
        }

        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 16px 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">';
        $html .= '<thead>';
        $html .= '<tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">';
        $html .= '<th style="padding: 10px 12px; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase;">Producto</th>';
        $html .= '<th style="padding: 10px 12px; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase; text-align: center;">Cant.</th>';
        $html .= '<th style="padding: 10px 12px; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase; text-align: right;">Precio</th>';
        $html .= '<th style="padding: 10px 12px; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase; text-align: right;">Subtotal</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $totalSubtotal = 0;
        foreach ($items as $item) {
            $product = $item->product;
            $variant = $item->variant;
            $productName = $product ? $product->name : "Producto #{$item->product_id}";
            $variantInfo = $variant ? $variant->attribute_value : '';
            $colorHex = $variant ? $variant->color_hex : null;
            $qty = (int) $item->quantity;
            $price = (float) $item->price;
            $subtotal = $qty * $price;
            $totalSubtotal += $subtotal;

            $imageUrl = null;
            if ($variant && !empty($variant->photo_url)) {
                $imageUrl = $variant->photo_url;
            } elseif ($product && !empty($product->photo_url)) {
                $imageUrl = $product->photo_url;
            }

            $html .= '<tr style="border-bottom: 1px solid #f1f5f9;">';
            $html .= '<td style="padding: 12px;">';
            $html .= '<div style="display: flex; align-items: center; gap: 10px;">';
            if ($imageUrl) {
                $html .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="" width="38" height="38" style="width: 38px; height: 38px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block; vertical-align: middle; margin-right: 8px;" />';
            }
            $html .= '<div style="display: inline-block; vertical-align: middle;">';
            $html .= '<span style="font-weight: 600; color: #0f172a; display: block;">' . htmlspecialchars($productName) . '</span>';
            if ($variantInfo) {
                $html .= '<div style="font-size: 11px; color: #64748b; margin-top: 2px;">';
                if ($colorHex) {
                    $html .= '<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: ' . htmlspecialchars($colorHex) . '; margin-right: 4px; vertical-align: middle; border: 1px solid #ccc;"></span>';
                }
                $html .= '<span>Tono: ' . htmlspecialchars($variantInfo) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</td>';

            $html .= '<td style="padding: 12px; text-align: center; font-weight: 600; color: #334155;">x' . $qty . '</td>';
            $html .= '<td style="padding: 12px; text-align: right; color: #64748b;">$' . number_format($price, 2) . '</td>';
            $html .= '<td style="padding: 12px; text-align: right; font-weight: 700; color: #0f172a;">$' . number_format($subtotal, 2) . '</td>';
            $html .= '</tr>';
        }

        // Costo de envío si existe
        if ((float) $order->shipping_cost > 0) {
            $html .= '<tr style="border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">';
            $html .= '<td colspan="3" style="padding: 8px 12px; text-align: right; color: #64748b; font-size: 12px;">Costo de Envío:</td>';
            $html .= '<td style="padding: 8px 12px; text-align: right; font-weight: 600; color: #334155;">$' . number_format((float) $order->shipping_cost, 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Envuelve el cuerpo del mensaje en una estructura HTML tipo plantilla editorial.
     */
    private function wrapInHtmlTemplate(string $title, string $bodyContent, ?GeneralSetting $setting = null): string
    {
        $storeName = $setting?->app_name ?: 'Tienda Online';
        $logoUrl = $setting?->app_logo;
        $primaryColor = $setting?->primary_color ?: '#1E1614';

        // Si el contenido no tiene etiquetas HTML de bloque, convertir saltos de línea a párrafos
        $formattedBody = str_contains($bodyContent, '<table') || str_contains($bodyContent, '<p')
            ? nl2br($bodyContent)
            : '<p style="margin-bottom: 16px; line-height: 1.7;">' . nl2br(htmlspecialchars($bodyContent)) . '</p>';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 30px 10px;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          
          <!-- Encabezado -->
          <tr>
            <td style="background-color: {$primaryColor}; padding: 24px 30px; text-align: center; color: #ffffff;">
              <h1 style="margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">{$storeName}</h1>
            </td>
          </tr>

          <!-- Título del mensaje -->
          <tr>
            <td style="padding: 28px 30px 10px 30px;">
              <h2 style="margin: 0; font-size: 18px; color: #0f172a; font-weight: 700; line-height: 1.4;">{$title}</h2>
            </td>
          </tr>

          <!-- Cuerpo -->
          <tr>
            <td style="padding: 10px 30px 30px 30px; font-size: 14px; line-height: 1.7; color: #334155;">
              {$formattedBody}
            </td>
          </tr>

          <!-- Pie de página -->
          <tr>
            <td style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; line-height: 1.5;">
              Este es un correo automático enviado por <strong>{$storeName}</strong> con relación a tu pedido.<br>
              Por favor no compartas información confidencial o contraseñas.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Enviar correo de prueba para verificar configuración SMTP.
     */
    public function sendTestMail(string $toEmail): array
    {
        try {
            $setting = GeneralSetting::first();
            $this->applySmtpConfiguration($setting);

            $subject = 'Correo de Prueba - Configuración SMTP Exitosa';
            $body = "¡Felicidades!\n\nEste correo confirma que la configuración de tu servidor SMTP en " . ($setting?->app_name ?: 'el sistema') . " está funcionando correctamente.\n\nFecha y hora: " . now()->format('d/m/Y h:i:s A');

            $html = $this->wrapInHtmlTemplate($subject, $body, $setting);

            Mail::to($toEmail)->send(new EcommerceOrderNotificationMail($subject, $html));

            return [
                'success' => true,
                'message' => "Correo de prueba enviado con éxito a {$toEmail}.",
            ];
        } catch (Throwable $e) {
            Log::error('[ECOMMERCE_MAIL_TEST] Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al conectar con el servidor SMTP: ' . $e->getMessage(),
            ];
        }
    }
}
