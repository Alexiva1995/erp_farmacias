<script setup>
const props = defineProps({
  packs: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPacks: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-pack", "delete-pack", "view-pack"]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Nombre", key: "name", sortable: true, width: "25%" },
  { title: "Productos", key: "products_count", sortable: true, width: "120px" },
  { title: "Precio Total", key: "total_price", sortable: true, width: "120px" },
  { title: "Cant. Máx.", key: "max_quantity", sortable: true, width: "120px" },
  { title: "Fecha Límite", key: "max_sale_date", sortable: true, width: "140px" },
  { title: "Estado", key: "is_active", sortable: true, width: "100px" },
  { title: "Acciones", key: "actions", sortable: false, align: "center", width: "120px" },
];

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-ES');
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0);
};

const handleEdit = (pack) => {
  emit('edit-pack', pack);
};

const handleDelete = (pack) => {
  emit('delete-pack', pack);
};

const handleView = (pack) => {
  emit('view-pack', pack);
};

</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.packs"
      :items-length="props.totalPacks"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.products_count="{ item }">
        <VChip variant="outlined" color="primary" size="small">
          {{ Object.keys(item.pack_config || {}).length }}
        </VChip>
      </template>

      <template #item.total_price="{ item }">
        <span class="font-weight-bold">
          {{ formatCurrency(item.total_price) }}
        </span>
      </template>

      <template #item.max_quantity="{ item }">
        <span v-if="item.max_quantity" class="text-caption">
          {{ item.max_quantity }}
        </span>
        <span v-else class="text-disabled text-caption">Ilimitado</span>
      </template>

      <template #item.max_sale_date="{ item }">
        <span class="text-caption">
          {{ formatDate(item.max_sale_date) }}
        </span>
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="item.is_active ? 'success' : 'error'"
          variant="flat"
          size="small"
        >
          {{ item.is_active ? 'Activo' : 'Inactivo' }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          color="info"
          @click="handleView(item)"
        >
          <VIcon>tabler-eye</VIcon>
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="primary"
          @click="handleEdit(item)"
        >
          <VIcon>tabler-edit</VIcon>
        </VBtn>
        
        <VBtn
          icon
          variant="text"
          size="small"
          color="error"
          @click="handleDelete(item)"
        >
          <VIcon>tabler-trash</VIcon>
        </VBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
