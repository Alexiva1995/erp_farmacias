<template>
  <VCard title="Facturas Cargadas" subtitle="Listado de facturas en estado cargado">
    <template #append>
      <VBtn
        icon
        variant="text"
        size="small"
        @click="$emit('refresh')"
      >
        <VIcon icon="tabler-refresh" />
      </VBtn>
    </template>
    <VCardText>
      <InvoiceTable
        :invoices="invoices"
        :loading="loading"
        :total-invoices="totalInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        actions-mode="approval"
        @update:options="(opt) => $emit('update:options', opt)"
      />
    </VCardText>
  </VCard>
</template>

<script setup>
import InvoiceTable from "@/components/InvoiceTable.vue"

defineProps({
  invoices: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalInvoices: {
    type: Number,
    default: 0,
  },
  itemsPerPage: {
    type: Number,
    default: 5,
  },
  page: {
    type: Number,
    default: 1,
  },
  isAdmin: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['refresh', 'update:options'])
</script>
