<script setup>
import { useAbility } from "@casl/vue";
import { formatDate, formatPrice } from "@/utils/formatters";
import { ref } from "vue";
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

const headers = [
  { title: "#", key: "product_id", sortable: true, width: "70px", align: "center" },
  { title: "Producto", key: "product.name", sortable: true, width: "320px" },
  { title: "Cantidad", key: "discrepancy", align: "center", sortable: true },
  { title: "Costo", key: "product.unit_cost", align: "end", sortable: true },
  { title: "Usuario", key: "user.name", sortable: true },
  { title: "Supervisión", key: "supervisor.name", sortable: true },
  { title: "Monto", key: "amount", align: "end", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

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

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard>
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
        no-data-text="No hay diferencias registradas para el cierre."
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product_id="{ item }">
          <span class="font-weight-black text-primary">{{ item.productId || item.product_id || "—" }}</span>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex flex-column text-normal-white py-1" style="max-inline-size: 320px;">
            <span class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight uppercase text-truncate" :title="item.product.name">
              {{ item.product.name.toUpperCase() }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs mt-1">
              <span class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.product.activeIngredient }}</span>
              <span class="text-disabled mx-1">|</span>
              <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                {{ item.product.laboratory?.name || 'S/L' }}
              </span>
            </div>
          </div>
        </template>

        <template #item.discrepancy="{ item }">
          <div v-if="editingId === item.id" class="d-flex align-center justify-center gap-1" style="inline-size: 150px;">
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
          <VChip
            v-else
            :color="item.discrepancy > 0 ? 'success' : 'error'"
            label
            size="x-small"
            variant="tonal"
            class="font-weight-black"
          >
            {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
          </VChip>
        </template>

        <template #item.product.unit_cost="{ item }">
          <span class="text-sm font-weight-medium">
            {{ formatPrice(item.product.unit_cost) }}
          </span>
        </template>

        <template #item.user.name="{ item }">
          <span class="text-xs text-capitalize">
            {{ (item.user?.employee_name || '') + (item.user?.employee_last_name ? ` ${item.user.employee_last_name}` : '') || item.user?.name || '—' }}
          </span>
        </template>

        <template #item.supervisor.name="{ item }">
          <span class="text-xs text-capitalize">
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
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingId === item.id">
              <IconBtn color="success" size="small" :loading="isSaving" @click="saveEdit(item)">
                <VIcon icon="tabler-check" />
                <VTooltip activator="parent">Guardar</VTooltip>
              </IconBtn>
              <IconBtn color="secondary" size="small" @click="cancelEdit">
                <VIcon icon="tabler-x" />
                <VTooltip activator="parent">Cancelar</VTooltip>
              </IconBtn>
            </template>
            <template v-else>
              <IconBtn
                v-if="can('manage', 'admin')"
                color="primary"
                size="small"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip activator="parent">Editar cantidad</VTooltip>
              </IconBtn>

              <IconBtn
                v-if="!item.hasTraceability"
                color="error"
                size="small"
                @click="handleDelete(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent">Eliminar registro</VTooltip>
              </IconBtn>
              <VIcon v-else icon="tabler-info-circle" size="small" color="secondary">
                <VTooltip activator="parent">Tiene movimientos en trazabilidad</VTooltip>
              </VIcon>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Cards) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.items.length === 0 && !props.loading" class="text-center py-8 text-disabled text-sm">
        No hay diferencias registradas.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.items"
          :key="item.id"
          variant="flat"
          class="cash-close-mobile-card border mb-1"
        >
          <div class="pa-3">
            <!-- Cabecera: Producto | Acciones -->
            <div class="d-flex align-start justify-space-between mb-3">
              <div class="d-flex flex-column min-width-0">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight text-truncate-1 mb-1">
                  {{ item.product.name.toUpperCase() }}
                </span>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.product.activeIngredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-bold text-truncate" style="max-inline-size: 120px;">{{ item.product.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
              <div class="d-flex align-center gap-1">
                <template v-if="editingId === item.id">
                  <IconBtn color="success" variant="tonal" size="32" :loading="isSaving" @click="saveEdit(item)">
                    <VIcon icon="tabler-check" size="18" />
                  </IconBtn>
                  <IconBtn color="secondary" variant="tonal" size="32" @click="cancelEdit">
                    <VIcon icon="tabler-x" size="18" />
                  </IconBtn>
                </template>
                <template v-else>
                  <IconBtn
                    v-if="can('manage', 'admin')"
                    variant="tonal"
                    color="primary"
                    size="32"
                    class="rounded"
                    @click="startEdit(item)"
                  >
                    <VIcon icon="tabler-edit" size="18" />
                    <VTooltip activator="parent">Editar cantidad</VTooltip>
                  </IconBtn>
                  <IconBtn
                    v-if="!item.hasTraceability"
                    variant="tonal"
                    color="error"
                    size="32"
                    class="rounded"
                    @click="handleDelete(item)"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </template>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Resumen de Cantidades y Montos -->
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
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
                  variant="flat"
                  class="text-super-xs font-weight-black mt-1"
                >
                  {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
                </VChip>
              </div>
              <div class="d-flex flex-column text-center px-2">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Costo U.</span>
                <span class="text-xs font-weight-medium">
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
            <div class="mt-3 d-flex align-center justify-space-between text-capitalize">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-user" size="12" class="text-disabled" />
                <span class="text-super-xs font-weight-medium">
                  {{ item.user?.employee_name }} {{ item.user?.employee_last_name || '' }}
                </span>
              </div>
              <div v-if="item.supervisor" class="d-flex align-center gap-1">
                <VIcon icon="tabler-user-check" size="12" class="text-disabled" />
                <span class="text-super-xs font-weight-medium">
                  {{ item.supervisor.employee_name }} {{ item.supervisor.employee_last_name || '' }}
                </span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalItems / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.cash-close-mobile-card {
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-truncate-1 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

.text-normal-white {
  overflow-wrap: break-word;
  white-space: normal;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table) {
  font-size: 0.8125rem;
}

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
