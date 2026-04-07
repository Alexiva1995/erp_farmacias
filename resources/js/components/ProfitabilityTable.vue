<script setup>
import axios from "@/plugins/axios";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faLock, faUnlock } from "@fortawesome/free-solid-svg-icons";

library.add(faLock, faUnlock);

const props = defineProps({
  products: { type: Array, required: true },
  profitability: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: 'asc' },
});

const emit = defineEmits(["refresh", "update:options", "editProduct"]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Precio Venta", key: "sale_price", sortable: true },
  { title: "% Utilidad", key: "profitability", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

async function storeProfitability(product_id, profitability) {
  let data = {
    product_id: product_id,
    profitability_percentage: profitability,
    is_locked: 1,
  };

  //console.log(data)
  try {
    const response = await axios.post(
      "/finances/profitability/product/store",
      data,
    );

    console.log("Éxito:", response.data);
    emit("refresh");
  } catch (error) {
    console.error("Error en la solicitud:", error);

    if (error.response) {
      // El servidor respondió con un código de error
      console.error("Datos del error:", error.response.data);
      console.error("Status:", error.response.status);
      console.error("Headers:", error.response.headers);

      if (error.response.status === 405) {
        console.error("Sugerencia: Prueba con PUT/PATCH en lugar de POST");
      }
    } else if (error.request) {
      // La solicitud fue hecha pero no hubo respuesta
      console.error("No se recibió respuesta del servidor");
    } else {
      // Hubo un error al configurar la solicitud
      console.error("Error al configurar la solicitud:", error.message);
    }
  }
}

async function editProfitability(
  product_id,
  profitability,
  profitability_id,
  is_locked,
) {
  if (is_locked == 1) {
    is_locked = 0;
  } else {
    is_locked = 1;
  }

  let data = {
    id: profitability_id,
    product_id: product_id,
    profitability_percentage: profitability,
    is_locked: is_locked,
  };

  console.log(data);
  try {
    const response = await axios.post(
      "/finances/profitability/product/update",
      data,
    );

    console.log("Éxito:", response.data);
    emit("refresh");
  } catch (error) {
    console.error("Error en la solicitud:", error);

    if (error.response) {
      // El servidor respondió con un código de error
      console.error("Datos del error:", error.response.data);
      console.error("Status:", error.response.status);
      console.error("Headers:", error.response.headers);

      if (error.response.status === 405) {
        console.error("Sugerencia: Prueba con PUT/PATCH en lugar de POST");
      }
    } else if (error.request) {
      // La solicitud fue hecha pero no hubo respuesta
      console.error("No se recibió respuesta del servidor");
    } else {
      // Hubo un error al configurar la solicitud
      console.error("Error al configurar la solicitud:", error.message);
    }
  }
}

const productExistProfitability = async (
  product_id = null,
  profitability_id,
  profitability,
  is_locked = null,
) => {
  try {
    const response = await axios.get(
      `/finances/profitability/product/${product_id}`,
    );

    if (response.status === 200) {
      //console.log("producto id" . product_id)
      //console.log("Rentabilida ". profitability)
      //console.log("Is Locked ". is_locked)
      //console.log("Editar")
      await editProfitability(
        product_id,
        profitability,
        profitability_id,
        is_locked,
      );
    }
  } catch (error) {
    //console.log(product_id)
    //console.log(profitability)
    //console.log("Crear")
    await storeProfitability(product_id, profitability);
  }
};

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
  const perc =
    item.profitability?.is_locked == "1"
      ? parseFloat(item.profitability.profitability_percentage || 0)
      : parseFloat(props.profitability || 0);

  const salePrice = cost * (1 + perc / 100);
  return item.iva == 1 ? salePrice * 1.16 : salePrice;
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
        :sort-by="props.sortBy ? [{ key: props.sortBy, order: props.orderBy || 'asc' }] : []"
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
              {{ formatPrice(getCalculatedSalePrice(item)) }}
            </span>
            <span
              v-if="item.iva == 1"
              class="text-super-xs text-success font-weight-bold uppercase"
              >IVA INCLUIDO</span
            >
          </div>
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
                  item.profitability?.id,
                  item.profitability?.profitability_percentage,
                  item.id,
                  item.profitability?.is_locked,
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
              @click="
                productExistProfitability(
                  item.id,
                  item.profitability?.id,
                  props.profitability,
                  item.profitability?.is_locked,
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
            class="font-weight-black px-4 rounded-lg shadow-sm"
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
                {{ formatPrice(getCalculatedSalePrice(item)) }}
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
                  item.profitability?.id,
                  item.profitability?.profitability_percentage,
                  item.id,
                  item.profitability?.is_locked,
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
              @click="
                productExistProfitability(
                  item.id,
                  item.profitability?.id,
                  props.profitability,
                  item.profitability?.is_locked,
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
