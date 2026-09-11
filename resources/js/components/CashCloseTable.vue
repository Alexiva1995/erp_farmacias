<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { useAbility } from "@casl/vue";
import { formatDateSimple, formatPrice } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { ref, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const { can } = useAbility();

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalItems: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "delete", "refresh"]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const headers = computed(() => {
  const list = [
    {
      title: "ID",
      key: "product_id",
      sortable: true,
      align: "start",
      cellClass: 'font-weight-black text-primary d-none d-sm-table-cell',
      headerClass: 'd-none d-sm-table-cell',
    },
    { title: "Producto", key: "product.name", sortable: true, width: "320px" },
    { title: "Cantidad", key: "discrepancy", align: "center", sortable: true, width: "110px" },
    { title: "Costo", key: "product.unit_cost", align: "end", sortable: true, width: "110px" },
    { title: "Usuario", key: "user.name", sortable: true, width: "140px" },
  ];

  const enableLots = brandingStore.settings?.enable_lots ?? true;
  if (enableLots) {
    list.push({ title: "Supervisión", key: "supervisor.name", sortable: true, width: "140px" });
  }

  list.push(
    { title: "Monto", key: "amount", align: "end", sortable: true, width: "120px" },
    { title: "Acciones", key: "actions", sortable: false, align: "center", width: "130px" }
  );
  return list;
});

const editingId = ref(null);
const editingValue = ref(0);
const isSaving = ref(false);

const startEdit = (item) => {
  editingId.value = item.id;
  editingValue.value = item.discrepancy;
};

const cancelEdit = () => {
  editingId.value = null;
  editingValue.value = 0;
};

const saveEdit = async (item) => {
  if (isSaving.value) return;
  
  isSaving.value = true;
  try {
    const response = await axios.patch(`/inventory/count/${item.sourceType}/${item.id}/discrepancy`, {
      discrepancy: editingValue.value
    });
    
    if (response.data.success) {
      toast.success("Discrepancia actualizada correctamente.");
      emit("refresh");
      cancelEdit();
    } else {
      toast.error(response.data.message || "Error al actualizar.");
    }
  } catch (error) {
    console.error("Error al guardar discrepancia:", error);
    toast.error(error.response?.data?.message || "Error al guardar los cambios.");
  } finally {
    isSaving.value = false;
  }
};

const handleDelete = (item) => {
  emit("delete", item);
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden">
    <!-- Cabecera Estándar (igual a Productos / Inventario) -->
    <VCardTitle class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">Diferencias de Inventario para Cierre</span>
      <VSpacer />
      <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
        {{ props.totalItems }} DIFERENCIAS
      </VChip>
    </VCardTitle>

    <VDivider />

    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :headers="headers"
        :items="props.items"
        :items-length="props.totalItems"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :loading="props.loading"
        item-value="id"
        density="compact"
        class="text-no-wrap"
        hover
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product_id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + (item.productId || item.product_id)"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.productId || item.product_id || "—" }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-2 py-2">
            <div class="d-flex flex-column min-width-0 flex-grow-1">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                style="max-inline-size: 420px;"
                :title="item.product.name"
              >
                {{ item.product.name?.toUpperCase() }}
                <span v-if="item.product.iva == 1 || item.product.iva === true" class="text-xs text-disabled font-weight-regular"> (G)</span>
                <span v-if="item.product.is_colombian_origin == 1 || item.product.is_colombian_origin === true" class="text-xs text-disabled font-weight-regular"> (COL)</span>
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0-5">
                <span v-if="!isRestaurant && item.product.activeIngredient" class="text-disabled truncate" style="max-inline-size: 240px;">
                  {{ item.product.activeIngredient }}
                </span>
                <span v-if="!isRestaurant && item.product.activeIngredient" class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 180px;">
                  {{ item.product.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.discrepancy="{ item }">
          <div v-if="editingId === item.id" class="d-flex align-center justify-center gap-1" style="inline-size: 120px;">
            <AppTextField
              v-model.number="editingValue"
              type="number"
              density="compact"
              hide-details
              autofocus
              @keyup.enter="saveEdit(item)"
              @keyup.esc="cancelEdit"
            />
          </div>
          <div v-else class="text-center">
            <VChip
              :color="item.discrepancy > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
            </VChip>
          </div>
        </template>

        <template #item.product.unit_cost="{ item }">
          <span class="text-sm font-weight-medium">
            {{ formatPrice(item.product.unit_cost) }}
          </span>
        </template>

        <template #item.user.name="{ item }">
          <span class="text-xs text-capitalize font-weight-medium">
            {{ (item.user?.employee_name || '') + (item.user?.employee_last_name ? ` ${item.user.employee_last_name}` : '') || item.user?.name || '—' }}
          </span>
        </template>

        <template #item.supervisor.name="{ item }">
          <span class="text-xs text-capitalize font-weight-medium">
            {{ (item.supervisor?.employee_name || '') + (item.supervisor?.employee_last_name ? ` ${item.supervisor.employee_last_name}` : '') || '—' }}
          </span>
        </template>

        <template #item.amount="{ item }">
          <span
            :class="(editingId === item.id ? editingValue : item.discrepancy) > 0 ? 'text-success' : 'text-error'"
            class="text-sm font-weight-black"
          >
            {{ formatPrice(item.product.sale_price * (editingId === item.id ? editingValue : item.discrepancy)) }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1 px-2">
            <template v-if="editingId === item.id">
              <IconBtn color="success" size="small" :loading="isSaving" @click="saveEdit(item)">
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent">Guardar</VTooltip>
              </IconBtn>
              <IconBtn color="secondary" size="small" @click="cancelEdit">
                <VIcon icon="tabler-x" size="18" />
                <VTooltip activator="parent">Cancelar</VTooltip>
              </IconBtn>
            </template>
            <template v-else>
              <!-- Indicador de concordancia con trazabilidad -->
              <VTooltip :text="item.hasTraceability ? 'Coincide con movimiento registrado' : 'Sin movimiento en trazabilidad'" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VIcon
                    v-bind="tooltipProps"
                    :icon="item.hasTraceability ? 'tabler-circle-check' : 'tabler-circle-x'"
                    size="18"
                    :color="item.hasTraceability ? 'info' : 'error'"
                  />
                </template>
              </VTooltip>

              <VTooltip v-if="can('manage', 'admin')" text="Editar cantidad" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn
                    v-bind="tooltipProps"
                    color="warning"
                    size="small"
                    @click="startEdit(item)"
                  >
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>

              <VTooltip v-if="!item.hasTraceability" text="Eliminar registro" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn
                    v-bind="tooltipProps"
                    color="error"
                    size="small"
                    @click="handleDelete(item)"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
            </template>
          </div>
        </template>

        <template #no-data>
          <AppEmptyState
            title="No hay diferencias registradas"
            message="No se encontraron discrepancias pendientes para el cierre de este ciclo."
            icon="tabler-clipboard-check"
          />
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Cards) -->
    <div class="d-block d-md-none pa-2">
      <div v-if="props.loading" class="d-flex flex-column gap-2">
        <VProgressLinear indeterminate color="primary" class="mb-2" />
        <VSkeletonLoader v-for="i in 3" :key="i" type="list-item-two-line" class="mb-2 rounded-lg border" />
      </div>
      
      <div v-else-if="props.items.length" class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.items"
          :key="item.id"
          variant="flat"
          class="border mb-1 rounded-lg pa-3"
        >
          <!-- Cabecera: Producto | Acciones -->
          <div class="d-flex align-start justify-space-between mb-2">
            <div class="d-flex flex-column min-width-0">
              <div class="d-flex align-center gap-2 mb-1">
                <a
                  :href="'/inventory/traceability?q=' + (item.productId || item.product_id)"
                  target="_blank"
                  class="text-decoration-none text-super-xs font-weight-black text-primary bg-primary-lighten-5 px-1-5 py-0-5 rounded"
                >
                  ID: {{ item.productId || item.product_id }}
                </a>
                <VTooltip :text="item.hasTraceability ? 'Coincide con trazabilidad' : 'Sin movimiento en trazabilidad'" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VIcon
                      v-bind="tooltipProps"
                      :icon="item.hasTraceability ? 'tabler-circle-check' : 'tabler-circle-x'"
                      size="16"
                      :color="item.hasTraceability ? 'info' : 'error'"
                    />
                  </template>
                </VTooltip>
              </div>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight text-truncate mb-1">
                {{ item.product.name }}
              </span>
              <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                <span v-if="!isRestaurant && item.product.activeIngredient" class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                  {{ item.product.activeIngredient }}
                </span>
                <span v-if="!isRestaurant && item.product.activeIngredient" class="text-disabled">|</span>
                <span class="text-primary font-weight-bold text-truncate" style="max-inline-size: 120px;">
                  {{ item.product.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
            <div class="d-flex align-start gap-1">
              <template v-if="editingId === item.id">
                <IconBtn color="success" variant="tonal" size="small" :loading="isSaving" @click="saveEdit(item)">
                  <VIcon icon="tabler-check" size="18" />
                </IconBtn>
                <IconBtn color="secondary" variant="tonal" size="small" @click="cancelEdit">
                  <VIcon icon="tabler-x" size="18" />
                </IconBtn>
              </template>
              <template v-else>
                <IconBtn
                  v-if="can('manage', 'admin')"
                  variant="tonal"
                  color="warning"
                  size="small"
                  @click="startEdit(item)"
                >
                  <VIcon icon="tabler-edit" size="18" />
                </IconBtn>
                <IconBtn
                  v-if="!item.hasTraceability"
                  variant="tonal"
                  color="error"
                  size="small"
                  @click="handleDelete(item)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                </IconBtn>
              </template>
            </div>
          </div>

          <VDivider class="my-2" />

          <!-- Resumen de Cantidades y Montos -->
          <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
            <div class="d-flex flex-column" style="min-inline-size: 80px;">
              <span class="text-super-xs text-disabled text-uppercase font-weight-black">Diferencia</span>
              <div v-if="editingId === item.id" class="mt-1">
                <AppTextField
                  v-model.number="editingValue"
                  type="number"
                  density="compact"
                  hide-details
                  @keyup.enter="saveEdit(item)"
                />
              </div>
              <VChip
                v-else
                :color="item.discrepancy > 0 ? 'success' : 'error'"
                size="x-small"
                label
                variant="tonal"
                class="font-weight-black mt-1"
              >
                {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
              </VChip>
            </div>
            <div class="d-flex flex-column text-center px-2">
              <span class="text-super-xs text-disabled text-uppercase font-weight-black">Costo U.</span>
              <span class="text-xs font-weight-bold">
                {{ formatPrice(item.product.unit_cost) }}
              </span>
            </div>
            <div class="d-flex flex-column text-right">
              <span class="text-super-xs text-disabled text-uppercase font-weight-black">Monto Total</span>
              <span 
                class="text-sm font-weight-black"
                :class="(editingId === item.id ? editingValue : item.discrepancy) > 0 ? 'text-success' : 'text-error'"
              >
                {{ formatPrice(item.product.sale_price * (editingId === item.id ? editingValue : item.discrepancy)) }}
              </span>
            </div>
          </div>

          <!-- Usuarios Responsables -->
          <div class="mt-2 d-flex align-center justify-space-between text-capitalize">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-user" size="14" class="text-disabled" />
              <span class="text-super-xs font-weight-medium">
                {{ item.user?.employee_name }} {{ item.user?.employee_last_name || '' }}
              </span>
            </div>
            <div v-if="item.supervisor" class="d-flex align-center gap-1">
              <VIcon icon="tabler-user-check" size="14" class="text-disabled" />
              <span class="text-super-xs font-weight-medium">
                {{ item.supervisor.employee_name }} {{ item.supervisor.employee_last_name || '' }}
              </span>
            </div>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalItems"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>

      <div v-else>
        <AppEmptyState
          title="No hay diferencias registradas"
          message="No se encontraron discrepancias pendientes para el cierre."
          icon="tabler-clipboard-check"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.px-1-5 {
  padding-left: 6px !important;
  padding-right: 6px !important;
}

.py-0-5 {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.leading-tight {
  line-height: 1.25 !important;
}

:deep(.v-data-table th) {
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
