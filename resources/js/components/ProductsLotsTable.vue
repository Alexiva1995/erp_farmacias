<script setup>
import { formatDate } from "@/utils/formatters";

const props = defineProps({
  products: { type: Array, required: true },
  totalProducts: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "adjust-lots"]);

const headers = [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  { title: "Stock Total", key: "stock", sortable: true, align: "center" },
  { 
    title: "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { 
    title: "Lotes", 
    key: "lots_count", 
    sortable: false, 
    align: "center",
    cellClass: "d-none d-lg-table-cell",
    headerClass: "d-none d-lg-table-cell"
  },
  { 
    title: "Próximo Vencimiento", 
    key: "next_expiration", 
    sortable: false,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getNextExpiration = (lots) => {
  if (!lots || lots.length === 0) return null;
  const expirations = lots
    .map(l => l.expiration_date)
    .filter(d => d)
    .sort();
  return expirations[0] || null;
};
</script>

<template>
  <VCard title="Inventario por Producto (Gestión de Lotes)">
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProducts"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.photo_url"
          />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
              {{ item.name.toUpperCase() }}
            </span>
            <span class="text-xs text-disabled d-md-none" v-if="item.laboratory?.name">
              {{ item.laboratory.name }}
            </span>   
          </div>
        </div>
      </template>

      <template #item.stock="{ item }">
        <VChip
          :color="item.stock > 0 ? 'success' : 'error'"
          label
          size="small"
          variant="tonal"
          class="font-weight-bold"
        >
          {{ item.stock }}
        </VChip>
      </template>

      <template #item.lots_count="{ item }">
        <span class="font-weight-medium">{{ item.lots?.length || 0 }}</span>
      </template>

      <template #item.next_expiration="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{ formatDate(getNextExpiration(item.lots)) || 'N/A' }}</span>
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-2">
          <IconBtn @click="emit('adjust-lots', item)" color="primary">
            <VIcon icon="tabler-package" />
            <VTooltip activator="parent" location="top">Ajustar Lotes</VTooltip>
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
