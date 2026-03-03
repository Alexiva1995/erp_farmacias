<script setup>
const props = defineProps({
  sellerCash:       { type: Array,  required: true },
  loading:          { type: Boolean, default: false },
  totalSellerCash:  { type: Number, required: true },
  itemsPerPage:     { type: Number, required: true },
  page:             { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'print-cash', 'download-cash']);

const headers = [
  { title: "Vendedor",   key: "seller.username",  sortable: true },
  { title: "USD",        key: "total_usd",         sortable: true, align: "end" },
  { title: "COP",        key: "total_cop",         sortable: true, align: "end" },
  { title: "Bs.",        key: "total_bs",          sortable: true, align: "end" },
  { title: "Total USD",  key: "total_sales",       sortable: true, align: "end" },
  { title: "Estado",     key: "status",            sortable: true, align: "center" },
  { title: "",           key: "actions",           sortable: false, align: "center", width: "100px" },
];

const statusMap = {
  closed: { label: "Cerrada", color: "success", icon: "tabler-lock" },
  open:   { label: "Abierta", color: "warning", icon: "tabler-lock-open" },
};

const fmtUsd = (val) => new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val ?? 0) + " USD";
const fmtCop = (val) => Math.round(val ?? 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
const fmtBs  = (val) => new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val ?? 0) + " Bs.";
</script>

<template>
  <VCard elevation="0" class="seller-table rounded-xl border">
    <VCardItem class="pa-4 pb-2">
      <template #prepend>
        <VAvatar color="primary" variant="tonal" size="38" rounded>
          <VIcon icon="tabler-users" size="20" />
        </VAvatar>
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">Cierres por Vendedor</VCardTitle>
      <VCardSubtitle class="text-caption">Resumen de cierres individuales del período</VCardSubtitle>
    </VCardItem>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.sellerCash"
      :items-length="props.totalSellerCash"
      :loading="props.loading"
      no-data-text="No hay cierres registrados para el período seleccionado"
      @update:options="(opt) => emit('update:options', opt)"
    >
      <!-- Vendedor -->
      <template #item.seller.username="{ item }">
        <div class="d-flex align-center gap-2 py-1">
          <VAvatar size="30" color="primary" variant="tonal" class="font-weight-bold text-caption">
            {{ (item.seller?.username ?? '?').charAt(0).toUpperCase() }}
          </VAvatar>
          <span class="font-weight-medium">{{ item.seller?.username ?? '—' }}</span>
        </div>
      </template>

      <!-- Monedas -->
      <template #item.total_usd="{ item }">
        <span class="font-weight-medium text-primary">{{ fmtUsd(item.total_usd) }}</span>
      </template>
      <template #item.total_cop="{ item }">
        <span class="font-weight-medium text-success">{{ fmtCop(item.total_cop) }}</span>
      </template>
      <template #item.total_bs="{ item }">
        <span class="font-weight-medium text-warning">{{ fmtBs(item.total_bs) }}</span>
      </template>
      <template #item.total_sales="{ item }">
        <span class="font-weight-black">{{ fmtUsd(item.total_sales) }}</span>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip
          :color="statusMap[item.status]?.color ?? 'default'"
          :prepend-icon="statusMap[item.status]?.icon"
          size="small"
          label
          class="font-weight-medium"
        >
          {{ statusMap[item.status]?.label ?? item.status }}
        </VChip>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-1">
          <VTooltip text="Ver / Imprimir" location="top">
            <template #activator="{ props: tip }">
              <VBtn v-bind="tip" icon="tabler-printer" size="small" variant="tonal" color="info" @click="emit('print-cash', item)" />
            </template>
          </VTooltip>
          <VTooltip text="Descargar PDF" location="top">
            <template #activator="{ props: tip }">
              <VBtn v-bind="tip" icon="tabler-download" size="small" variant="tonal" color="primary" @click="emit('download-cash', item)" />
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.seller-table {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
}
</style>
