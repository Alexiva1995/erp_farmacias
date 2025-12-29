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
  { title: "Estado", key: "status", sortable: false },
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
      <template #item.status="{ item }">
        <VBadge
          :color="item.status ? 'success' : 'error'"
          :content="item.status ? 'Finalizado' : 'Pendiente'"
          inline=""
        />
      </template>
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
        <VTooltip text="Ver nómina" location="top">
          <template #activator="{ props }">
            <VBtn
              :href="'/finances/payslips/' + item.id"
              v-bind="props"
              icon="tabler-eye"
              variant="text"
              color="info"
            >
            </VBtn>
          </template>
        </VTooltip>
        <VTooltip
          v-if="item.status === 1"
          text="Descargar pdf legal"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="emit('download-pdf', item.id, 'legal')"
            >
              <VIcon icon="tabler-pdf" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          v-if="item.status === 1"
          text="Descargar pdf completo"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="emit('download-pdf', item.id, 'full')"
            >
              <VIcon icon="tabler-pdf" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          v-if="item.status === 1"
          text="Descargar excel"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="emit('download-excel', item.id, 'excel')"
            >
              <VIcon icon="tabler-file" />
            </IconBtn>
          </template>
        </VTooltip>
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
