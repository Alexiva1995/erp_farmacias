<script setup>
import { computed, ref } from "vue";
import axios from "@/plugins/axios";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faLock, faUnlock } from "@fortawesome/free-solid-svg-icons";

library.add(faLock, faUnlock);

import { useBrandingStore } from "@/stores/useBrandingStore";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  products: { type: Array, required: true },
  profitability: { type: Number, required: true },
  settings: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: 'asc' },
});

const brandingStore = useBrandingStore();
const isMinimarket = computed(() => brandingStore.settings?.business_type === 'minimarket');
const isMiniMarket = isMinimarket;

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || 'asc' }];
});

const emit = defineEmits(["refresh", "update:options", "editProduct", "updateProduct"]);

const headers = computed(() => {
  if (isMinimarket.value) {
    return [
      { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary d-none d-sm-table-cell", headerClass: "d-none d-sm-table-cell" },
      { title: "Producto", key: "name", sortable: true },
      { title: "Costo Base", key: "unit_cost", sortable: true, align: "end" },
      { title: "TAX (USA)", key: "tax_usa", sortable: false, align: "end" },
      { title: "Envío", key: "shipping_cost", sortable: false, align: "end" },
      { title: "Embalaje", key: "packaging_cost", sortable: false, align: "end" },
      { title: "Margen Gastos", key: "expense_margin", sortable: false, align: "end" },
      { title: "Margen Utilidad", key: "profit_margin", sortable: false, align: "end" },
      { title: "Precio Venta", key: "sale_price", sortable: true, align: "end" },
      { title: "Acciones", key: "actions", sortable: false, align: "center" },
    ];
  }
  return [
    { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary d-none d-sm-table-cell", headerClass: "d-none d-sm-table-cell" },
    { title: "Producto", key: "name", sortable: true },
    { title: "Costo", key: "unit_cost", sortable: true, align: "end" },
    { title: "Precio Venta", key: "sale_price", sortable: true, align: "end" },
    { title: "% Utilidad", key: "profitability", sortable: true, align: "center" },
    { title: "Acciones", key: "actions", sortable: false, align: "center" },
  ];
});

const loadingLocks = ref({});

async function toggleLock(productId, percentage) {
  if (loadingLocks.value[productId]) return;
  loadingLocks.value[productId] = true;

  try {
    const response = await axios.post("/finances/profitability/product/toggle-lock", {
      product_id: productId,
      profitability_percentage: percentage
    });
    toast.success("Estado de bloqueo actualizado");
    if (response.data?.data) {
      emit("updateProduct", response.data.data);
    } else {
      emit("refresh");
    }
  } catch (error) {
    console.error("Error al actualizar el bloqueo de margen:", error);
    toast.error("Error al cambiar el estado de bloqueo");
  } finally {
    loadingLocks.value[productId] = false;
  }
}

const formatPrice = (price) => {
  return new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

const getProfitabilityPercentage = (item) => {
  return item.profitability?.is_locked == "1"
    ? parseInt(item.profitability.profitability_percentage)
    : parseInt(props.profitability);
};
</script>

<template>
  <div class="profitability-table-wrapper">
    <!-- Vista Escritorio: Tabla Premium -->
    <VCard
      v-if="!$vuetify.display.smAndDown"
      class="rounded-lg border shadow-sm overflow-hidden bg-surface"
    >
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        :sort-by="sortByModel"
        class="text-no-wrap"
        density="comfortable"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black"
            :class="[
              item.profitability?.is_locked == '1'
                ? 'text-error'
                : 'text-primary',
            ]"
          >
            {{ item.id }}
          </a>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                :class="{
                  'text-warning':
                    item.psychotropic == 1 || item.psychotropic === true,
                }"
                style="max-inline-size: 380px"
                :title="item.name"
              >
                {{ item.name?.toUpperCase() }}
                <span
                  v-if="item.iva == 1 || item.iva === true"
                  class="text-xs text-disabled font-weight-regular"
                >
                  (G)</span
                >
                <span
                  v-if="
                    item.is_colombian_origin == 1 ||
                    item.is_colombian_origin === true
                  "
                  class="text-xs text-disabled font-weight-regular"
                >
                  (COL)</span
                >
              </span>
              <div class="d-flex align-center gap-1 text-super-xs mt-0-5">
                <span
                  class="text-disabled truncate"
                  style="max-inline-size: 200px"
                  >{{ item.active_ingredient || "—" }}</span
                >
                <span class="text-disabled mx-1">|</span>
                <span
                  class="text-primary font-weight-black text-uppercase truncate"
                  style="max-inline-size: 150px"
                >
                  {{ isMiniMarket ? (item.category?.name || 'SIN CATEGORÍA') : (item.laboratory?.name || "S/L") }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.unit_cost="{ item }">
          <span class="text-sm font-weight-medium text-high-emphasis">
            {{ formatPrice(item.unit_cost) }}
          </span>
        </template>

        <template #item.sale_price="{ item }">
          <div class="d-flex flex-column text-end">
            <span
              :class="[
                'text-sm font-weight-black',
                item.profitability?.is_locked == '1'
                  ? 'text-error'
                  : 'text-primary',
              ]"
            >
              {{ formatPrice(item.sale_price) }}
            </span>
            <span
              v-if="item.iva == 1"
              class="text-super-xs text-success"
              >IVA INC.</span
            >
          </div>
        </template>

        <template #item.tax_usa="{ item }">
          <span class="text-sm font-weight-medium">
            {{ item.profitability?.is_locked == '1' && item.profitability?.tax_usa !== null ? item.profitability.tax_usa : props.settings?.tax_usa || 0 }}%
          </span>
        </template>

        <template #item.shipping_cost="{ item }">
          <span class="text-sm font-weight-medium">
            {{ formatPrice(item.profitability?.is_locked == '1' && item.profitability?.shipping_cost !== null ? item.profitability.shipping_cost : props.settings?.shipping_cost || 0) }}
          </span>
        </template>

        <template #item.packaging_cost="{ item }">
          <span class="text-sm font-weight-medium">
            {{ formatPrice(item.profitability?.is_locked == '1' && item.profitability?.packaging_cost !== null ? item.profitability.packaging_cost : props.settings?.packaging_cost || 0) }}
          </span>
        </template>

        <template #item.expense_margin="{ item }">
          <span class="text-sm font-weight-bold">
            {{ item.profitability?.is_locked == '1' && item.profitability?.expense_margin !== null ? item.profitability.expense_margin : props.settings?.expense_margin || 0 }}%
          </span>
        </template>

        <template #item.profit_margin="{ item }">
          <span class="text-sm font-weight-bold text-primary">
            {{ item.profitability?.is_locked == '1' && item.profitability?.profit_margin !== null ? item.profitability.profit_margin : props.settings?.profit_margin || 0 }}%
          </span>
        </template>

        <template #item.profitability="{ item }">
          <div class="d-flex align-center justify-center gap-2">
            <VChip
              :color="item.profitability?.is_locked == '1' ? 'error' : 'primary'"
              label
              size="small"
              variant="tonal"
              class="font-weight-black px-2"
            >
              {{ getProfitabilityPercentage(item) }}%
            </VChip>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <IconBtn
              size="small"
              color="warning"
              @click="emit('editProduct', item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent" location="top">Editar Rentabilidad</VTooltip>
            </IconBtn>

            <IconBtn
              size="small"
              :color="item.profitability?.is_locked == '1' ? 'error' : 'secondary'"
              :loading="!!loadingLocks[item.id]"
              :disabled="!!loadingLocks[item.id]"
              @click="toggleLock(item.id, props.profitability)"
            >
              <VIcon
                :icon="item.profitability?.is_locked == '1' ? 'tabler-lock' : 'tabler-lock-open'"
                size="18"
              />
              <VTooltip activator="parent" location="top">
                {{ item.profitability?.is_locked == '1' ? 'Desbloquear Margen' : 'Bloquear Margen Personalizado' }}
              </VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil: Cards Homologadas con ProductMobileCard -->
    <div v-else class="d-flex flex-column gap-2">
      <VCard
        v-for="item in props.products"
        :key="item.id"
        variant="flat"
        class="border mb-1 rounded-lg overflow-hidden bg-white"
        :class="{
          'border-error': item.profitability?.is_locked == '1',
        }"
      >
        <div class="pa-3">
          <div class="d-flex justify-space-between align-start mb-2">
            <div class="d-flex align-center gap-2 min-width-0">
              <VAvatar
                size="40"
                variant="tonal"
                :color="item.profitability?.is_locked == '1' ? 'error' : 'primary'"
                class="rounded-lg font-weight-bold"
                :image="item.photo_url"
              >
                <span v-if="!item.photo_url">{{ item.name.charAt(0) }}</span>
              </VAvatar>

              <div class="d-flex flex-column min-width-0">
                <span class="text-xs font-weight-black text-primary uppercase">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none"
                    :class="[item.profitability?.is_locked == '1' ? 'text-error' : 'text-primary']"
                  >
                    #{{ item.id }}
                  </a>
                </span>
                <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                  {{ item.name }}
                </h3>
                <div class="d-flex align-center gap-1 text-super-xs text-medium-emphasis">
                  <span class="truncate">{{ item.active_ingredient || "—" }}</span>
                  <span>|</span>
                  <span class="text-primary font-weight-medium truncate">
                    {{ isMiniMarket ? (item.category?.name || 'SIN CATEGORÍA') : (item.laboratory?.name || "S/L") }}
                  </span>
                </div>
              </div>
            </div>

            <VChip
              :color="item.profitability?.is_locked == '1' ? 'error' : 'primary'"
              variant="tonal"
              size="small"
              class="font-weight-black px-2 rounded"
            >
              {{ getProfitabilityPercentage(item) }}%
            </VChip>
          </div>

          <VDivider class="my-2 border-opacity-10" />

          <div class="d-flex justify-space-between align-center mb-3">
            <div class="d-flex flex-column">
              <span class="text-super-xs text-disabled font-weight-medium uppercase">Costo Base</span>
              <span class="text-sm font-weight-medium text-high-emphasis">
                {{ formatPrice(item.unit_cost) }}
              </span>
            </div>
            <div class="d-flex flex-column align-end">
              <span class="text-super-xs text-disabled font-weight-medium uppercase">Precio Venta</span>
              <span
                :class="[
                  'text-base font-weight-black',
                  item.profitability?.is_locked == '1' ? 'text-error' : 'text-primary',
                ]"
              >
                {{ formatPrice(item.sale_price) }}
              </span>
            </div>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              variant="tonal"
              color="warning"
              size="small"
              class="rounded-lg flex-grow-1 font-weight-bold"
              prepend-icon="tabler-edit"
              @click="emit('editProduct', item)"
            >
              Editar
            </VBtn>
            <VBtn
              variant="tonal"
              :color="item.profitability?.is_locked == '1' ? 'error' : 'secondary'"
              size="small"
              class="rounded-lg px-3"
              :loading="!!loadingLocks[item.id]"
              :disabled="!!loadingLocks[item.id]"
              @click="toggleLock(item.id, props.profitability)"
            >
              <VIcon
                :icon="item.profitability?.is_locked == '1' ? 'tabler-lock' : 'tabler-lock-open'"
                size="18"
              />
            </VBtn>
          </div>
        </div>
      </VCard>

      <!-- Paginación Móvil Simplificada -->
      <VCard class="rounded-lg border shadow-sm pa-3 d-flex justify-center align-center bg-surface">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalProduct"
          :loading="props.loading"
          :sort-by="props.sortBy"
          :order-by="props.orderBy"
          @change="(options) => emit('update:options', options)"
        />
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.profitability-table-wrapper {
  margin-top: 0.5rem;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.min-width-0 {
  min-width: 0;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

:deep(.v-data-table td) {
  padding-block: 8px !important;
}

.border-error {
  border: 1px solid rgb(var(--v-theme-error)) !important;
}
</style>
