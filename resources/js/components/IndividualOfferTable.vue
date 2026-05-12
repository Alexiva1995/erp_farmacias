<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  productsOffer: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOffer: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);
const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Producto", key: "product.name", sortable: true, width: "35%" },
  {
    title: "% DESC",
    key: "discount_percent",
    sortable: true,
    align: "center",
    width: "100px",
  },
  { title: "Precio Normal", key: "sale_price", sortable: false, align: "end" },
  {
    title: "Precio Oferta",
    key: "discount_price",
    sortable: false,
    align: "end",
  },
  { title: "Ventas", key: "sales_count", sortable: false, align: "center", width: "90px" },
  { title: "Vigencia", key: "validity", sortable: false, align: "center" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "100px",
  },
];

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString();
};

const calculateDiscountPrice = (price, discount) => {
  const salePrice = parseFloat(price) || 0;
  const discPercent = parseFloat(discount) || 0;
  return (salePrice * (1 - discPercent / 100)).toFixed(2);
};
</script>

<template>
  <div class="individual-offers-container">
    <!-- Vista de Escritorio (Tabla Premium) -->
    <VCard
      class="d-none d-md-block elevation-1 border-0 rounded-lg overflow-hidden"
    >
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.productsOffer"
        :items-length="props.totalOffer"
        :loading="props.loading"
        class="text-no-wrap premium-table"
        density="compact"
        fixed-header
        height="auto"
        @update:options="(options) => $emit('update:options', options)"
      >
        <!-- ID Column -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- Product Column -->
        <template #item.product.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span
              class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate"
              style="max-inline-size: 300px"
            >
              {{ item.product?.name || "SIN NOMBRE" }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs mt-1">
              <span
                class="text-disabled truncate"
                style="max-inline-size: 180px"
                >{{ item.product?.active_ingredient || "—" }}</span
              >
              <span class="text-disabled mx-1">|</span>
              <span
                class="text-primary font-weight-black text-uppercase truncate"
                style="max-inline-size: 120px"
              >
                {{ item.product?.laboratory?.name || "S/L" }}
              </span>
            </div>
          </div>
        </template>

        <!-- Discount Column -->
        <template #item.discount_percent="{ item }">
          <VChip
            size="small"
            color="success"
            variant="tonal"
            class="font-weight-black rounded"
          >
            {{ item.discount_percent }}%
          </VChip>
        </template>

        <!-- Sale Price -->
        <template #item.sale_price="{ item }">
          <span
            class="text-xs font-weight-bold text-medium-emphasis text-decoration-line-through"
          >
            ${{ (parseFloat(item.product?.sale_price) || 0).toFixed(2) }}
          </span>
        </template>

        <!-- Discount Price -->
        <template #item.discount_price="{ item }">
          <span class="text-sm font-weight-black text-success">
            ${{
              calculateDiscountPrice(
                item.product?.sale_price,
                item.discount_percent,
              )
            }}
          </span>
        </template>

        <!-- Sales Count Column -->
        <template #item.sales_count="{ item }">
          <div class="d-flex justify-center">
            <VChip
              size="small"
              color="info"
              variant="tonal"
              class="font-weight-black rounded"
              prepend-icon="tabler-shopping-cart"
            >
              {{ item.sales_count ?? 0 }}
            </VChip>
          </div>
        </template>

        <!-- Validity Column -->
        <template #item.validity="{ item }">
          <div class="d-flex flex-column align-center">
            <span class="text-super-xs font-weight-bold text-primary uppercase"
              >INICIO: {{ formatDate(item.start_date) }}</span
            >
            <span class="text-super-xs font-weight-bold text-error uppercase"
              >FIN: {{ formatDate(item.end_date) }}</span
            >
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VBtn
              icon
              size="x-small"
              color="warning"
              variant="tonal"
              class="rounded-circle shadow-sm"
              @click="$emit('edit-offer', item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent" location="top"
                >Editar Oferta</VTooltip
              >
            </VBtn>
            <VBtn
              icon
              size="x-small"
              color="error"
              variant="tonal"
              class="rounded-circle shadow-sm"
              @click="$emit('delete-offer', item.id)"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent" location="top"
                >Eliminar Oferta</VTooltip
              >
            </VBtn>
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
        v-else-if="props.productsOffer.length === 0"
        class="text-center py-8 bg-white rounded-lg border border-dashed"
      >
        <VIcon icon="tabler-tag-off" size="48" color="disabled" class="mb-2" />
        <div class="text-sm font-weight-black text-disabled uppercase">
          No hay ofertas individuales
        </div>
      </div>

      <div
        v-for="item in props.productsOffer"
        :key="item.id"
        class="premium-card mb-4 bg-white rounded-lg elevation-2 overflow-hidden border-0"
      >
        <!-- Badge Lateral de Descuento -->
        <div class="status-strip bg-success"></div>

        <div class="pa-4">
          <!-- Cabecera Tarjeta -->
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="d-flex align-center gap-1">
              <a
                :href="'/inventory/traceability?q=' + item.product?.id"
                target="_blank"
                class="text-decoration-none text-primary font-weight-black text-xs"
              >
                {{ item.id }}
              </a>
              <span class="text-disabled mx-1">|</span>
              <h3
                class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mb-0"
              >
                {{ item.product?.name?.toUpperCase() || "SIN NOMBRE" }}
              </h3>
            </div>

            <div class="d-flex gap-2 align-center">
              <VChip
                size="x-small"
                color="success"
                variant="flat"
                class="font-weight-black rounded-sm flex-shrink-0"
              >
                {{ item.discount_percent }}% OFF
              </VChip>

              <VChip
                size="x-small"
                color="info"
                variant="tonal"
                class="font-weight-black rounded-sm flex-shrink-0"
                prepend-icon="tabler-shopping-cart"
              >
                {{ item.sales_count ?? 0 }} ventas
              </VChip>
            </div>

            <div class="d-flex gap-2">
              <VBtn
                icon="tabler-edit"
                size="x-small"
                color="warning"
                variant="tonal"
                class="rounded-circle shadow-sm"
                @click="$emit('edit-offer', item)"
              />
              <VBtn
                icon="tabler-trash"
                size="x-small"
                color="error"
                variant="tonal"
                class="rounded-circle shadow-sm"
                @click="$emit('delete-offer', item.id)"
              />
            </div>
          </div>

          <!-- Producto y Precios -->
          <div class="bg-light pa-3 rounded-lg mb-3 border border-dashed">
            <div class="d-flex flex-column overflow-hidden mb-2">
              <span
                class="text-xs font-weight-black text-high-emphasis uppercase leading-tight truncate"
              >
                {{ item.product?.name || "SIN NOMBRE" }}
              </span>
              <span
                class="text-super-xs font-weight-bold text-disabled uppercase"
              >
                {{ item.product?.laboratory?.name || "SIN LABORATORIO" }}
              </span>
            </div>

            <div class="d-flex justify-space-between align-center">
              <div class="d-flex flex-column">
                <span
                  class="text-super-xs font-weight-bold text-disabled uppercase"
                  >Normal</span
                >
                <span
                  class="text-xs text-medium-emphasis text-decoration-line-through"
                >
                  ${{ (parseFloat(item.product?.sale_price) || 0).toFixed(2) }}
                </span>
              </div>
              <VIcon
                icon="tabler-arrow-right"
                size="14"
                class="text-disabled"
              />
              <div class="d-flex flex-column text-end">
                <span
                  class="text-super-xs font-weight-black text-success uppercase"
                  >Oferta</span
                >
                <span class="text-sm font-weight-black text-success">
                  ${{
                    calculateDiscountPrice(
                      item.product?.sale_price,
                      item.discount_percent,
                    )
                  }}
                </span>
              </div>
            </div>
          </div>

          <!-- Footer Tarjeta: Vigencia -->
          <div
            class="d-flex justify-space-between align-center pt-1 border-t mt-2"
          >
            <div class="d-flex align-center gap-1">
              <VIcon
                icon="tabler-calendar-play"
                size="12"
                class="text-success"
              />
              <span
                class="text-super-xs font-weight-bold text-disabled uppercase"
                >{{ formatDate(item.start_date) }}</span
              >
            </div>
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-calendar-off" size="12" class="text-error" />
              <span
                class="text-super-xs font-weight-bold text-disabled uppercase"
                >{{ formatDate(item.end_date) }}</span
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-4 pb-6" v-if="props.totalOffer > props.itemsPerPage">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalOffer / props.itemsPerPage)"
          density="compact"
          color="primary"
          @update:model-value="
            (val) =>
              $emit('update:options', {
                page: val,
                itemsPerPage: props.itemsPerPage,
                sortBy: [],
              })
          "
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: white !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-high-emphasis-opacity)
  ) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.premium-table :deep(td) {
  padding-block: 8px !important;
}

.premium-card {
  position: relative;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.status-strip {
  position: absolute;
  inline-size: 4px;
  inset-block: 0;
  inset-inline-start: 0;
}

.bg-light {
  background-color: #f1f5f9 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.uppercase {
  text-transform: uppercase;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.gap-1 {
  gap: 4px !important;
}
.gap-2 {
  gap: 8px !important;
}
</style>
