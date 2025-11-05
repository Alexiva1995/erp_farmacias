<script setup>
import { computed } from 'vue';

const props = defineProps({
  categoriesOffer: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOffer: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Categoría", key: "category.name", sortable: true, width: "30%" },
  { title: "% Descuento", key: "discount_percentage", sortable: true },
  { title: "Fecha Inicio", key: "start_date", sortable: true },
  { title: "Fecha Final", key: "end_date", sortable: true },
  { title: "Estado", key: "is_active", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formattedCategories = computed(() => {
  return props.categoriesOffer.map(offer => ({
    ...offer,
    'category.name': offer.category?.name || 'N/A'
  }));
});

const getStatusBadge = (isActive) => {
  return isActive ? 'success' : 'error';
};

const getStatusText = (isActive) => {
  return isActive ? 'Activa' : 'Inactiva';
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="categoriesOffer"
      :items-length="props.totalOffer"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => $emit('update:options', options)"
    >
      <template #item.category.name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1">{{ item.category?.name || 'N/A' }}</span>
          <span class="text-caption text-disabled">ID: {{ item.category?.id || 'N/A' }}</span>
        </div>
      </template>
      <template #item.discount_percentage="{ item }">
        {{ item.discount_percentage }}%
      </template>
      <template #item.start_date="{ item }">
        {{ new Date(item.start_date).toLocaleDateString() }}
      </template>
      <template #item.end_date="{ item }">
        {{ new Date(item.end_date).toLocaleDateString() }}
      </template>
      <template #item.is_active="{ item }">
        <VChip :color="getStatusBadge(item.is_active)" size="small">
          {{ getStatusText(item.is_active) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn @click="$emit('edit-offer', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn @click="$emit('delete-offer', item.id)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
