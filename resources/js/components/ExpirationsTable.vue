<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatDateSimple } from "@/utils/formatters";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useAbility } from "@casl/vue";

const { can } = useAbility();
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
  lots: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    required: true,
  },
  totalLots: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  page: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits([
  "update:modelValue",
  "update:options",
  "expire-lot",
]);

const headers = computed(() => [
  { 
    title: "ID", 
    key: "product.id", 
    sortable: true,
    cellClass: 'font-weight-black text-primary'
  },
  { title: "Producto", key: "product.name", sortable: true },
  { 
    title: isRestaurant.value ? "Marca" : "Laboratorio", 
    key: "laboratory_name", 
    sortable: true,
    visible: false,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Nº Lote", key: "lot_number", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: true },
  { title: "Stock", key: "quantity", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
]);

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const getExpirationColor = (dateString) => {
  if (!dateString) return "text-disabled";
  const expDate = new Date(dateString);
  const today = new Date();
  const diffTime = expDate - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays <= 0) return "text-error font-weight-bold";
  if (diffDays <= 30) return "text-error";
  if (diffDays <= 90) return "text-warning";
  return "text-medium-emphasis";
};

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
};
</script>

<template>
  <VCard variant="flat">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        v-model="selected"
        :show-select="can('manage', 'admin') || can('manage', 'supervisor')"
        item-value="id"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.lots"
        :items-length="props.totalLots"
        :loading="props.loading"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.product?.id }}
          </a>
        </template>
        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.product?.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="border elevation-1"
            />
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase truncate" style="max-inline-size: 320px;">{{
                item.product?.name || ""
              }}</span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.product?.active_ingredient || "" }}</span>
                <span v-else class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.product?.presentation || "S/P" }}{{ item.product?.unit_of_measure ? ` (${item.product?.unit_of_measure})` : '' }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.laboratory_name="{ item }">
          <span class="text-uppercase">{{ item.product?.laboratory?.name || "—" }}</span>
        </template>

        <template #item.lot_number="{ item }">
          <span class="font-weight-medium">{{ item.lot_number }}</span>
        </template>

        <template #item.expiration_date="{ item }">
          <span :class="getExpirationColor(item.expiration_date)">
            {{ formatDate(item.expiration_date) }}
          </span>
        </template>

        <template #item.quantity="{ item }">
          <div class="text-center">
            <VChip
              :color="item.quantity > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ item.quantity }}
            </VChip>
          </div>
        </template>

        <template #item.actions="{ item }">
          <VTooltip v-if="can('manage', 'admin') || can('manage', 'supervisor')" location="top" text="Marcar como Caducado">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="error"
                @click="emit('expire-lot', item)"
              >
                <VIcon icon="tabler-calendar-off" />
              </IconBtn>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.lots.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos por vencer.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.lots"
          :key="item.id"
          variant="flat"
          class="product-mobile-card border mb-1 overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 mt-1 border"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate-2-lines">
                    <a
                      :href="'/inventory/traceability?q=' + item.product?.id"
                      target="_blank"
                      class="text-decoration-none text-primary text-xs font-weight-black"
                    >
                      {{ item.product?.id }}
                    </a>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.product?.name }}
                  </h3>
                </div>
                
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span v-if="!isRestaurant" class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.product?.active_ingredient }}</span>
                  <span v-else class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.product?.presentation || "S/P" }}{{ item.product?.unit_of_measure ? ` (${item.product?.unit_of_measure})` : '' }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 120px;">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>
 
            <VDivider class="my-3 border-opacity-10" />
 
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Vencimiento</span>
                <span :class="getExpirationColor(item.expiration_date)" class="text-base font-weight-black">
                  {{ formatDate(item.expiration_date) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Stock Lote</span>
                <span :class="(item.quantity ?? 0) > 0 ? 'text-success' : 'text-error'" class="text-base font-weight-black">
                  {{ item.quantity ?? 0 }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
            </div>
            
            <div class="mt-3 bg-var-theme-background-light rounded pa-2 d-flex justify-space-between align-center border-s-4 border-warning">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Lote No.</span>
                <span class="text-sm font-weight-black">{{ item.lot_number }}</span>
              </div>
            </div>
          </div>
 
          <VBtn 
            v-if="can('manage', 'admin') || can('manage', 'supervisor')"
            block 
            color="error" 
            variant="flat" 
            class="rounded-0 font-weight-black"
            height="44"
            prepend-icon="tabler-calendar-off" 
            @click="emit('expire-lot', item)"
          >
            MARCAR COMO CADUCADO
          </VBtn>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalLots"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
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

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.02);
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
