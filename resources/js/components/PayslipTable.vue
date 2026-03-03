<script setup>
const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: false },
  { title: "Fecha", key: "payslip_date", sortable: false },
  { title: "Total", key: "total", sortable: false },
  { title: "Total Pagado", key: "payed", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits([
  "update:options",
  "finalize-payslip",
  "download-excel",
  "download-pdf",
]);
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      @update:options="(options) => emit('update:options', options)"
    >

      <template #item.total="{ item }">
        <span
          >{{
            Intl.NumberFormat("es-ES", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }).format(item.total)
          }}
          $</span
        >
      </template>
      <template #item.payed="{ item }">
        <span v-if="item.status === 1" class="text-success font-weight-bold">
          {{
            Intl.NumberFormat("es-ES", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }).format(item.payed)
          }}
          $</span
        >
        <span v-else>-</span>
      </template>
      <template #item.currency="{ item }">
        <span v-if="item.status === 1">{{ item.currency }}</span>
        <span v-else>-</span>
      </template>
      <template #item.actions="{ item }">
        <!-- Ver detalle (Ojo Azul) -->
        <VTooltip text="Nómina Legal (PDF)" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              color="info"
              @click="emit('download-pdf', item.id, 'legal')"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>

        <!-- Ver detalle Completo (Ojo Naranja/Warning) -->
        <VTooltip
          v-if="item.status === 1"
          text="Nómina Completa (PDF)"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              color="warning"
              @click="emit('download-pdf', item.id, 'full')"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>

        <!-- Finalizar -->
        <VTooltip
          v-if="item.status === 0"
          text="Finalizar nómina"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('finalize-payslip', item)">
              <VIcon icon="tabler-file-check" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
