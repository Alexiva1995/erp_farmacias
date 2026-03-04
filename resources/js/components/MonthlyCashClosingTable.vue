<script setup>
const props = defineProps({
  monthlyCash:       { type: Array,  required: true },
  loading:           { type: Boolean, default: false },
  totalMonthlyCash:  { type: Number, required: true },
  itemsPerPage:      { type: Number, required: true },
  page:              { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'view-cash']);

const headers = [
  { title: "Período",         key: "period",              sortable: true },
  { title: "USD",             key: "amount_usd",           sortable: true, align: "end" },
  { title: "COP",             key: "amount_cop",           sortable: true, align: "end" },
  { title: "Bs.",             key: "amount_bs",            sortable: true, align: "end" },
  { title: "Total (≈ USD)",   key: "total_usd_equivalent", sortable: false, align: "end" },
  { title: "Días",            key: "days_closed",          sortable: false, align: "center" },
  { title: "Promedio/Día",    key: "daily_average",        sortable: true, align: "end" },
  { title: "",                key: "actions",              sortable: false, align: "center", width: "90px" },
];
</script>

<template>
  <VCard elevation="0" class="monthly-table rounded-xl border">
    <VCardItem class="pa-4 pb-2">
      <template #prepend>
        <VAvatar color="success" variant="tonal" size="38" rounded>
          <VIcon icon="tabler-chart-area" size="20" />
        </VAvatar>
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">Cierres Mensuales</VCardTitle>
      <VCardSubtitle class="text-caption">Totales convertidos a USD equivalente para comparación correcta</VCardSubtitle>
    </VCardItem>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.monthlyCash"
      :items-length="props.totalMonthlyCash"
      :loading="props.loading"
      no-data-text="No hay cierres mensuales registrados"
      @update:options="(opt) => emit('update:options', opt)"
    >
      <template #item.period="{ item }">
        <span class="font-weight-bold text-high-emphasis">{{ item.period }}</span>
      </template>

      <template #item.amount_usd="{ item }">
        <span class="text-primary font-weight-medium">{{ item.amount_usd }} USD</span>
      </template>
      <template #item.amount_cop="{ item }">
        <span class="text-success font-weight-medium">{{ item.amount_cop }} COP</span>
      </template>
      <template #item.amount_bs="{ item }">
        <span class="text-warning font-weight-medium">{{ item.amount_bs }} Bs.</span>
      </template>

      <!-- Total unificado correcto -->
      <template #item.total_usd_equivalent="{ item }">
        <VChip color="primary" size="small" label class="font-weight-bold">
          {{ item.total_usd_equivalent ?? '—' }} USD
        </VChip>
      </template>

      <template #item.days_closed="{ item }">
        <VChip size="small" label variant="tonal">{{ item.days_closed }} días</VChip>
      </template>

      <template #item.daily_average="{ item }">
        <span class="font-weight-medium">{{ item.daily_average }} USD</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-1 justify-center">
          <VTooltip text="Ver detalle mensual" location="top">
            <template #activator="{ props: tip }">
              <VBtn v-bind="tip" icon="tabler-eye" size="small" variant="tonal" color="info" @click="emit('view-cash', item)" />
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.monthly-table {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
}
</style>
