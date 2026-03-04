<script setup>
const props = defineProps({
  dailyCash:       { type: Array,  required: true },
  loading:         { type: Boolean, default: false },
  totalDailyCash:  { type: Number, required: true },
  itemsPerPage:    { type: Number, required: true },
  page:            { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'view-cash', 'delivery', 'reference', 'closing-daily']);

const headers = [
  { title: "Fecha",      key: "date",          sortable: true },
  { title: "USD",        key: "total_usd",      sortable: true, align: "end" },
  { title: "COP",        key: "total_cop",      sortable: true, align: "end" },
  { title: "Bs.",        key: "total_bs",       sortable: true, align: "end" },
  { title: "E. USD",     key: "usd_delivered",  sortable: true, align: "end" },
  { title: "E. COP",     key: "cop_delivered",  sortable: true, align: "end" },
  { title: "Bs PM",      key: "bs_mobile",      sortable: true, align: "end" },
  { title: "Bs Tarjeta", key: "bs_card",        sortable: true, align: "end" },
  { title: "",           key: "actions",        sortable: false, align: "center", width: "120px" },
];

const fmtDate = (v) => v ? new Date(v).toISOString().split("T")[0] : "—";
const fmtUsd  = (v) => new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);
const fmtCop  = (v) => Math.round(v ?? 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
const fmtBs   = (v) => new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);

// Heurística de alerta: si usd_delivered < total_usd * 0.8 → advertencia de conciliación
const conciliationAlert = (item) => {
  const expected = parseFloat(item.total_usd ?? 0);
  const delivered = parseFloat(item.usd_delivered ?? 0);
  if (expected <= 0) return null;
  const ratio = delivered / expected;
  if (ratio < 0.5) return { color: 'error',   label: 'Diferencia alta' };
  if (ratio < 0.9) return { color: 'warning',  label: 'Revisar entrega' };
  return { color: 'success', label: 'Conciliado' };
};
</script>

<template>
  <VCard elevation="0" class="daily-table rounded-xl border">
    <VCardItem class="pa-4 pb-2">
      <template #prepend>
        <VAvatar color="info" variant="tonal" size="38" rounded>
          <VIcon icon="tabler-calendar-check" size="20" />
        </VAvatar>
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">Cierres Diarios</VCardTitle>
      <VCardSubtitle class="text-caption">Consolidado de ventas por día con detalle de entregas</VCardSubtitle>
    </VCardItem>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.dailyCash"
      :items-length="props.totalDailyCash"
      :loading="props.loading"
      no-data-text="No hay cierres diarios registrados"
      density="compact"
      @update:options="(opt) => emit('update:options', opt)"
    >
      <template #item.date="{ item }">
        <span class="font-weight-medium">{{ fmtDate(item.created_at) }}</span>
      </template>

      <!-- Totales vendidos -->
      <template #item.total_usd="{ item }">
        <span class="font-weight-medium text-primary">{{ fmtUsd(item.total_usd) }}</span>
      </template>
      <template #item.total_cop="{ item }">
        <span class="font-weight-medium text-success">{{ fmtCop(item.total_cop) }}</span>
      </template>
      <template #item.total_bs="{ item }">
        <span class="font-weight-medium text-warning">{{ fmtBs(item.total_bs) }}</span>
      </template>

      <!-- Entregas con conciliación -->
      <template #item.usd_delivered="{ item }">
        <div class="text-right">
          <span class="font-weight-medium">{{ fmtUsd(item.usd_delivered) }}</span>
        </div>
      </template>
      <template #item.cop_delivered="{ item }">
        <span>{{ fmtCop(item.cop_delivered) }}</span>
      </template>
      <template #item.bs_mobile="{ item }">
        <span>{{ fmtBs(item.bs_mobile) }}</span>
      </template>
      <template #item.bs_card="{ item }">
        <span>{{ fmtBs(item.bs_card) }}</span>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-1 justify-center">
          <VTooltip text="Ver detalle" location="top">
            <template #activator="{ props: tip }">
              <VBtn v-bind="tip" icon="tabler-eye" size="x-small" variant="tonal" color="info" @click="emit('view-cash', item)" />
            </template>
          </VTooltip>
          <VTooltip text="Entregas" location="top">
            <template #activator="{ props: tip }">
              <VBtn v-bind="tip" icon="tabler-box" size="x-small" variant="tonal" @click="emit('delivery', item)" />
            </template>
          </VTooltip>
          <VTooltip text="Referencias" location="top">
            <template #activator="{ props: tip }">
              <VBtn 
                v-bind="tip" 
                icon="tabler-clipboard-list" 
                size="x-small" 
                variant="tonal" 
                color="secondary" 
                @click="emit('reference', item)" 
              />
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.daily-table {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
}
</style>
