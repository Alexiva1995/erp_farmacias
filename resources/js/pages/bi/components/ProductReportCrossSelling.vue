<script setup>
import { computed, ref } from 'vue';
import axios from '@/plugins/axios';

// Componente: Venta Cruzada (Cross-selling / Market Basket) con Afiliación, Co-ocurrencia y Selección de Producto
const props = defineProps({
  crossSelling: { type: Array, default: () => [] },
  page: { type: Number, default: 1 },
  loading: { type: Boolean, default: false },
  selectedProductId: { type: [Number, String, null], default: null },
  selectedProductName: { type: String, default: '' },
});

const emit = defineEmits(['page-change', 'select-product']);

const isSearchOpen = ref(false);
const searchItems = ref([]);
const searchLoading = ref(false);
const selectedSearchModel = ref(null);

const hasMore = () => props.crossSelling.length >= 7;

const getConfidenceColor = (conf) => {
  if (conf >= 60) return 'success';
  if (conf >= 35) return 'primary';
  return 'info';
};

const handleSelectProduct = (id, name) => {
  if (props.selectedProductId === id) {
    // Si ya está seleccionado, limpiar filtro
    emit('select-product', null);
  } else {
    emit('select-product', { id, name });
  }
};

const searchCatalog = async (query) => {
  if (!query || query.length < 1) return;
  searchLoading.value = true;
  const isIdSearch = /^\d+$/.test(query.trim());
  const params = isIdSearch ? { id: query.trim() } : { q: query, itemsPerPage: 10 };
  try {
    const { data } = await axios.get('/products', { params });
    searchItems.value = data.data ?? [];
  } catch {
    searchItems.value = [];
  } finally {
    searchLoading.value = false;
  }
};

const onSearchSelected = (item) => {
  if (!item) return;
  const found = searchItems.value.find((p) => p.id === item || p.id === item?.id);
  if (found) {
    emit('select-product', { id: found.id, name: found.name });
  } else if (typeof item === 'number' || typeof item === 'string') {
    emit('select-product', { id: item, name: `Producto #${item}` });
  }
  selectedSearchModel.value = null;
  isSearchOpen.value = false;
};
</script>

<template>
  <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm d-flex flex-column">
    <VCardTitle class="pa-3 pa-sm-4 border-b d-flex align-center justify-space-between flex-wrap gap-2 bg-surface">
      <div class="d-flex align-center min-width-0">
        <VAvatar size="32" color="primary" variant="tonal" class="me-2 rounded flex-shrink-0">
          <VIcon icon="tabler-arrows-cross" size="18" />
        </VAvatar>
        <div class="min-width-0">
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis text-truncate leading-tight">
            Venta Cruzada (Market Basket)
          </div>
          <div class="text-caption text-medium-emphasis text-truncate">
            {{ selectedProductId ? `Asociaciones frecuentes con: ${selectedProductName || 'ID #' + selectedProductId}` : 'Asociación de productos frecuentes y confianza de compra conjunta' }}
          </div>
        </div>
      </div>

      <div class="d-flex align-center gap-2 flex-shrink-0">
        <!-- Chip de filtro activo -->
        <VChip
          v-if="selectedProductId"
          color="primary"
          size="small"
          variant="flat"
          closable
          class="font-weight-bold"
          @click:close="emit('select-product', null)"
        >
          <VIcon start size="14" icon="tabler-filter" />
          {{ selectedProductName ? (selectedProductName.length > 18 ? selectedProductName.substring(0, 18) + '...' : selectedProductName) : `ID #${selectedProductId}` }}
        </VChip>

        <VChip v-else color="primary" size="x-small" variant="tonal" label class="font-weight-bold">
          Afiliación & Co-ocurrencia
        </VChip>

        <!-- Botón de búsqueda específica -->
        <VBtn
          icon
          size="x-small"
          variant="tonal"
          :color="isSearchOpen ? 'primary' : 'secondary'"
          @click="isSearchOpen = !isSearchOpen"
          title="Buscar asociaciones de un producto específico"
        >
          <VIcon :icon="isSearchOpen ? 'tabler-x' : 'tabler-search'" size="16" />
        </VBtn>
      </div>
    </VCardTitle>

    <!-- Barra desplegable de búsqueda de producto para venta cruzada -->
    <VExpandTransition>
      <div v-if="isSearchOpen" class="pa-3 bg-light border-b">
        <VAutocomplete
          v-model="selectedSearchModel"
          :items="searchItems"
          :loading="searchLoading"
          item-title="name"
          item-value="id"
          placeholder="Buscar producto para ver con qué se vende..."
          density="compact"
          variant="outlined"
          hide-details
          clearable
          autofocus
          prepend-inner-icon="tabler-search"
          no-data-text="Escribe el nombre o ID del producto..."
          @update:search="searchCatalog"
          @update:model-value="onSearchSelected"
        >
          <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps" :title="item.raw.name" :subtitle="`ID: #${item.raw.id} · ${item.raw.active_ingredient || 'S/PA'}`" />
          </template>
        </VAutocomplete>
      </div>
    </VExpandTransition>

    <VCardText class="pa-0 flex-grow-1 overflow-hidden">
      <!-- Skeleton -->
      <div v-if="loading" class="skeleton-pulse pa-4">
        <div v-for="i in 7" :key="i" class="d-flex align-center justify-space-between mb-3 pb-2 border-b">
          <div class="d-flex gap-2 align-center flex-grow-1 min-width-0">
            <div style="flex: 1;">
              <div class="skeleton-line w-75 mb-1" />
              <div class="skeleton-line w-50" />
            </div>
            <div class="skeleton-avatar d-flex align-center justify-center flex-shrink-0" style="width: 20px; height: 20px;">
              <VIcon icon="tabler-plus" size="12" class="opacity-30" />
            </div>
            <div style="flex: 1;">
              <div class="skeleton-line w-75 mb-1" />
              <div class="skeleton-line w-50" />
            </div>
          </div>
          <div class="skeleton-line ms-3 flex-shrink-0" style="width: 90px; height: 22px; border-radius: 4px;" />
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else-if="!crossSelling.length" class="text-center pa-8 text-medium-emphasis">
        <VIcon icon="tabler-arrows-left-right" size="36" class="mb-2 opacity-30" />
        <div class="text-subtitle-2 font-weight-bold">
          {{ selectedProductId ? 'No hay ventas cruzadas registradas para este producto' : 'No se han detectado asociaciones frecuentes' }}
        </div>
        <div class="text-caption text-disabled mb-3">
          {{ selectedProductId ? 'Prueba seleccionando otro producto o limpiando el filtro.' : 'No hay coincidencias de productos vendidos juntos en este período.' }}
        </div>
        <VBtn
          v-if="selectedProductId"
          variant="tonal"
          color="primary"
          size="small"
          class="font-weight-bold"
          @click="emit('select-product', null)"
        >
          Ver Todas las Asociaciones
        </VBtn>
      </div>

      <!-- Tabla sin scroll horizontal -->
      <div v-else class="cross-selling-table-wrapper">
        <VTable density="compact" class="cross-selling-table">
          <thead>
            <tr>
              <th class="text-left font-weight-bold text-caption text-uppercase px-3">Asociación de Productos (A + B)</th>
              <th class="text-right font-weight-bold text-caption text-uppercase px-3" style="width: 140px;">Confianza / Frecuencia</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(pair, idx) in crossSelling" :key="idx" class="border-b">
              <td class="py-2 px-3">
                <div class="d-flex align-center gap-1 gap-sm-2 min-width-0">
                  <!-- Producto A (Clickeable para filtrar) -->
                  <div
                    class="d-flex flex-column min-width-0 product-item-box rounded px-1 py-0.5 cursor-pointer"
                    style="flex: 1 1 0;"
                    :class="{ 'product-active': Number(selectedProductId) === Number(pair.product_id_a) }"
                    :title="`Filtrar asociaciones de: ${pair.product_a}`"
                    @click="handleSelectProduct(pair.product_id_a, pair.product_a)"
                  >
                    <span class="text-caption font-weight-bold text-uppercase text-truncate mb-0" :title="pair.product_a">
                      {{ pair.product_a }}
                    </span>
                    <div class="d-flex align-center gap-1 text-super-xs text-medium-emphasis">
                      <span class="font-weight-bold">#{{ pair.product_id_a }}</span>
                      <span>·</span>
                      <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 70px;">
                        {{ pair.lab_a || 'S/L' }}
                      </span>
                    </div>
                  </div>

                  <div class="d-flex align-center justify-center text-medium-emphasis flex-shrink-0 px-0.5">
                    <VIcon icon="tabler-plus" size="13" />
                  </div>

                  <!-- Producto B (Clickeable para filtrar) -->
                  <div
                    class="d-flex flex-column min-width-0 product-item-box rounded px-1 py-0.5 cursor-pointer"
                    style="flex: 1 1 0;"
                    :class="{ 'product-active': Number(selectedProductId) === Number(pair.product_id_b) }"
                    :title="`Filtrar asociaciones de: ${pair.product_b}`"
                    @click="handleSelectProduct(pair.product_id_b, pair.product_b)"
                  >
                    <span class="text-caption font-weight-bold text-uppercase text-truncate mb-0" :title="pair.product_b">
                      {{ pair.product_b }}
                    </span>
                    <div class="d-flex align-center gap-1 text-super-xs text-medium-emphasis">
                      <span class="font-weight-bold">#{{ pair.product_id_b }}</span>
                      <span>·</span>
                      <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 70px;">
                        {{ pair.lab_b || 'S/L' }}
                      </span>
                    </div>
                  </div>
                </div>
              </td>

              <td class="text-right px-3" style="width: 140px;">
                <div class="d-flex flex-column align-end">
                  <div class="d-flex align-center gap-1">
                    <VChip
                      :color="getConfidenceColor(pair.confidence_percent ?? 40)"
                      class="font-weight-black text-super-xs"
                      size="x-small"
                      variant="tonal"
                      label
                    >
                      {{ pair.confidence_percent ?? 40 }}%
                    </VChip>
                    <span class="text-caption font-weight-black text-high-emphasis">
                      {{ pair.frequency }} <span class="font-weight-normal text-medium-emphasis text-super-xs">veces</span>
                    </span>
                  </div>
                  <div class="w-100 mt-1 d-flex align-center justify-end" style="max-width: 110px;">
                    <VProgressLinear
                      :model-value="pair.confidence_percent ?? 40"
                      :color="getConfidenceColor(pair.confidence_percent ?? 40)"
                      height="3"
                      rounded
                    />
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <VDivider />
      <div class="pa-2 px-3 px-sm-4 d-flex align-center justify-space-between bg-surface">
        <span class="text-caption text-medium-emphasis font-weight-medium">
          Página {{ page }} {{ selectedProductId ? '(Filtrado)' : '' }}
        </span>
        <div class="d-flex gap-1">
          <VBtn
            icon="tabler-chevron-left"
            size="x-small"
            variant="tonal"
            :disabled="page <= 1 || loading"
            @click="emit('page-change', page - 1)"
          />
          <VBtn
            icon="tabler-chevron-right"
            size="x-small"
            variant="tonal"
            :disabled="!hasMore() || loading"
            @click="emit('page-change', page + 1)"
          />
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.cross-selling-table-wrapper {
  overflow-x: hidden !important;
  width: 100%;
}

.cross-selling-table {
  table-layout: fixed !important;
  width: 100% !important;
}

.cross-selling-table :deep(.v-table__wrapper) {
  overflow-x: hidden !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
  line-height: 1.1;
}

.product-item-box {
  transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
  border: 1px solid transparent;
}

.product-item-box:hover {
  background-color: rgba(var(--v-theme-primary), 0.08);
  border-color: rgba(var(--v-theme-primary), 0.2);
}

.product-active {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}

.skeleton-pulse { animation: pulse 1.5s infinite ease-in-out; }
@keyframes pulse {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}
.skeleton-avatar { border-radius: 50%; background-color: rgba(var(--v-theme-on-surface), 0.1); }
.skeleton-line { height: 10px; background-color: rgba(var(--v-theme-on-surface), 0.1); border-radius: 4px; }
</style>
