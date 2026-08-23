<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  orderData: { type: Object, required: true },
  cashData: { type: Object, required: true },
  isPdf: { type: Boolean, default: true },
});

const brandingStore = useBrandingStore();
const logoSrc = computed(() => brandingStore.settings?.app_logo || BASE64_LOGO_DATA);

// Calcular totales de la orden sumando sus detalles o usando el total registrado
const getOrderTotal = (order) => {
  if (order.details && Array.isArray(order.details) && order.details.length > 0) {
    const sum = order.details.reduce((acc, detail) => acc + parseFloat(detail.price || 0), 0);
    if (sum > 0) return sum;
  }
  return parseFloat(order.total_amount || 0);
};
// Mapeo de traducción de métodos de pago al español
const translatePaymentMethod = (method) => {
  const map = {
    cash: 'Efectivo',
    transfer: 'Transferencia',
    mobile: 'Pago Móvil',
    mobile_payment: 'Pago Móvil',
    debit_card: 'Tarjeta de Débito',
    credit_card: 'Tarjeta de Crédito',
    card: 'Tarjeta',
    paypal: 'PayPal',
    binance: 'Binance',
    zelle: 'Zelle',
    credit: 'Crédito',
  };
  const key = String(method || '').toLowerCase().trim();
  return map[key] || method || 'Pago';
};

// Obtener el desglose de montos por método de pago para abonos
const getCreditPaymentMethodsBreakdown = (payment) => {
  const methods = payment.method_Payment || [];
  if (!Array.isArray(methods) || methods.length === 0) return [];
  return methods.map(m => {
    const rawLabel = m.method || m.type || 'Pago';
    const label = translatePaymentMethod(rawLabel);
    const amount = parseFloat(m.amount || 0);
    const curr = (m.currency || 'USD').toUpperCase();
    return `${label}: ${formatCurrency(amount, curr)}`;
  });
};

// Obtener total pagado en un abono
const getCreditPaymentTotal = (payment) => {
  const methods = payment.method_Payment || [];
  if (Array.isArray(methods) && methods.length > 0) {
    const sum = methods.reduce((acc, m) => acc + parseFloat(m.amount || 0), 0);
    if (sum > 0) {
      const curr = (methods[0]?.currency || 'USD').toUpperCase();
      return formatCurrency(sum, curr);
    }
  }
  return formatCurrency(parseFloat(payment.money_returns || 0), 'USD');
};
</script>

<template>
  <div style="color: #333; font-family: Helvetica, Arial, sans-serif; width: 100%;">
    <div class="pa-4 text-left" style="border: 1px solid #ddd; border-radius: 8px; background: #fff;">
      <!-- CABECERA DEL REPORTE -->
      <table style="width: 100%; margin-bottom: 20px;">
        <tbody>
          <tr>
            <td style="width: 30%; text-align: left; vertical-align: top;">
              <img v-if="logoSrc" :src="logoSrc" alt="Logo" style="width: 120px;" />
            </td>
            <td style="width: 70%; text-align: right; vertical-align: top;">
              <h2 style="margin: 0; color: #2c3e50; font-size: 20px;">Reporte de Ventas por Cierre</h2>
              <p style="color: #555; font-size: 14px; margin: 5px 0 0 0;">
                Cierre N°: <strong>{{ props.cashData.id }}</strong>
              </p>
              <p style="color: #555; font-size: 14px; margin: 5px 0 0 0;">
                Fecha: {{ formatDateTime(props.cashData.closing_date, "date") }}
              </p>
              <p style="color: #555; font-size: 14px; margin: 5px 0 0 0;">
                Vendedor: <strong>{{ props.cashData.seller?.username }}</strong>
              </p>
            </td>
          </tr>
        </tbody>
      </table>

      <hr style="border: 0; border-top: 2px solid #34495e; margin-bottom: 20px;" />

      <!-- DETALLE DE VENTAS (TABLA PRINCIPAL) -->
      <div style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #ecf0f1; color: #2c3e50; font-size: 16px; margin: 0 0 15px 0; padding-bottom: 5px;">
          Detalle de Facturas
        </h3>

        <table style="border-collapse: collapse; font-size: 13px; width: 100%;">
          <thead>
            <tr style="background-color: #f8f9fa;">
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 10%; text-align: center;">Factura #</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 60%; text-align: left;">Productos (Cantidad x Descripción)</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 30%; text-align: right;">Totales</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="order in props.orderData" :key="order.id">
              <!-- Fila con detalles de la orden -->
              <tr>
                <td style="padding: 10px; border: 1px solid #dee2e6; font-weight: bold; text-align: center; vertical-align: top;">
                  {{ order.id }}
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; vertical-align: top;">
                  <template v-if="order.details && order.details.length > 0">
                    <div v-for="detail in order.details" :key="detail.id" style="border-bottom: 1px dashed #eee; margin-bottom: 4px; padding-bottom: 4px;">
                      <span style="display: inline-block; font-weight: bold; min-width: 30px;">{{ detail.quantity }}x</span>
                      <span>{{ detail.product?.name || 'Producto' }} </span>
                      <span style="color: #444; font-weight: bold;">
                          - {{ formatCurrency(parseFloat(detail.price || 0), (order.currency || 'USD').toUpperCase()) }}
                      </span>
                      <span style="color: #888; font-size: 11px;"> (ID: {{ detail.product_id }})</span>
                    </div>
                  </template>
                  <span v-else style="color: #888; font-style: italic;">
                    Sin desglose de productos
                  </span>
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; background-color: #fafbfc; text-align: right; vertical-align: bottom;">
                  <div style="color: #2c3e50; font-size: 14px; font-weight: bold;">
                    Total: {{ formatCurrency(getOrderTotal(order), (order.currency || 'USD').toUpperCase()) }}
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!props.orderData || props.orderData.length === 0">
              <td colspan="3" style="padding: 20px; border: 1px solid #dee2e6; color: #7f8c8d; text-align: center;">
                No hay ventas registradas en este cierre.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- DETALLE DE ABONOS DE CRÉDITO -->
      <div v-if="props.cashData.credit_payments && props.cashData.credit_payments.length > 0" style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #ecf0f1; color: #2c3e50; font-size: 16px; margin: 25px 0 15px 0; padding-bottom: 5px;">
          Abonos de Crédito Recibidos
        </h3>

        <table style="border-collapse: collapse; font-size: 13px; width: 100%;">
          <thead>
            <tr style="background-color: #f8f9fa;">
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 10%; text-align: center;">Abono #</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 35%; text-align: left;">Cliente</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 30%; text-align: left;">Método de Pago</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; width: 25%; text-align: right;">Monto</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in props.cashData.credit_payments" :key="payment.id">
              <td style="padding: 10px; border: 1px solid #dee2e6; font-weight: bold; text-align: center; vertical-align: top;">
                {{ payment.id }}
              </td>
              <td style="padding: 10px; border: 1px solid #dee2e6; vertical-align: top;">
                <div style="font-weight: bold; color: #2c3e50;">
                  {{ payment.client?.name || 'Cliente General' }}
                </div>
                <div v-if="payment.client?.identification" style="color: #888; font-size: 11px;">
                  C.I / RIF: {{ payment.client.identification }}
                </div>
              </td>
              <td style="padding: 10px; border: 1px solid #dee2e6; vertical-align: top;">
                <div v-for="(method, mIdx) in getCreditPaymentMethodsBreakdown(payment)" :key="mIdx" style="margin-bottom: 2px;">
                  <span style="font-size: 12px; color: #555;">{{ method }}</span>
                </div>
                <span v-if="getCreditPaymentMethodsBreakdown(payment).length === 0" style="color: #888; font-style: italic; font-size: 12px;">
                  Efectivo / No especificado
                </span>
              </td>
              <td style="padding: 10px; border: 1px solid #dee2e6; background-color: #fafbfc; text-align: right; vertical-align: middle;">
                <div style="color: #2e7d32; font-size: 14px; font-weight: bold;">
                  {{ getCreditPaymentTotal(payment) }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- PIE DE PÁGINA -->
      <div style="border-top: 1px solid #ecf0f1; color: #95a5a6; font-size: 11px; margin-top: 40px; padding-top: 10px; text-align: center;">
        Reporte generado automáticamente por el sistema financiero.
      </div>
    </div>
  </div>
</template>
