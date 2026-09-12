<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  offerData: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue", "modal-closed-view"]);

const { mobile } = useDisplay();

const productsList = ref([]);
const loadingProducts = ref(false);
const searchQuery = ref("");
const activeScope = ref("qualifying"); // 'qualifying' | 'all' | 'excluded'
const togglingProductIds = ref(new Set());

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed-view");
};

const fetchOfferProducts = async () => {
  if (!props.offerData?.id) return;

  loadingProducts.value = true;
  try {
    const response = await axios.get(
      `/tpv/promotions/expiration-offer/${props.offerData.id}/products`,
      {
        params: {
          q: searchQuery.value || undefined,
          scope: activeScope.value,
        },
      }
    );

    if (response.data.success) {
      productsList.value = response.data.data;
    } else {
      toast.error(response.data.message || "Error al cargar los productos");
    }
  } catch (error) {
    console.error("Error al obtener productos de la oferta:", error);
    toast.error("No se pudieron cargar los productos asociados a esta oferta.");
  } finally {
    loadingProducts.value = false;
  }
};

const handleToggleExclusion = async (item) => {
  if (!props.offerData?.id || !item.product_id) return;

  togglingProductIds.value.add(item.product_id);
  const previousState = item.is_excluded;
  // Optimistic update para todos los lotes de ese producto
  productsList.value.forEach((p) => {
    if (p.product_id === item.product_id) {
      p.is_excluded = !previousState;
      p.is_active_in_offer = previousState;
    }
  });

  try {
    const response = await axios.post(
      `/tpv/promotions/expiration-offer/${props.offerData.id}/toggle-exclusion`,
      {
        product_id: item.product_id,
      }
    );

    if (response.data.success) {
      toast.success(response.data.message);
      productsList.value.forEach((p) => {
        if (p.product_id === item.product_id) {
          p.is_excluded = response.data.is_excluded;
          p.is_active_in_offer = !response.data.is_excluded;
        }
      });
    } else {
      throw new Error(response.data.message);
    }
  } catch (error) {
    console.error("Error al cambiar estado de exclusión:", error);
    toast.error(error.response?.data?.message || "Error al cambiar estado del producto");
    productsList.value.forEach((p) => {
      if (p.product_id === item.product_id) {
        p.is_excluded = previousState;
        p.is_active_in_offer = !previousState;
      }
    });
  } finally {
    togglingProductIds.value.delete(item.product_id);
  }
};

// Totales computados
const totalProducts = computed(() => productsList.value.length);
const totalActive = computed(() => productsList.value.filter((p) => !p.is_excluded).length);
const totalExcluded = computed(() => productsList.value.filter((p) => p.is_excluded).length);

let debounceSearchTimer;
watch(
  [() => searchQuery.value, () => activeScope.value],
  () => {
    clearTimeout(debounceSearchTimer);
    debounceSearchTimer = setTimeout(() => {
      if (props.modelValue && props.offerData?.id) {
        fetchOfferProducts();
      }
    }, 300);
  }
);

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.offerData?.id) {
      searchQuery.value = "";
      activeScope.value = "qualifying";
      fetchOfferProducts();
    } else {
      productsList.value = [];
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="960px"
    width="960px"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @keydown.esc.prevent="onCancel"
  >
    <VCard
      v-if="props.offerData"
      :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'"
    >
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="38"
            class="me-3 elevation-1 text-primary font-weight-black"
          >
            <VIcon icon="tabler-hourglass-high" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Productos en Oferta por Vencimiento
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Regla: {{ props.offerData.months_to_expiration }} {{ props.offerData.months_to_expiration == 1 ? 'Mes' : 'Meses' }} o menos • Descuento: {{ parseFloat(props.offerData.discount_percentage || 0) }}%
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="onCancel"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Resumen de Métricas de la Oferta -->
        <VRow dense class="mb-4">
          <VCol cols="12" sm="4">
            <div class="pa-3 rounded border bg-var-theme-background d-flex align-center gap-3">
              <VAvatar size="34" color="primary" variant="tonal" class="rounded">
                <VIcon icon="tabler-packages" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Total Mostrados</span>
                <span class="text-h6 font-weight-black text-high-emphasis leading-tight">
                  {{ totalProducts }}
                </span>
              </div>
            </div>
          </VCol>

          <VCol cols="12" sm="4">
            <div class="pa-3 rounded border bg-var-theme-background d-flex align-center gap-3">
              <VAvatar size="34" color="success" variant="tonal" class="rounded">
                <VIcon icon="tabler-discount-check" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Aplica en TPV</span>
                <span class="text-h6 font-weight-black text-success leading-tight">
                  {{ totalActive }}
                </span>
              </div>
            </div>
          </VCol>

          <VCol cols="12" sm="4">
            <div class="pa-3 rounded border bg-var-theme-background d-flex align-center gap-3">
              <VAvatar size="34" color="error" variant="tonal" class="rounded">
                <VIcon icon="tabler-ban" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Excluidos de TPV</span>
                <span class="text-h6 font-weight-black text-error leading-tight">
                  {{ totalExcluded }}
                </span>
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Filtros y Barra de Búsqueda -->
        <div class="d-flex flex-column flex-sm-row align-stretch align-sm-center gap-3 mb-4">
          <VTextField
            v-model="searchQuery"
            placeholder="Buscar por producto, laboratorio, lote o código de barra..."
            prepend-inner-icon="tabler-search"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="rounded flex-grow-1 font-weight-bold"
          />

          <VBtnToggle
            v-model="activeScope"
            mandatory
            density="compact"
            color="primary"
            class="rounded border"
          >
            <VBtn value="qualifying" size="small" class="text-caption font-weight-bold">
              Próximos a Vencer (≤ {{ props.offerData.months_to_expiration }}m)
            </VBtn>
            <VBtn value="all" size="small" class="text-caption font-weight-bold">
              Todos los Lotes
            </VBtn>
            <VBtn value="excluded" size="small" class="text-caption font-weight-bold">
              Excluidos
            </VBtn>
          </VBtnToggle>

          <VBtn
            icon="tabler-refresh"
            variant="outlined"
            color="secondary"
            size="small"
            class="rounded align-self-sm-center"
            :loading="loadingProducts"
            @click="fetchOfferProducts"
          >
            <VIcon icon="tabler-refresh" size="18" />
            <VTooltip activator="parent">Actualizar</VTooltip>
          </VBtn>
        </div>

        <!-- Indicador de Carga -->
        <VProgressLinear
          v-if="loadingProducts"
          indeterminate
          color="primary"
          class="mb-3 rounded"
        />

        <!-- Estado Vacío -->
        <div
          v-if="!loadingProducts && productsList.length === 0"
          class="text-center py-10 border rounded bg-var-theme-background"
        >
          <VAvatar size="50" color="secondary" variant="tonal" class="mb-3">
            <VIcon icon="tabler-package-off" size="28" />
          </VAvatar>
          <h4 class="text-body-1 font-weight-black text-high-emphasis mb-1">
            No se encontraron productos
          </h4>
          <p class="text-caption text-medium-emphasis mb-3">
            {{ searchQuery ? 'No hay productos que coincidan con la búsqueda.' : (activeScope === 'qualifying' ? 'No hay productos con lotes que venzan en ' + props.offerData.months_to_expiration + ' meses o menos actualmente.' : 'No hay productos registrados en este criterio.') }}
          </p>
          <VBtn
            v-if="activeScope === 'qualifying'"
            color="primary"
            variant="tonal"
            size="small"
            class="font-weight-bold rounded"
            @click="activeScope = 'all'"
          >
            <VIcon start icon="tabler-list-search" size="16" />
            Buscar en todos los lotes del inventario
          </VBtn>
        </div>

        <!-- Lista de Productos en Desktop -->
        <div v-if="!loadingProducts && productsList.length > 0" class="d-none d-md-block border rounded overflow-hidden">
          <VTable density="compact" class="products-table">
            <thead>
              <tr class="bg-var-theme-background text-uppercase text-super-xs font-weight-bold">
                <th class="ps-3 py-2 text-start">Producto</th>
                <th class="py-2 text-start">Lote / Vencimiento</th>
                <th class="py-2 text-end">Stock</th>
                <th class="py-2 text-end">Precio Normal</th>
                <th class="py-2 text-end">Precio Promo</th>
                <th class="pe-3 py-2 text-center" style="width: 170px;">Aplicar en TPV</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in productsList"
                :key="item.lot_id"
                :class="item.is_excluded ? 'row-excluded' : ''"
              >
                <!-- Producto -->
                <td class="ps-3 py-2">
                  <div class="d-flex flex-column">
                    <span class="text-sm font-weight-black text-high-emphasis leading-tight">
                      {{ item.product_name }}
                    </span>
                    <div class="d-flex align-center gap-2 mt-0-5">
                      <span class="text-super-xs text-disabled uppercase font-weight-bold">
                        {{ item.laboratory_name }}
                      </span>
                      <span v-if="item.barcode" class="text-super-xs text-primary font-weight-medium">
                        • {{ item.barcode }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Lote y Vencimiento -->
                <td class="py-2">
                  <div class="d-flex flex-column">
                    <span class="text-xs font-weight-bold text-high-emphasis leading-tight">
                      Lote: {{ item.lot_number || 'S/N' }}
                    </span>
                    <div class="d-flex align-center gap-1 mt-0-5">
                      <span class="text-super-xs font-weight-bold text-warning">
                        Exp: {{ item.expiration_date }}
                      </span>
                      <span class="text-super-xs text-disabled">
                        ({{ item.days_remaining }} días)
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Stock -->
                <td class="py-2 text-end">
                  <span class="text-xs font-weight-bold text-high-emphasis">
                    {{ item.quantity }} uds.
                  </span>
                </td>

                <!-- Precio Normal -->
                <td class="py-2 text-end">
                  <span class="text-xs font-weight-medium text-disabled text-decoration-line-through">
                    ${{ parseFloat(item.sale_price || 0).toFixed(2) }}
                  </span>
                </td>

                <!-- Precio con Promo -->
                <td class="py-2 text-end">
                  <div class="d-flex flex-column align-end">
                    <span class="text-sm font-weight-black text-success leading-tight">
                      ${{ parseFloat(item.final_price || 0).toFixed(2) }}
                    </span>
                    <span class="text-super-xs font-weight-bold text-success">
                      -{{ item.discount_percentage }}%
                    </span>
                  </div>
                </td>

                <!-- Acción Toggle Exclusión -->
                <td class="pe-3 py-2 text-center">
                  <VBtn
                    :color="item.is_excluded ? 'secondary' : 'success'"
                    :variant="item.is_excluded ? 'tonal' : 'flat'"
                    size="small"
                    class="font-weight-bold rounded text-caption px-3"
                    :loading="togglingProductIds.has(item.product_id)"
                    @click="handleToggleExclusion(item)"
                  >
                    <VIcon
                      start
                      :icon="item.is_excluded ? 'tabler-eye-off' : 'tabler-check'"
                      size="16"
                    />
                    {{ item.is_excluded ? 'Excluido' : 'Aplica TPV' }}
                    <VTooltip activator="parent">
                      {{ item.is_excluded ? 'Haz clic para incluir en la promo de TPV' : 'Haz clic para excluir de la promo de TPV' }}
                    </VTooltip>
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <!-- Lista de Productos en Móvil -->
        <div v-if="!loadingProducts && productsList.length > 0" class="d-block d-md-none d-flex flex-column gap-2">
          <VCard
            v-for="item in productsList"
            :key="item.lot_id"
            variant="flat"
            class="border rounded pa-3"
            :class="item.is_excluded ? 'row-excluded' : ''"
          >
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column text-start">
                <span class="text-body-2 font-weight-black text-high-emphasis leading-tight">
                  {{ item.product_name }}
                </span>
                <span class="text-super-xs text-disabled uppercase mt-0-5 font-weight-bold">
                  {{ item.laboratory_name }} {{ item.barcode ? '• ' + item.barcode : '' }}
                </span>
              </div>
            </div>

            <!-- Datos de Lote y Stock -->
            <div class="pa-2 rounded bg-var-theme-background d-flex justify-space-between align-center mb-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled uppercase font-weight-bold">Lote / Vence:</span>
                <span class="text-xs font-weight-bold text-warning">
                  {{ item.lot_number }} • {{ item.expiration_date }}
                </span>
              </div>
              <div class="d-flex flex-column text-end">
                <span class="text-super-xs text-disabled uppercase font-weight-bold">Stock:</span>
                <span class="text-xs font-weight-black text-high-emphasis">
                  {{ item.quantity }} uds.
                </span>
              </div>
            </div>

            <!-- Precios y Acción -->
            <div class="d-flex justify-space-between align-center">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-decoration-line-through">
                  Norm: ${{ parseFloat(item.sale_price || 0).toFixed(2) }}
                </span>
                <span class="text-sm font-weight-black text-success leading-tight">
                  Promo: ${{ parseFloat(item.final_price || 0).toFixed(2) }}
                </span>
              </div>

              <VBtn
                :color="item.is_excluded ? 'secondary' : 'success'"
                :variant="item.is_excluded ? 'tonal' : 'flat'"
                size="small"
                class="font-weight-bold rounded text-caption"
                :loading="togglingProductIds.has(item.product_id)"
                @click="handleToggleExclusion(item)"
              >
                <VIcon
                  start
                  :icon="item.is_excluded ? 'tabler-eye-off' : 'tabler-check'"
                  size="16"
                />
                {{ item.is_excluded ? 'Excluido' : 'Aplica TPV' }}
              </VBtn>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer de Modal -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          height="40"
          class="font-weight-black rounded px-5 text-button uppercase"
          @click="onCancel"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.row-excluded {
  background-color: rgba(var(--v-theme-error), 0.04) !important;
  opacity: 0.75;
}

.products-table th {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  letter-spacing: 0.5px;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
