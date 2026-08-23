<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";

const props = defineProps({
  cashData: {
    type: Object,
    default: () => ({}),
  },
  filteredCashClosings: {
    type: Array,
    default: () => [],
  },
  computedBcvRate: {
    type: [Number, String],
    default: 1,
  },
  computedCopRate: {
    type: [Number, String],
    default: 1,
  },
  totalSalesGlobal: {
    type: Number,
    default: 0,
  },
  totalCreditsUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalCopGlobal: {
    type: Number,
    default: 0,
  },
  totalBsGlobal: {
    type: Number,
    default: 0,
  },
  totalCashUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalTransferUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalCashCopGlobal: {
    type: Number,
    default: 0,
  },
  totalTransferCopGlobal: {
    type: Number,
    default: 0,
  },
  totalCashBsGlobal: {
    type: Number,
    default: 0,
  },
  totalPosBsGlobal: {
    type: Number,
    default: 0,
  },
  totalTransferBsGlobal: {
    type: Number,
    default: 0,
  },
});

const getCurrentTime = () => {
  return new Date().toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
};
</script>

<template>
  <div
    id="daily-cash-report"
    class="d-none"
  >
    <div class="header">
      <img
        v-if="BASE64_LOGO_DATA"
        :src="BASE64_LOGO_DATA"
        alt="Logo"
        class="logo"
      >
      <div class="company-name">
        FARMACIA BARRIO SUCRE 2024 C.A.
      </div>
      <div class="company-rif">
        R.I.F: J-50478962-1
      </div>
      <div class="document-title">
        CIERRE CONSOLIDADO DE OPERACIONES
      </div>
    </div>

    <div
      class="info-section"
      style="padding: 5px; margin-block-end: 10px;"
    >
      <table class="info-table">
        <tbody>
          <tr>
            <td style="inline-size: 40%;">
              <strong>CORRELATIVO:</strong> #{{ props.cashData?.id }}
            </td>
            <td style="inline-size: 30%;">
              <strong>TASAS:</strong> BCV: {{ props.computedBcvRate }} Bs.
            </td>
            <td style="inline-size: 30%; text-align: end;">
              <strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}
            </td>
          </tr>
          <tr>
            <td><strong>VENTA TOTAL:</strong> {{ formatCurrency(props.totalSalesGlobal, 'USD') }}</td>
            <td>COP: {{ props.computedCopRate }} COP</td>
            <td style="text-align: end;">
              <strong>HORA:</strong> {{ getCurrentTime() }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="section-header">
      DESGLOSE POR PERSONAL
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th style="padding: 4px; font-size: 8pt;">VENDEDOR</th>
          <th style="padding: 4px; font-size: 8pt; text-align: end;">CRÉDITO</th>
          <th style="padding: 4px; font-size: 8pt; text-align: end;">USD</th>
          <th style="padding: 4px; font-size: 8pt; text-align: end;">COP</th>
          <th style="padding: 4px; font-size: 8pt; text-align: end;">Bs.</th>
          <th style="padding: 4px; font-size: 8pt; text-align: end;">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        <template
          v-for="(cash, index) in props.filteredCashClosings"
          :key="cash.id"
        >
          <tr class="total-row">
            <td style="padding: 6px; font-size: 8pt; font-weight: bold;">
              {{ index + 1 }}. {{ (cash.seller?.username || 'Sin Nombre').toUpperCase() }} (ID: {{ cash.id }})
            </td>
            <td style="padding: 6px; font-size: 8pt; text-align: end; color: #d32f2f;">
              {{ formatCurrency(cash.usd_credit, 'USD') }}
            </td>
            <td style="padding: 6px; font-size: 8pt; text-align: end;">
              {{ formatCurrency(cash.usd_delivered, 'USD') }}
            </td>
            <td style="padding: 6px; font-size: 8pt; text-align: end; color: #2e7d32;">
              {{ formatCurrency(parseFloat(cash.cop_delivered || 0) + parseFloat(cash.cop_transfer || 0), 'COP') }}
            </td>
            <td style="padding: 6px; font-size: 8pt; text-align: end; color: #ed6c02;">
              {{ formatCurrency(cash.total_bs, 'BS') }}
            </td>
            <td style="padding: 6px; font-size: 8pt; text-align: end; font-weight: bold; background-color: #f1f3f5;">
              {{ formatCurrency(cash.total_sales, 'USD') }}
            </td>
          </tr>
          <tr>
            <td
              colspan="6"
              style="padding: 4px; border-block-end: 1px solid #dee2e6; color: #7f8c8d; font-size: 7.5pt; background-color: #fafafa; line-height: 1.2;"
            >
              <strong>OBSERVACIONES / DESCUADRES:</strong> 
              <span v-if="cash.blind_note || (cash.blind_mismatches && cash.blind_mismatches.length > 0)" style="color: #d32f2f; font-weight: bold;">
                {{ cash.blind_note || 'Presenta descuadre en: ' + (Array.isArray(cash.blind_mismatches) ? cash.blind_mismatches.join(', ') : cash.blind_mismatches) }}
              </span>
              <span v-else style="color: #2e7d32; font-weight: bold;">
                Caja cuadrada sin observaciones.
              </span>
            </td>
          </tr>
        </template>

        <!-- TOTAL GENERAL CONSOLIDADO -->
        <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
          <td style="padding: 8px; font-size: 9pt;">
            TOTAL GENERAL CONSOLIDADO
          </td>
          <td style="padding: 8px; font-size: 9pt; text-align: end;">
            {{ formatCurrency(props.totalCreditsUsdGlobal, 'USD') }}
          </td>
          <td style="padding: 8px; font-size: 9pt; text-align: end;">
            {{ formatCurrency(props.totalUsdGlobal, 'USD') }}
          </td>
          <td style="padding: 8px; font-size: 9pt; text-align: end;">
            {{ formatCurrency(props.totalCopGlobal, 'COP') }}
          </td>
          <td style="padding: 8px; font-size: 9pt; text-align: end;">
            {{ formatCurrency(props.totalBsGlobal, 'BS') }}
          </td>
          <td style="padding: 8px; font-size: 9pt; text-align: end; background-color: #1a252f;">
            {{ formatCurrency(props.totalSalesGlobal, 'USD') }}
          </td>
        </tr>
      </tbody>
    </table>

    <!-- DESGLOSE CONSOLIDADO POR MONEDAS -->
    <div class="section-header" style="margin-top: 20px; background-color: #1a252f;">
      DESGLOSE CONSOLIDADO POR MONEDAS Y MÉTODOS
    </div>

    <div style="display: flex; gap: 15px; margin-top: 10px;">
      <!-- Tabla USD -->
      <div style="flex: 1;">
        <table class="data-table" style="width: 100%;">
          <thead>
            <tr>
              <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #34495e !important;">DÓLARES (USD)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalCashUsdGlobal, 'USD') }}</td>
            </tr>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Transferencia / PayPal / Binance</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalTransferUsdGlobal, 'USD') }}</td>
            </tr>
            <tr style="background-color: #f1f3f5; font-weight: bold;">
              <td style="font-size: 7.5pt; padding: 4px;">Total Entregado</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(props.totalUsdGlobal, 'USD') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tabla COP -->
      <div style="flex: 1;">
        <table class="data-table" style="width: 100%;">
          <thead>
            <tr>
              <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #27ae60 !important;">PESOS (COP)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalCashCopGlobal, 'COP') }}</td>
            </tr>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Transferencia Bancaria</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalTransferCopGlobal, 'COP') }}</td>
            </tr>
            <tr style="background-color: #e8f5e9; font-weight: bold; color: #2e7d32;">
              <td style="font-size: 7.5pt; padding: 4px;">Total Entregado</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(props.totalCopGlobal, 'COP') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tabla BS -->
      <div style="flex: 1;">
        <table class="data-table" style="width: 100%;">
          <thead>
            <tr>
              <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #d35400 !important;">BOLÍVARES (BS)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalCashBsGlobal, 'BS') }}</td>
            </tr>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Puntos POS (Debito / Credito)</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalPosBsGlobal, 'BS') }}</td>
            </tr>
            <tr>
              <td style="font-size: 7.5pt; padding: 4px;">Pago Móvil / Transferencia</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(props.totalTransferBsGlobal, 'BS') }}</td>
            </tr>
            <tr style="background-color: #fff3e0; font-weight: bold; color: #e65100;">
              <td style="font-size: 7.5pt; padding: 4px;">Total Reportado</td>
              <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(props.totalBsGlobal, 'BS') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="signature-section" style="margin-top: 35px;">
      <div class="signature-box">
        <div style=" color: #000;font-size: 10pt; font-weight: bold;">
          FIRMA SUPERVISOR
        </div>
        <small style=" color: #666;font-size: 8pt;">CONTROL DE TURNO / VERIFICACIÓN</small>
      </div>
    </div>

    <div class="footer-note" style="margin-top: 20px;">
      ESTE DOCUMENTO ES UN INSTRUMENTO DE CONTROL FINANCIERO INSTITUCIONAL. LA HORA DE CIERRE REGISTRADA ES AUDITABLE.
    </div>
  </div>
</template>
