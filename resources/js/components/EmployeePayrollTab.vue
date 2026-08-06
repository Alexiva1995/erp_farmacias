<script setup>
import { computed } from "vue";

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
</script>

<template>
  <div class="employee-payroll-tab">
    <div class="d-flex align-center justify-space-between mb-6">
      <h2 :class="mobile ? 'text-h6' : 'text-h5'" class="font-weight-black text-high-emphasis tracking-tight uppercase">
        Nómina e Incentivos
      </h2>
      <VChip color="success" variant="flat" class="font-weight-black px-4">ACTIVO</VChip>
    </div>

    <VCard class="rounded-lg border shadow-sm mb-6">
      <VCardText class="pa-6">
        <VRow align="center">
          <VCol cols="12" md="6">
            <div class="d-flex align-center gap-4">
              <VAvatar color="primary" variant="tonal" size="52" class="rounded-lg d-flex align-center justify-center">
                <VIcon icon="tabler-wallet" size="28" color="primary" />
              </VAvatar>
              <div>
                <span class="text-caption font-weight-bold text-medium-emphasis uppercase d-block">
                  Paquete Mensual Acordado
                </span>
                <div class="d-flex align-center gap-2 mt-1">
                  <span class="text-h4 font-weight-black text-primary">$</span>
                  <VTextField
                    v-model.number="paymentForm.total_package_usd"
                    type="number"
                    step="0.01"
                    min="0"
                    density="compact"
                    variant="outlined"
                    hide-details
                    style="max-width: 160px;"
                    class="font-weight-black"
                  />
                  <VBtn
                    v-if="isAdmin"
                    color="primary"
                    variant="flat"
                    size="small"
                    class="font-weight-bold ms-2"
                    :loading="savingPackage"
                    @click="emit('save-package')"
                  >
                    Guardar
                  </VBtn>
                </div>
              </div>
            </div>
          </VCol>

          <VCol cols="12" md="6" class="d-flex justify-md-end">
            <div class="pa-4 rounded-lg bg-surface border d-flex align-center gap-4" style="min-width: 240px;">
              <VAvatar color="success" variant="tonal" size="44" class="rounded-lg">
                <VIcon icon="tabler-currency-dollar" size="24" />
              </VAvatar>
              <div>
                <span class="text-caption font-weight-bold text-medium-emphasis uppercase d-block">Neto Estimado</span>
                <span class="text-h5 font-weight-black text-success tabular-nums">
                  {{ distribution ? formatCurrency(distribution.total_a_cobrar) : formatCurrency(paymentForm.total_package_usd) }}
                </span>
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Desglose de Conceptos en Tabla / Listado Limpio -->
        <VDivider class="my-6" />

        <div v-if="distribution">
          <h3 class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis mb-4">
            <VIcon icon="tabler-file-invoice" size="18" class="me-1 text-primary" /> Detalle de Conceptos de Cobro
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
                <span class="text-super-xs font-weight-bold text-disabled uppercase">{{ c.name }}</span>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis tabular-nums">{{ formatCurrency(c.amount) }}</span>
              </div>
            </VCol>
          </VRow>
        </div>
      </VCardText>
    </VCard>

    <!-- Historial -->
    <VCard class="rounded-lg border-0 shadow-sm overflow-hidden mt-6">
      <div class="pa-4 bg-light border-b font-weight-black text-super-xs text-primary uppercase letter-spacing-1">
        Historial de Pagos Procesados
      </div>
      <VDataTableServer
        :items="paymentHistory"
        :headers="[
          { title: 'PERIODO', key: 'fecha' },
          { title: 'NETO (USD)', key: 'total_pagado_usd', align: 'end' },
          { title: 'EQUIVALENTE (VES)', key: 'total_pagado_ves', align: 'end' }
        ]"
        class="premium-table"
        hide-default-footer
      >
        <template #item.fecha="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg font-weight-black text-super-xs">
              {{ new Date(item.fecha).getMonth() + 1 }}
            </VAvatar>
            <span class="text-xs font-weight-black uppercase">{{ new Date(item.fecha).toLocaleString('es-VE', { month: 'long', year: 'numeric' }) }}</span>
          </div>
        </template>
        <template #item.total_pagado_usd="{ item }">
          <span class="text-xs font-weight-black text-primary tabular-nums">{{ formatCurrency(item.total_pagado_usd) }}</span>
        </template>
        <template #item.total_pagado_ves="{ item }">
          <span class="text-xs font-weight-bold text-medium-emphasis tabular-nums">{{ item.total_pagado_ves.toLocaleString('es-VE') }} Bs</span>
        </template>
      </VDataTableServer>
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
