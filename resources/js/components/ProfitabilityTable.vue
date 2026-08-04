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

const emit = defineEmits(["refresh", "update:options", "editProduct"]);

const headers = computed(() => {
  if (isMinimarket.value) {
    return [
      { title: "id", key: "id", sortable: true },
      { title: "Producto", key: "name", sortable: true },
      { title: "Costo Base", key: "unit_cost", sortable: true },
      { title: "TAX (USA)", key: "tax_usa", sortable: false },
      { title: "Envío", key: "shipping_cost", sortable: false },
      { title: "Embalaje", key: "packaging_cost", sortable: false },
      { title: "Margen Gastos", key: "expense_margin", sortable: false },
      { title: "Margen Utilidad", key: "profit_margin", sortable: false },
      { title: "Precio Venta", key: "sale_price", sortable: true },
      { title: "Acciones", key: "actions", sortable: false },
    ];
  }
  return [
    { title: "id", key: "id", sortable: true },
    { title: "Producto", key: "name", sortable: true },
    { title: "Costo", key: "unit_cost", sortable: true },
    { title: "Precio Venta", key: "sale_price", sortable: true },
    { title: "% Utilidad", key: "profitability", sortable: true },
    { title: "Acciones", key: "actions", sortable: false },
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
    console.log("Éxito:", response.data);
    toast.success("Estado de bloqueo actualizado");
    emit("refresh");
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

const getCalculatedSalePrice = (item) => {
  const cost = parseFloat(item.unit_cost || 0);
  const useCompound = props.settings?.profitability_calculation_type === 'compound';

  if (isMinimarket.value || useCompound) {
    const isLocked = item.profitability?.is_locked == "1";
    const shipping = isLocked && item.profitability?.shipping_cost !== null ? parseFloat(item.profitability.shipping_cost) : parseFloat(props.settings?.shipping_cost || 0);
    const packaging = isLocked && item.profitability?.packaging_cost !== null ? parseFloat(item.profitability.packaging_cost) : parseFloat(props.settings?.packaging_cost || 0);
    const expense = isLocked && item.profitability?.expense_margin !== null ? parseFloat(item.profitability.expense_margin) : parseFloat(props.settings?.expense_margin || 0);
    const profit = isLocked && item.profitability?.profit_margin !== null ? parseFloat(item.profitability.profit_margin) : parseFloat(props.settings?.profit_margin || 0);
    const tax = isLocked && item.profitability?.tax_usa !== null ? parseFloat(item.profitability.tax_usa) : parseFloat(props.settings?.tax_usa || 0);

    const costWithTax = cost * (1 + tax / 100);
    const fixedExpenseAmount = costWithTax * (expense / 100);
    const profitDenominator = 1 - (profit / 100);
    if (profitDenominator <= 0) return 9999.99;
    const salePrice = (costWithTax + shipping + packaging + fixedExpenseAmount) / profitDenominator;
    return item.iva == 1 ? salePrice * 1.16 : salePrice;
  } else {
    const perc =
      item.profitability?.is_locked == "1"
        ? parseFloat(item.profitability.profitability_percentage || 0)
        : parseFloat(props.profitability || 0);

    const salePrice = cost * (1 + perc / 100);
    return item.iva == 1 ? salePrice * 1.16 : salePrice;
  }
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
        class="premium-table text-no-wrap"
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
                style="max-inline-size: 320px"
                :title="item.name"
              >
                {{ item.name.toUpperCase() }}
                <span
                  v-if="item.iva == 1 || item.iva === true"
                  class="text-xs text-disabled"
                >
                  (G)</span
                >
                <span
                  v-if="
                    item.is_colombian_origin == 1 ||
                    item.is_colombian_origin === true
                  "
                  class="text-xs text-disabled"
                >
                  (COL)</span
                >
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span
                  class="text-disabled truncate"
                  style="max-inline-size: 200px"
                  >{{ item.active_ingredient }}</span
                >
                <span class="text-disabled mx-1">|</span>
                <span
                  class="text-primary font-weight-black text-uppercase truncate"
                  style="max-inline-size: 150px"
                >
                  {{ item.laboratory?.name || "S/L" }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Eliminado Laboratory.name ya que está integrado en Name -->

        <template #item.unit_cost="{ item }">
          <span class="font-weight-black text-high-emphasis">
            {{ formatPrice(item.unit_cost) }}
          </span>
        </template>

        <template #item.sale_price="{ item }">
          <div class="d-flex flex-column">
            <span
              :class="[
                'font-weight-black text-lg',
                item.profitability?.is_locked == '1'
                  ? 'text-error'
                  : 'text-success',
              ]"
            >
              {{ formatPrice(item.sale_price) }}
            </span>
            <span
              v-if="item.iva == 1"
              class="text-super-xs text-success font-weight-bold uppercase"
              >IVA INCLUIDO</span
            >
          </div>
        </template>

        <template #item.tax_usa="{ item }">
          <span>
            {{ item.profitability?.is_locked == '1' && item.profitability?.tax_usa !== null ? item.profitability.tax_usa : props.settings?.tax_usa || 0 }}%
          </span>
        </template>

        <template #item.shipping_cost="{ item }">
          <span>
            {{ formatPrice(item.profitability?.is_locked == '1' && item.profitability?.shipping_cost !== null ? item.profitability.shipping_cost : props.settings?.shipping_cost || 0) }}
          </span>
        </template>

        <template #item.packaging_cost="{ item }">
          <span>
            {{ formatPrice(item.profitability?.is_locked == '1' && item.profitability?.packaging_cost !== null ? item.profitability.packaging_cost : props.settings?.packaging_cost || 0) }}
          </span>
        </template>

        <template #item.expense_margin="{ item }">
          <span class="font-weight-bold">
            {{ item.profitability?.is_locked == '1' && item.profitability?.expense_margin !== null ? item.profitability.expense_margin : props.settings?.expense_margin || 0 }}%
          </span>
        </template>

        <template #item.profit_margin="{ item }">
          <span class="font-weight-bold text-primary">
            {{ item.profitability?.is_locked == '1' && item.profitability?.profit_margin !== null ? item.profitability.profit_margin : props.settings?.profit_margin || 0 }}%
          </span>
        </template>

        <template #item.profitability="{ item }">
          <div class="d-flex align-center gap-2">
            <VProgressCircular
              :model-value="getProfitabilityPercentage(item)"
              size="32"
              width="3"
              :color="
                item.profitability?.is_locked == '1' ? 'error' : 'primary'
              "
              class="font-weight-black text-xs"
            >
              {{ getProfitabilityPercentage(item) }}
            </VProgressCircular>
            <span
              :class="[
                'font-weight-black',
                item.profitability?.is_locked == '1'
                  ? 'text-error'
                  : 'text-primary',
              ]"
            >
              {{ getProfitabilityPercentage(item) }}%
            </span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <IconBtn
              size="small"
              color="primary"
              variant="tonal"
              class="rounded-lg"
              @click="
                emit(
                  'editProduct',
                  item,
                )
              "
            >
              <VIcon icon="tabler-edit" size="18" />
            </IconBtn>

            <IconBtn
              size="small"
              :color="
                item.profitability?.is_locked == '1' ? 'error' : 'secondary'
              "
              variant="tonal"
              class="rounded-lg"
              :loading="!!loadingLocks[item.id]"
              :disabled="!!loadingLocks[item.id]"
              @click="
                toggleLock(
                  item.id,
                  props.profitability,
                )
              "
            >
              <VIcon
                :icon="
                  item.profitability?.is_locked == '1'
                    ? 'tabler-lock'
                    : 'tabler-lock-open'
                "
                size="18"
              />
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil: Cards Premium -->
    <div v-else class="d-flex flex-column gap-4">
      <VCard
        v-for="item in props.products"
        :key="item.id"
        class="rounded-lg border shadow-sm overflow-hidden"
        :class="{
          'border-error border-primary-opacity-30':
            item.profitability?.is_locked == '1',
        }"
      >
        <div class="pa-4 bg-surface-variant-light d-flex align-center gap-3">
          <VAvatar
            size="48"
            variant="tonal"
            :color="item.profitability?.is_locked == '1' ? 'error' : 'primary'"
            class="rounded-lg shadow-sm font-weight-black"
            :image="item.photo_url"
          >
            <span v-if="!item.photo_url">{{ item.name.charAt(0) }}</span>
          </VAvatar>

          <div class="d-flex flex-column flex-grow-1 min-width-0">
            <span
              class="text-base font-weight-black text-high-emphasis text-uppercase text-truncate"
            >
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
              <span class="mx-1 text-disabled">|</span>
              {{ item.name }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs mt-1">
              <span class="text-disabled truncate">{{
                item.active_ingredient
              }}</span>
              <span class="text-disabled mx-1">|</span>
              <span
                class="text-primary font-weight-black text-uppercase truncate"
              >
                {{ item.laboratory?.name || "S/L" }}
              </span>
            </div>
          </div>

          <VChip
            :color="item.profitability?.is_locked == '1' ? 'error' : 'primary'"
            variant="elevated"
            class="font-weight-black px-2 rounded-lg shadow-sm text-xs"
            style="min-width: 42px; justify-content: center;"
          >
            {{ getProfitabilityPercentage(item) }}%
          </VChip>
        </div>

        <VDivider class="opacity-10" />

        <div class="pa-4 pt-4">
          <div class="d-flex justify-space-between align-center mb-4">
            <div class="d-flex flex-column">
              <span
                class="text-super-xs text-disabled font-weight-black uppercase"
                >Precio de Venta</span
              >
              <span
                :class="[
                  'text-xl font-weight-black',
                  item.profitability?.is_locked == '1'
                    ? 'text-error'
                    : 'text-success',
                ]"
              >
                {{ formatPrice(item.sale_price) }}
              </span>
            </div>
            <div class="text-right d-flex flex-column">
              <span
                class="text-super-xs text-disabled font-weight-black uppercase"
                >Costo Base</span
              >
              <span class="text-base font-weight-bold text-high-emphasis">{{
                formatPrice(item.unit_cost)
              }}</span>
            </div>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="rounded-lg font-weight-black flex-grow-1"
              prepend-icon="tabler-edit"
              @click="
                emit(
                  'editProduct',
                  item,
                )
              "
            >
              Editar
            </VBtn>
            <VBtn
              variant="tonal"
              :color="
                item.profitability?.is_locked == '1' ? 'error' : 'secondary'
              "
              class="rounded-lg px-4"
              :loading="!!loadingLocks[item.id]"
              :disabled="!!loadingLocks[item.id]"
              @click="
                toggleLock(
                  item.id,
                  props.profitability,
                )
              "
            >
              <VIcon
                :icon="
                  item.profitability?.is_locked == '1'
                    ? 'tabler-lock'
                    : 'tabler-lock-open'
                "
              />
            </VBtn>
          </div>
        </div>
      </VCard>

      <!-- Paginación Móvil Simplificada -->
      <VCard
        class="rounded-lg border shadow-sm pa-3 d-flex justify-center align-center bg-surface"
      >
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
  margin-top: 1.5rem;
}

.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

:deep(.premium-table) {
  .v-data-table-header th {
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
    background: white !important;
    color: rgba(
      var(--v-theme-on-surface),
      var(--v-high-emphasis-opacity)
    ) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.05rem !important;
    text-transform: uppercase !important;
  }

  .v-data-table__td {
    padding-block: 12px !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
  }

  .v-data-table__tr:hover {
    background-color: rgba(var(--v-theme-primary), 0.02) !important;
  }
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.border-error {
  border: 1px solid rgb(var(--v-theme-error)) !important;
}
</style>
