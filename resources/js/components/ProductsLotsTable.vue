<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  totalProducts: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "adjust-lots"]);
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

const headers = computed(() => [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { title: "Producto", key: "name", sortable: true },
  { 
    title: "Stock Total", 
    key: "stock", 
    sortable: true, 
    align: "center" 
  },
  { 
    title: isRestaurant.value ? "Marca" : "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    visible: false,
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
    title: "EXP.", 
    key: "next_expiration", 
    sortable: false,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
]);

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
  <VCard>
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
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.photo_url"
              class="border elevation-1"
            />
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
                style="max-inline-size: 300px;"
                :class="{
                  'text-warning': item.psychotropic == 1 || item.psychotropic === true,
                }"
              >
                {{ item.name || "—" }}
                <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.active_ingredient || "" }}</span>
                <span v-if="!isRestaurant" class="text-disabled mx-1">|</span>
                <span v-if="isRestaurant && item.presentation" class="text-disabled truncate" style="max-inline-size: 180px;">
                  {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
                </span>
                <span v-if="isRestaurant && item.presentation" class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.stock="{ item }">
          <VChip
            :color="item.stock > 0 ? 'success' : 'error'"
            label
            size="x-small"
            variant="tonal"
            class="font-weight-black"
          >
            {{ item.stock }}
          </VChip>
        </template>

        <template #item.lots_count="{ item }">
          <span class="font-weight-medium">{{ item.lots?.length || 0 }}</span>
        </template>

        <template #item.next_expiration="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{ formatDateSimple(getNextExpiration(item.lots)) || 'N/A' }}</span>
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
                class="flex-shrink-0 mt-1 border"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate-2-lines">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none text-primary text-xs font-weight-black"
                  >
                    #{{ item.id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.name || 'S/N' }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span v-if="!isRestaurant" class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.active_ingredient || '' }}</span>
                  <span v-if="!isRestaurant" class="text-disabled">|</span>
                  <span v-if="isRestaurant && item.presentation" class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                    {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
                  </span>
                  <span v-if="isRestaurant && item.presentation" class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 120px;">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>
 
            <VDivider class="my-2 border-opacity-10" />
 
            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin mb-2">
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Stock Total</span>
                <span class="text-base font-weight-black" :class="item.stock > 0 ? 'text-success' : 'text-error'">
                  {{ item.stock || 0 }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Próx. Venc.</span>
                <span class="text-base font-weight-black text-warning">{{ formatDateSimple(getNextExpiration(item.lots)) || 'N/A' }}</span>
              </div>
            </div>

            <div class="mt-2 bg-var-theme-background rounded pa-2 d-flex justify-space-between align-center border-s-4 border-primary">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Lotes Activos</span>
                <span class="text-sm font-weight-black text-primary">{{ item.lots?.length || 0 }} Registros</span>
              </div>
            </div>
          </div>
 
          <!-- Acciones Rectangulares Movil -->
          <div class="d-flex border-t border-opacity-10">
            <VBtn
              block
              color="primary"
              variant="flat"
              class="rounded-0 font-weight-black"
              height="44"
              prepend-icon="tabler-package"
              @click="emit('adjust-lots', item)"
            >
              AJUSTAR LOTES
            </VBtn>
          </div>
        </VCard>
 
        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-2">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProducts"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
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

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-1 { gap: 4px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
