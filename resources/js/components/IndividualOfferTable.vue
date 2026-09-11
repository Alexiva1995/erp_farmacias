<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  productsOffer: { type: Array, required: true },
  loading:       { type: Boolean, default: false },
  totalOffer:    { type: Number, default: 0 },
  discount:      { type: Number, default: 0 },
  itemsPerPage:  { type: Number, required: true },
  page:          { type: Number, required: true },
  title:         { type: String, default: "" },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

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
  { title: "Producto",      key: "product.name",    sortable: true, width: "35%" },
  { title: "% DESC",        key: "discount_percent", sortable: true, align: "center", width: "90px" },
  { title: "P. Normal",     key: "sale_price",      sortable: false, align: "end",   width: "110px" },
  { title: "P. Oferta",     key: "discount_price",  sortable: false, align: "end",   width: "110px" },
  { title: "Ventas",        key: "sales_count",     sortable: false, align: "center", width: "90px" },
  { title: "Vigencia",      key: "validity",        sortable: false, align: "center", width: "160px" },
  { title: "Acciones",      key: "actions",         sortable: false, align: "center", width: "90px" },
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
  <VCard class="rounded-lg border shadow-sm overflow-hidden">
    <VCardTitle v-if="props.title" class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">{{ props.title }}</span>
      <VSpacer />
    </VCardTitle>

    <VDivider />

    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.productsOffer"
        :items-length="props.totalOffer"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="No se encontraron ofertas"
            message="No hay ofertas disponibles con los filtros actuales."
            icon="tabler-tag-off"
          />
        </template>

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
          <div class="d-flex align-center gap-x-2 py-2">
            <div class="d-flex flex-column min-width-0 flex-grow-1">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                style="max-inline-size: 420px;"
                :title="item.product?.name"
              >
                {{ item.product?.name?.toUpperCase() || "—" }}
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0-5">
                <span class="text-disabled font-weight-normal truncate" style="max-inline-size: 240px;">
                  {{ item.product?.active_ingredient || "—" }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 180px;">
                  {{ item.product?.laboratory?.name || "S/L" }}
                </span>
              </div>
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
          <span class="text-xs font-weight-bold text-medium-emphasis text-decoration-line-through">
            ${{ (parseFloat(item.product?.sale_price) || 0).toFixed(2) }}
          </span>
        </template>

        <!-- Discount Price -->
        <template #item.discount_price="{ item }">
          <span class="text-sm font-weight-black text-success">
            ${{ calculateDiscountPrice(item.product?.sale_price, item.discount_percent) }}
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
            <span class="text-super-xs font-weight-bold text-primary uppercase">
              INICIO: {{ formatDate(item.start_date) }}
            </span>
            <span class="text-super-xs font-weight-bold text-error uppercase">
              FIN: {{ formatDate(item.end_date) }}
            </span>
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn
              @click="emit('edit-offer', item)"
              color="warning"
              size="small"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Editar Oferta</VTooltip>
            </IconBtn>
            <IconBtn
              @click="emit('delete-offer', item.id)"
              color="error"
              size="small"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent">Eliminar Oferta</VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.productsOffer.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No hay ofertas individuales disponibles.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.productsOffer"
          :key="item.id"
          variant="flat"
          class="product-mobile-card border mb-1"
        >
          <div class="pa-2 pa-sm-3">
            <div class="d-flex gap-2 align-start">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <a
                    :href="'/inventory/traceability?q=' + item.product?.id"
                    target="_blank"
                    class="text-decoration-none text-primary font-weight-black text-super-xs bg-primary-lighten-5 px-1-5 py-0-5 rounded flex-shrink-0"
                  >
                    ID: {{ item.id }}
                  </a>
                  <span v-if="item.product?.laboratory?.name" class="text-primary font-weight-bold text-super-xs text-uppercase truncate" style="max-inline-size: 140px;">
                    {{ item.product.laboratory.name }}
                  </span>
                  <VSpacer />
                  <VChip
                    size="x-small"
                    color="success"
                    variant="flat"
                    class="font-weight-black text-super-xs flex-shrink-0"
                  >
                    {{ item.discount_percent }}% OFF
                  </VChip>
                </div>

                <h3 class="product-mobile-title font-weight-black text-high-emphasis text-uppercase truncate-2-lines mb-1 text-body-2">
                  {{ item.product?.name?.toUpperCase() || "SIN NOMBRE" }}
                </h3>

                <div v-if="item.product?.active_ingredient" class="d-flex align-center flex-wrap gap-x-1 text-super-xs">
                  <span class="text-medium-emphasis text-truncate" style="max-inline-size: 260px;">
                    {{ item.product.active_ingredient }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Caja compacta de Precios y Vigencia -->
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1 mt-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Normal:</span>
                <span class="text-xs text-medium-emphasis text-decoration-line-through">
                  ${{ (parseFloat(item.product?.sale_price) || 0).toFixed(2) }}
                </span>
              </div>

              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Oferta:</span>
                <span class="text-xs font-weight-black text-success">
                  ${{ calculateDiscountPrice(item.product?.sale_price, item.discount_percent) }}
                </span>
              </div>

              <div class="d-flex flex-column text-end">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Ventas:</span>
                <span class="text-xs font-weight-bold text-info">
                  {{ item.sales_count ?? 0 }}
                </span>
              </div>
            </div>

            <!-- Vigencia Móvil -->
            <div class="d-flex justify-space-between align-center px-1 mt-1 text-super-xs font-weight-bold">
              <span class="text-primary">INI: {{ formatDate(item.start_date) }}</span>
              <span class="text-error">FIN: {{ formatDate(item.end_date) }}</span>
            </div>
          </div>

          <!-- Acciones Rectangulares en Móvil -->
          <div class="d-flex align-center border-t border-opacity-10 mobile-actions-bar">
            <VBtn
              color="warning"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="emit('edit-offer', item)"
            >
              <VIcon icon="tabler-edit" size="18" />
            </VBtn>
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="error"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="emit('delete-offer', item.id)"
            >
              <VIcon icon="tabler-trash" size="18" />
            </VBtn>
          </div>
        </VCard>
      </div>

      <div class="mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalOffer"
          :loading="props.loading"
          @change="(options) => emit('update:options', options)"
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

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
