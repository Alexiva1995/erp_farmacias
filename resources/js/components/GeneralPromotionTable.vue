<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  promotions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOffer: { type: Number, default: 0 },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  categories: { type: Array, default: () => [] }
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);
const { mobile } = useDisplay();

const headers = [
  {
    title: "ID",
    key: "id",
    sortable: true,
    align: "center",
    width: "70px",
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
  },
  { title: "Tipo de Oferta", key: "type",        sortable: true, width: "30%" },
  { title: "Beneficio",      key: "fixed_price", sortable: true, align: "center", width: "130px" },
  { title: "Categorías",     key: "categories",  sortable: false, width: "40%" },
  { title: "Estado",         key: "is_active",   sortable: true, align: "center", width: "100px" },
  { title: "Acciones",       key: "actions",     sortable: false, align: "center", width: "110px" },
];

const getPromoTypeName = (type) => {
  switch (type) {
    case "general": return "Oferta General (%)";
    case "2x1": return "Oferta 2X1";
    case "3x2": return "Oferta 3X2";
    case "50_second": return "50% en el segundo";
    case "fixed_price": return "Precio Fijo";
    default: return type;
  }
};

const getCategoryNames = (categoryIds) => {
  if (!Array.isArray(categoryIds) || categoryIds.length === 0) return "Todas las Categorías";
  return categoryIds
    .map(id => {
      const cat = props.categories.find(c => c.id === id);
      return cat ? cat.name : `ID: ${id}`;
    })
    .join(", ");
};
</script>

<template>
  <div class="general-offers-container">
    <!-- Vista de Escritorio (Tabla Premium) -->
    <VCard class="d-none d-md-block rounded-lg border shadow-sm overflow-hidden">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.promotions"
        :items-length="props.totalOffer"
        :loading="props.loading"
        items-per-page-text="Filas por página:"
        page-text="{0}-{1} de {2}"
        loading-text="Cargando..."
        no-data-text="No hay datos disponibles"
        class="text-no-wrap"
        density="compact"
        fixed-header
        height="auto"
        @update:options="(options) => $emit('update:options', options)"
      >
        <!-- ID Column -->
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <!-- Type Column -->
        <template #item.type="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-inline-size: 380px;">
              {{ getPromoTypeName(item.type) }}
            </span>
            <span class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold mt-0-5">
              Promoción General
            </span>
          </div>
        </template>

        <!-- Fixed Price / Beneficio Column -->
        <template #item.fixed_price="{ item }">
          <span v-if="item.type === 'general'" class="font-weight-black text-success text-sm">
            {{ parseFloat(item.fixed_price).toFixed(1) }}% OFF
          </span>
          <span v-else-if="item.type === 'fixed_price'" class="font-weight-black text-success text-sm">
            ${{ parseFloat(item.fixed_price).toFixed(2) }}
          </span>
          <span v-else class="text-super-xs font-weight-bold text-medium-emphasis uppercase">
            {{ item.type === '2x1' ? 'Paga 1 Lleva 2' : item.type === '3x2' ? 'Paga 2 Lleva 3' : '50% en 2do' }}
          </span>
        </template>

        <!-- Categories Column -->
        <template #item.categories="{ item }">
          <div class="text-xs truncate uppercase font-weight-bold text-high-emphasis" style="max-inline-size: 350px" :title="getCategoryNames(item.categories)">
            {{ getCategoryNames(item.categories) }}
          </div>
        </template>

        <!-- State Column -->
        <template #item.is_active="{ item }">
          <VChip
            size="x-small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
            class="font-weight-black px-2 rounded"
          >
            {{ item.is_active ? 'ACTIVA' : 'INACTIVA' }}
          </VChip>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn
              @click="$emit('edit-offer', item)"
              color="warning"
              size="small"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Editar Promoción</VTooltip>
            </IconBtn>
            <IconBtn
              @click="$emit('delete-offer', item.id)"
              color="error"
              size="small"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent">Eliminar Promoción</VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Premium Cards) -->
    <div class="d-block d-md-none">
      <div v-if="props.loading" class="d-flex justify-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div
        v-else-if="props.promotions.length === 0"
        class="text-center py-8 bg-white rounded-lg border border-dashed"
      >
        <VIcon icon="tabler-tag-off" size="48" color="disabled" class="mb-2" />
        <div class="text-sm font-weight-black text-disabled uppercase">
          No hay promociones generales
        </div>
      </div>

      <div
        v-for="item in props.promotions"
        :key="item.id"
        class="premium-card mb-4 bg-white rounded-lg elevation-2 overflow-hidden border-0"
      >
        <div class="status-strip" :class="item.is_active ? 'bg-success' : 'bg-secondary'"></div>

        <div class="pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="d-flex align-center gap-1">
              <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
              <span class="text-disabled mx-1">|</span>
              <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase mb-0">
                {{ getPromoTypeName(item.type) }}
              </h3>
            </div>

            <div class="d-flex gap-1">
              <IconBtn
                size="small"
                color="warning"
                @click="$emit('edit-offer', item)"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent">Editar</VTooltip>
              </IconBtn>
              <IconBtn
                size="small"
                color="error"
                @click="$emit('delete-offer', item.id)"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent">Eliminar</VTooltip>
              </IconBtn>
            </div>
          </div>

          <div class="bg-light pa-3 rounded-lg mb-3 border border-dashed">
            <div class="mb-2">
              <span class="text-super-xs font-weight-black text-disabled uppercase block mb-1">Categorías Aplicables</span>
              <div class="text-xs font-weight-bold uppercase leading-tight text-high-emphasis">
                {{ getCategoryNames(item.categories) }}
              </div>
            </div>

            <div v-if="item.type === 'fixed_price'" class="d-flex justify-space-between align-center mt-2 pt-2 border-t">
              <span class="text-xs font-weight-black text-disabled uppercase">Precio Fijo</span>
              <span class="text-sm font-weight-black text-success">${{ parseFloat(item.fixed_price).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gap-1 {
  gap: 4px !important;
}

.gap-2 {
  gap: 8px !important;
}
</style>
