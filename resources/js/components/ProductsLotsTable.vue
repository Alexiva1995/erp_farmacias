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
<template>
  <VCard>
    <VCardTitle class="pa-4 d-none d-sm-block">
      Inventario por Producto (Gestión de Lotes)
    </VCardTitle>

    <!-- Desktop Table -->
    <div class="d-none d-sm-block">
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
                {{ item.name.toUpperCase() }}
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
            <VBtn
              icon
              variant="text"
              color="primary"
              @click.stop="emit('adjust-lots', item)"
            >
              <VIcon icon="tabler-package" />
              <VTooltip activator="parent" location="top">Ajustar Lotes</VTooltip>
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-sm-none">
      <div v-if="loading && products.length === 0" class="pa-5 text-center">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div class="pa-2">
        <VCard
          v-for="item in products"
          :key="item.id"
          variant="flat"
          class="lot-list-mobile-card border mb-2 overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0 mt-1"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <span class="text-primary mr-1">#{{ item.id }}</span>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.name }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-primary font-weight-bold">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="d-flex justify-space-between align-center px-1">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock Total</span>
                <span class="text-xs font-weight-black" :class="item.stock > 0 ? 'text-success' : 'text-error'">
                  {{ item.stock || 0 }} <small>UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lotes</span>
                <span class="text-xs font-weight-black text-primary">{{ item.lots?.length || 0 }}</span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Próx. Venc.</span>
                <span class="text-xs font-weight-medium text-warning">{{ formatDate(getNextExpiration(item.lots)) || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares Movil -->
          <div class="d-flex border-t border-opacity-10">
            <VBtn
              block
              color="primary"
              variant="text"
              class="rounded-0"
              height="40"
              prepend-icon="tabler-package"
              @click="emit('adjust-lots', item)"
            >
              AJUSTAR LOTES
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-2">
          <VPagination
            :model-value="page"
            :length="Math.ceil(totalProducts / itemsPerPage)"
            :total-visible="3"
            density="compact"
            size="small"
            @update:model-value="emit('update:options', { page: $event, itemsPerPage })"
          />
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.lot-list-mobile-card {
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.text-super-xs {
  font-size: 0.65rem !important;
}
</style>
