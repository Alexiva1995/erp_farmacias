<script setup>
import { computed } from "vue";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  paymentHistory: { type: Array, required: true },
  payrollEmployee: { type: Object, required: true },
  distribution: { type: Object, default: null },
  paymentForm: { type: Object, required: true },
  savingPackage: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false },
  mobile: { type: Boolean, default: false },
});

const emit = defineEmits(["save-package"]);

const formatCurrency = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString("es-VE", { style: "currency", currency: "USD" }) : "—";
};

// Agrupar historial por mes (Mes/Año, 1ra quincena, 2da quincena y total USD)
const monthlyPaymentHistory = computed(() => {
  if (!props.paymentHistory || !props.paymentHistory.length) return [];

  const groups = {};

  props.paymentHistory.forEach((item) => {
    const key = `${item.year}-${String(item.month).padStart(2, '0')}`;
    if (!groups[key]) {
      groups[key] = {
        key,
        year: item.year,
        month: item.month,
        fecha: item.fecha,
        q1: 0,
        q2: 0,
        hasQ1: false,
        hasQ2: false,
        total: 0,
      };
    }

    const amount = Number(item.total_pagado_usd) || 0;
    if (item.fortnight === 1) {
      groups[key].q1 += amount;
      groups[key].hasQ1 = true;
    } else {
      groups[key].q2 += amount;
      groups[key].hasQ2 = true;
    }
    groups[key].total += amount;
  });

  return Object.values(groups).sort((a, b) => b.key.localeCompare(a.key));
});
</script>

<template>
  <div class="employee-payroll-tab">
    <!-- Bloque Cohesionado: Paquete Acordado y Neto Estimado -->
    <VCard class="rounded-lg border shadow-sm mb-6" variant="flat">
      <VCardText class="pa-6">
        <VRow align="stretch" class="g-4">
          <!-- Columna Izquierda: Configuración del Monto Mensual -->
          <VCol cols="12" md="7" class="d-flex flex-column justify-center border-md-e pe-md-6">
            <div class="d-flex align-center gap-4 mb-3">
              <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-wallet" size="26" color="primary" />
              </VAvatar>
              <div>
                <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-0 leading-tight">
                  Paquete Mensual Acordado
                </h3>
                <span class="text-caption text-medium-emphasis">
                  Monto base pactado en divisas para el cálculo salarial
                </span>
              </div>
            </div>

            <div class="mt-2">
              <label class="text-caption font-weight-bold text-high-emphasis d-block mb-1.5">
                Monto Mensual (USD)
              </label>
              <div class="d-flex align-center gap-2" style="max-width: 360px;">
                <VTextField
                  v-model.number="paymentForm.total_package_usd"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  density="compact"
                  variant="outlined"
                  placeholder="0.00"
                  hide-details
                  class="font-weight-bold"
                  :disabled="!isAdmin || savingPackage"
                />
                <VBtn
                  v-if="isAdmin"
                  color="primary"
                  variant="flat"
                  size="default"
                  height="40"
                  class="font-weight-bold text-none px-5"
                  prepend-icon="tabler-device-floppy"
                  :loading="savingPackage"
                  @click="emit('save-package')"
                >
                  Guardar
                </VBtn>
              </div>
            </div>
          </VCol>

          <!-- Columna Derecha: Resumen de Neto Estimado -->
          <VCol cols="12" md="5" class="d-flex align-center justify-center ps-md-6 mt-4 mt-md-0">
            <div class="pa-4 rounded-lg bg-surface border d-flex align-center gap-4 w-100 h-100">
              <VAvatar color="success" variant="tonal" size="52" class="rounded-lg">
                <VIcon icon="tabler-currency-dollar" size="28" />
              </VAvatar>
              <div>
                <span class="text-caption font-weight-bold text-medium-emphasis d-block">
                  Neto Estimado a Cobrar
                </span>
                <span class="text-h4 font-weight-black text-success tabular-nums leading-tight">
                  {{ distribution ? formatCurrency(distribution.total_a_cobrar) : formatCurrency(paymentForm.total_package_usd) }}
                </span>
                <span class="text-super-xs text-disabled d-block mt-0.5">
                  Sujeto a deducciones y asignaciones vigentes
                </span>
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Desglose de Conceptos en Tabla / Listado Limpio -->
        <template v-if="distribution">
          <VDivider class="my-6" />

          <div>
            <h3 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-3 d-flex align-center gap-1.5">
              <VIcon icon="tabler-file-invoice" size="18" class="text-primary" /> Detalle de Conceptos de Cobro
            </h3>

            <VRow dense>
              <VCol
                v-for="c in distribution.concepts"
                :key="c.name"
                cols="12"
                sm="6"
                md="3"
              >
                <div class="pa-3 rounded-lg border bg-surface d-flex flex-column gap-1">
                  <span class="text-super-xs font-weight-bold text-medium-emphasis">{{ c.name }}</span>
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis tabular-nums">{{ formatCurrency(c.amount) }}</span>
                </div>
              </VCol>
            </VRow>
          </div>
        </template>
      </VCardText>
    </VCard>

    <!-- Historial de Pagos Procesados -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden" variant="flat">
      <div class="pa-4 bg-light border-b d-flex align-center justify-space-between">
        <span class="font-weight-bold text-caption text-primary d-flex align-center gap-1.5">
          <VIcon icon="tabler-history" size="18" /> Historial de Pagos Procesados
        </span>
      </div>
      <VDataTable
        :items="monthlyPaymentHistory"
        :headers="[
          { title: 'Mes', key: 'fecha' },
          { title: '1ra Quincena', key: 'q1', align: 'end' },
          { title: '2da Quincena', key: 'q2', align: 'end' },
          { title: 'Total (USD)', key: 'total', align: 'end' }
        ]"
        class="premium-table"
        hide-default-footer
      >
        <template #no-data>
          <AppEmptyState
            title="Sin pagos registrados"
            message="El historial aparecerá aquí una vez procesada y emitida la primera nómina para este empleado."
            icon="tabler-receipt-off"
          />
        </template>

        <template #item.fecha="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg font-weight-black text-super-xs">
              {{ item.month }}
            </VAvatar>
            <span class="text-xs font-weight-semibold text-high-emphasis text-capitalize">
              {{ new Date(item.year, item.month - 1, 1).toLocaleString('es-VE', { month: 'long', year: 'numeric' }) }}
            </span>
          </div>
        </template>

        <template #item.q1="{ item }">
          <span class="text-xs font-weight-bold tabular-nums" :class="item.hasQ1 ? 'text-high-emphasis' : 'text-disabled'">
            {{ item.hasQ1 ? formatCurrency(item.q1) : '—' }}
          </span>
        </template>

        <template #item.q2="{ item }">
          <span class="text-xs font-weight-bold tabular-nums" :class="item.hasQ2 ? 'text-high-emphasis' : 'text-disabled'">
            {{ item.hasQ2 ? formatCurrency(item.q2) : '—' }}
          </span>
        </template>

        <template #item.total="{ item }">
          <span class="text-xs font-weight-black text-primary tabular-nums">
            {{ formatCurrency(item.total) }}
          </span>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}
.salary-package-input {
  border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
}
.border-dashed-b {
  border-block-end: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
