<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  laboratory: { type: Object, default: () => ({}) },
  status: { type: Number, default: 0 },
  startDate: { type: String, default: "" },
  endDate: { type: String, default: "" },
  isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "refresh"]);

const { mobile } = useDisplay();

const loading = ref(false);
const saving = ref(false);
const details = ref([]);
const page = ref(1);
const itemsPerPage = ref(10);
const totalDetails = ref(0);
const searchQuery = ref("");

// Estado de edición
const affectedRows = ref(new Map());
const isDirty = computed(() => {
  return details.value.some(
    (r) =>
      r.quantity !== affectedRows.value.get(r.id)?.quantity ||
      r.unit_cost !== affectedRows.value.get(r.id)?.unit_cost
  );
});

let searchDebounceTimer;
watch(searchQuery, () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    page.value = 1;
    fetchDetails();
  }, 300);
});

watch(
  () => props.modelValue,
  (val) => {
    if (val && props.laboratory) {
      page.value = 1;
      searchQuery.value = "";
      affectedRows.value.clear();
      fetchDetails();
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  page.value = 1;
  searchQuery.value = "";
  affectedRows.value.clear();
};

const formatUsd = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount ?? 0);
};

const headers = [
  { title: "PRODUCTO", key: "product_name", sortable: false },
  { title: "PROVEEDOR", key: "supplier_name", sortable: false },
  { title: "CANTIDAD", key: "quantity", sortable: false, width: "130px" },
  { title: "COST", key: "unit_cost", sortable: false, width: "130px" },
  { title: "SUBTOTAL", key: "subtotal", sortable: false, width: "130px" },
  { title: "ACCIÓN", key: "actions", sortable: false, align: "end", width: "80px" },
];

const getStatusLabel = (status) => {
  const labels = {
    0: "PENDIENTE",
    1: "ENVIADA",
    2: "COMPLETADA",
  };
  return labels[status] ?? "DESCONOCIDO";
};

const getStatusColor = (status) => {
  const colors = {
    0: "warning",
    1: "info",
    2: "success",
  };
  return colors[status] ?? "secondary";
};

const fetchDetails = async () => {
  if (!props.laboratory) return;
  loading.value = true;
  try {
    const labId = props.laboratory.laboratory_id ?? 0;
    const { data } = await axios.get(
      `/suppliers/purchase-orders-laboratory/${labId}/details`,
      {
        params: {
          page: page.value,
          itemsPerPage: itemsPerPage.value,
          search: searchQuery.value,
          status: props.status,
          start_date: props.startDate,
          end_date: props.endDate,
        },
      }
    );

    details.value = data.data.data;
    totalDetails.value = data.data.total;

    // Inicializar mapa de filas originales
    affectedRows.value.clear();
    details.value.forEach((row) => {
      affectedRows.value.set(row.id, {
        quantity: row.quantity,
        unit_cost: row.unit_cost,
      });
    });
  } catch (error) {
    toast.error("Error al cargar los productos del laboratorio.");
  } finally {
    loading.value = false;
  }
};

const updateQuantity = (item, newQuantity) => {
  const parsed = parseInt(newQuantity, 10);
  if (isNaN(parsed) || parsed <= 0) return;
  item.quantity = parsed;
  item.subtotal = item.quantity * item.unit_cost;
};

const handleSaveBatchChanges = async () => {
  const changes = details.value
    .filter((r) => {
      const original = affectedRows.value.get(r.id);
      return original && (original.quantity !== r.quantity || original.unit_cost !== r.unit_cost);
    })
    .map((r) => ({
      id: r.id,
      order_id: r.order_id,
      quantity: r.quantity,
      unit_cost: r.unit_cost,
    }));

  if (changes.length === 0) return;

  saving.value = true;
  try {
    // Agrupar cambios por orden para reutilizar el endpoint existente
    const changesByOrder = {};
    changes.forEach((c) => {
      if (!changesByOrder[c.order_id]) changesByOrder[c.order_id] = [];
      changesByOrder[c.order_id].push({
        id: c.id,
        quantity: c.quantity,
        unit_cost: c.unit_cost,
      });
    });

    for (const orderId of Object.keys(changesByOrder)) {
      await axios.put(`/suppliers/purchase-orders/${orderId}`, {
        items: changesByOrder[orderId],
      });
    }

    toast.success("Cambios guardados correctamente.");
    emit("refresh");
    await fetchDetails();
  } catch (error) {
    toast.error("Error al guardar los cambios en las cantidades.");
  } finally {
    saving.value = false;
  }
};

const handleDeleteDetail = async (detail) => {
  const result = await Swal.fire({
    title: "¿Eliminar producto del pedido?",
    text: `Se eliminará "${detail.product_name}" de la orden de compra.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/purchase-orders/details/${detail.id}`);
      toast.success("Producto eliminado del pedido.");
      emit("refresh");
      await fetchDetails();
    } catch (error) {
      toast.error("Error al eliminar el producto del pedido.");
    }
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1100"
    persistent
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="management-dialog rounded-lg overflow-hidden">
      <!-- Header del Dialog -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center text-white">
          <div class="d-flex align-center gap-3">
            <VAvatar color="white" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-flask" color="white" size="24" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-black leading-tight text-white">
                {{ props.laboratory?.laboratory_name || 'Sin Laboratorio' }}
              </div>
              <div class="text-caption text-white opacity-80 font-weight-bold uppercase">
                {{ props.laboratory?.laboratory_id ? `ID: #${props.laboratory.laboratory_id}` : 'ORDEN DE COMPRA POR LABORATORIO' }}
              </div>
            </div>
          </div>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <VBtn
              v-if="isDirty"
              color="success"
              variant="flat"
              prepend-icon="tabler-device-floppy"
              size="small"
              class="font-weight-bold rounded-lg"
              :loading="saving"
              @click="handleSaveBatchChanges"
            >
              Guardar Cambios
            </VBtn>
            <VBtn
              icon
              variant="tonal"
              color="white"
              density="comfortable"
              class="rounded-lg"
              @click="closeDialog"
            >
              <VIcon>tabler-x</VIcon>
            </VBtn>
          </div>
        </div>
      </VCardTitle>

      <!-- Resumen Superior Adaptativo -->
      <div class="pa-4 bg-var-theme-background border-b shadow-inner-sm">
        <VRow no-gutters class="gap-y-3">
          <VCol cols="4" sm="4">
            <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Total SKUs</div>
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-box" size="16" class="text-info" />
              <span class="text-sm font-weight-bold">{{ props.laboratory?.total_skus || 0 }} SKUs</span>
            </div>
          </VCol>
          <VCol cols="4" sm="4">
            <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Unidades Solicitadas</div>
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-packages" size="16" class="text-warning" />
              <span class="text-sm font-weight-bold">{{ props.laboratory?.total_units || 0 }} uds</span>
            </div>
          </VCol>
          <VCol cols="4" sm="4" class="text-right text-sm-left">
            <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Total Estimado</div>
            <div class="d-flex align-center gap-1 justify-end justify-sm-start">
              <VIcon icon="tabler-currency-dollar" size="16" class="text-success" />
              <span class="text-sm font-weight-black text-success">${{ formatUsd(props.laboratory?.total_amount_usd) }}</span>
            </div>
          </VCol>
        </VRow>
      </div>

      <!-- Barra de Filtro Interno -->
      <VCardText class="pa-4 border-b bg-var-theme-background">
        <VRow dense align="center">
          <VCol cols="12" sm="8" md="6">
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar producto o código de barras..."
              prepend-inner-icon="tabler-search"
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>
          <VSpacer />
          <VCol cols="auto">
            <span class="text-xs text-disabled font-weight-bold">
              Mostrando {{ details.length }} de {{ totalDetails }} registros
            </span>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Contenido / Tabla de Productos -->
      <VCardText class="pa-0">
        <VDataTableServer
          :headers="headers"
          :items="details"
          :items-length="totalDetails"
          :items-per-page="itemsPerPage"
          :page="page"
          :loading="loading"
          density="compact"
          hover
          class="text-no-wrap"
          @update:options="(options) => {
            page = options.page;
            itemsPerPage = options.itemsPerPage;
            fetchDetails();
          }"
        >
          <!-- Producto -->
          <template #item.product_name="{ item }">
            <div class="py-2">
              <div class="font-weight-bold text-sm text-high-emphasis">
                <span class="text-primary mr-1">#{{ item.product_id }}</span>
                {{ item.product_name }}
              </div>
              <div v-if="item.product_barcode" class="text-xs text-disabled">
                <VIcon icon="tabler-barcode" size="14" class="me-1" />
                {{ item.product_barcode }}
              </div>
            </div>
          </template>

          <!-- Proveedor -->
          <template #item.supplier_name="{ item }">
            <VChip
              size="x-small"
              color="info"
              variant="tonal"
              class="font-weight-bold text-uppercase"
            >
              <VIcon icon="tabler-building-store" size="12" class="me-1" />
              {{ item.supplier_name }}
            </VChip>
          </template>

          <!-- Cantidad (Editable) -->
          <template #item.quantity="{ item }">
            <div style="max-inline-size: 110px;">
              <VTextField
                v-if="item.order_status === 0"
                :model-value="item.quantity"
                type="number"
                min="1"
                density="compact"
                variant="outlined"
                hide-details
                class="quantity-input"
                @update:model-value="updateQuantity(item, $event)"
              />
              <span v-else class="font-weight-bold text-sm">
                {{ item.quantity }}
              </span>
            </div>
          </template>

          <!-- Costo Unitario -->
          <template #item.unit_cost="{ item }">
            <span class="text-sm font-weight-medium">
              ${{ formatUsd(item.unit_cost) }}
            </span>
          </template>

          <!-- Subtotal -->
          <template #item.subtotal="{ item }">
            <span class="text-sm font-weight-black text-high-emphasis">
              ${{ formatUsd(item.subtotal) }}
            </span>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end">
              <VTooltip v-if="item.order_status === 0" text="Eliminar del pedido" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="small"
                    @click="handleDeleteDetail(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-disabled text-sm">
              No hay productos pedidos para este laboratorio en el estado seleccionado
            </div>
          </template>
        </VDataTableServer>
      </VCardText>

      <!-- Footer del Dialog -->
      <VCardActions class="pa-4 border-t bg-surface d-flex justify-space-between align-center">
        <span class="text-xs text-medium-emphasis">
          Los cambios en cantidades se recalculan automáticamente en la orden de compra del proveedor.
        </span>
        <VBtn
          variant="outlined"
          color="secondary"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e293b 100%);
}

.shadow-inner-sm {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 5%);
}

.text-xxs {
  font-size: 0.65rem !important;
}

.leading-tight {
  line-height: 1.25;
}

.quantity-input :deep(input) {
  text-align: center;
  font-weight: 700;
  padding: 4px 8px;
}
</style>
