<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, defineProps } from "vue";

const props = defineProps({
  orderData: { type: Object, required: true },
  cashData: { type: Object, required: true },
  isPdf: { type: Boolean, default: true },
});

const logoSrc = computed(() => BASE64_LOGO_DATA);

// Calcular totales de la orden sumando sus detalles
const getOrderTotal = (order) => {
  return order.details.reduce((sum, detail) => sum + parseFloat(detail.price), 0);
};

</script>

<template>
  <div style=" color: #333; font-family: Helvetica, Arial, sans-serif;inline-size: 100%;">
    <VCard variant="outlined" class="pa-4 text-start" style="border: 1px solid #ddd; border-radius: 8px; background: #fff;">
      <!-- CABECERA DEL REPORTE -->
      <table style="inline-size: 100%; margin-block-end: 20px;">
        <tr>
          <td style="inline-size: 30%; text-align: start; vertical-align: top;">
            <img :src="logoSrc" alt="Logo" style="inline-size: 120px;" />
          </td>
          <td style="inline-size: 70%; text-align: end; vertical-align: top;">
            <h2 style="margin: 0; color: #2c3e50; font-size: 20px;">Reporte de Ventas por Cierre</h2>
            <p style=" color: #555; font-size: 14px;margin-block: 5px 0; margin-inline: 0;">
              Cierre N°: <strong>{{ props.cashData.id }}</strong>
            </p>
            <p style=" color: #555; font-size: 14px;margin-block: 5px 0; margin-inline: 0;">
              Fecha: {{ formatDateTime(props.cashData.closing_date, "date") }}
            </p>
            <p style=" color: #555; font-size: 14px;margin-block: 5px 0; margin-inline: 0;">
              Vendedor: <strong>{{ props.cashData.seller?.username }}</strong>
            </p>
          </td>
        </tr>
      </table>

      <hr style="border: 0; border-block-start: 2px solid #34495e; margin-block-end: 20px;" />

      <!-- DETALLE DE VENTAS (TABLA PRINCIPAL) -->
      <div style="margin-block-end: 20px;">
        <h3 style=" border-block-end: 1px solid #ecf0f1; color: #2c3e50; font-size: 16px; margin-block: 0 15px; padding-block-end: 5px;">
          Detalle de Facturas
        </h3>

        <table style=" border-collapse: collapse; font-size: 13px;inline-size: 100%;">
          <thead>
            <tr style="background-color: #f8f9fa;">
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; inline-size: 10%; text-align: center;">Factura #</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; inline-size: 60%; text-align: start;">Productos (Cantidad x Descripción)</th>
              <th style="padding: 10px; border: 1px solid #dee2e6; color: #495057; inline-size: 30%; text-align: end;">Totales</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(order, index) in props.orderData" :key="order.id">
              <!-- Fila con detalles de la orden -->
              <tr>
                <td style="padding: 10px; border: 1px solid #dee2e6; font-weight: bold; text-align: center; vertical-align: top;">
                  {{ order.id }}
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; vertical-align: top;">
                  <div v-for="detail in order.details" :key="detail.id" style=" border-block-end: 1px dashed #eee;margin-block-end: 4px; padding-block-end: 4px;">
                    <span style="display: inline-block; font-weight: bold; inline-size: 30px;">{{ detail.quantity }}x</span>
                    <span>{{ detail.product.name }} <span style="color: #888; font-size: 11px;">(ID: {{ detail.product_id }})</span></span>
                    <span style="float: inline-end;">{{ formatCurrency(parseFloat(detail.price), order.currency.toUpperCase()) }}</span>
                  </div>
                </td>
                <td style="padding: 10px; border: 1px solid #dee2e6; background-color: #fafbfc; text-align: end; vertical-align: bottom;">
                  <div style=" color: #2c3e50; font-size: 14px;font-weight: bold;">
                    Total: {{ formatCurrency(getOrderTotal(order), order.currency.toUpperCase()) }}
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

      <!-- PIE DE PÁGINA -->
      <div style=" border-block-start: 1px solid #ecf0f1; color: #95a5a6; font-size: 11px;margin-block-start: 40px; padding-block-start: 10px; text-align: center;">
        Reporte generado automáticamente por el sistema financiero.
      </div>
    </VCard>
  </div>
</template>
