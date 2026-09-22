<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  supplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "merged"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const loading = ref(false);
const loadingSuppliers = ref(false);
const availableSuppliers = ref([]);
const selectedDuplicateId = ref(null);
const primarySupplierChoice = ref("current"); // 'current' or 'duplicate'

const fetchAvailableSuppliers = async () => {
  loadingSuppliers.value = true;
  try {
    const response = await axios.get("/suppliers/available-suppliers");
    const list = response.data.data || response.data || [];
    availableSuppliers.value = list.filter((s) => s.id !== props.supplier.id);
  } catch (error) {
    console.error("Error al cargar lista de proveedores:", error);
    toast.error("No se pudo cargar la lista de proveedores para fusionar.");
  } finally {
    loadingSuppliers.value = false;
  }
};

watch(
  () => props.modelValue,
  (val) => {
    if (val && props.supplier?.id) {
      selectedDuplicateId.value = null;
      primarySupplierChoice.value = "current";
      fetchAvailableSuppliers();
    }
  },
);

const duplicateSupplier = computed(() => {
  if (!selectedDuplicateId.value) return null;
  return availableSuppliers.value.find((s) => s.id === selectedDuplicateId.value) || null;
});

const targetSupplier = computed(() => {
  return primarySupplierChoice.value === "current" ? props.supplier : duplicateSupplier.value;
});

const sourceSupplier = computed(() => {
  return primarySupplierChoice.value === "current" ? duplicateSupplier.value : props.supplier;
});

const handleMerge = async () => {
  if (!selectedDuplicateId.value) {
    toast.warning("Debes seleccionar el proveedor duplicado a fusionar.");
    return;
  }

  const result = await Swal.fire({
    title: "¿Confirmar Fusión de Proveedores?",
    html: `
      <div class="text-left text-sm">
        <p class="mb-2">Se conservará como proveedor principal: <strong>${targetSupplier.value?.name}</strong> (ID: ${targetSupplier.value?.id}).</p>
        <p class="mb-2 text-error">Se eliminará el proveedor: <strong>${sourceSupplier.value?.name}</strong> (ID: ${sourceSupplier.value?.id}).</p>
        <p class="text-xs text-muted">Todas las facturas, retenciones, órdenes, movimientos y productos del proveedor eliminado se transferirán al principal. Esta acción no se puede deshacer.</p>
      </div>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, Fusionar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#d33",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  loading.value = true;
  try {
    const response = await axios.post("/suppliers/merge", {
      target_supplier_id: targetSupplier.value.id,
      source_supplier_id: sourceSupplier.value.id,
    });

    toast.success(response.data.message || "Proveedores fusionados exitosamente.");
    emit("merged", response.data.supplier);
    isVisible.value = false;
  } catch (error) {
    console.error("Error al fusionar proveedores:", error);
    const msg = error.response?.data?.message || "Ocurrió un error al fusionar los proveedores.";
    toast.error(msg);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="750" persistent>
    <VCard class="rounded-xl shadow-lg">
      <VCardItem class="bg-primary py-4 px-6">
        <div class="d-flex align-center justify-space-between w-100 text-white">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-arrows-join-2" size="24" />
            <div>
              <div class="text-h6 font-weight-bold text-white">Fusionar Proveedores Duplicados</div>
              <div class="text-xs text-white-50">Unifica dos registros en uno solo sin perder información histórica</div>
            </div>
          </div>
          <VBtn icon="tabler-x" variant="text" color="white" density="compact" @click="isVisible = false" />
        </div>
      </VCardItem>

      <VCardText class="pa-6">
        <VAlert
          color="info"
          variant="tonal"
          icon="tabler-info-circle"
          density="compact"
          class="mb-5 rounded-lg text-xs"
        >
          Selecciona el proveedor duplicado. Podrás elegir cuál de los dos permanecerá como registro principal (conservando sus datos fiscales) mientras absorbe todas las facturas, retenciones y productos del otro.
        </VAlert>

        <VRow>
          <VCol cols="12">
            <label class="text-xs font-weight-bold text-high-emphasis mb-1 d-block">
              Proveedor Duplicado a Unificar
            </label>
            <VAutocomplete
              v-model="selectedDuplicateId"
              :items="availableSuppliers"
              :loading="loadingSuppliers"
              item-title="name"
              item-value="id"
              placeholder="Buscar por nombre o RIF..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              clearable
              no-data-text="No se encontraron otros proveedores disponibles"
            >
              <template #item="{ props, item }">
                <VListItem v-bind="props" :subtitle="`RIF: ${item.raw.rif || 'N/A'} | ID: ${item.raw.id} | Tipo: ${item.raw.type || 'Droguería'}`" />
              </template>
            </VAutocomplete>
          </VCol>
        </VRow>

        <div v-if="duplicateSupplier" class="mt-4">
          <div class="text-xs font-weight-bold text-uppercase text-medium-emphasis mb-3 letter-spacing-1">
            Comparación y Selección de Proveedor Principal
          </div>

          <VRow>
            <!-- Proveedor Seleccionado Inicialmente -->
            <VCol cols="12" sm="6">
              <VCard
                variant="outlined"
                class="pa-4 rounded-lg cursor-pointer transition-all position-relative"
                :class="{
                  'border-primary bg-primary-subtle border-2': primarySupplierChoice === 'current',
                  'border-dashed opacity-80': primarySupplierChoice !== 'current',
                }"
                @click="primarySupplierChoice = 'current'"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VChip
                    size="x-small"
                    :color="primarySupplierChoice === 'current' ? 'primary' : 'secondary'"
                    variant="flat"
                    class="font-weight-bold"
                  >
                    {{ primarySupplierChoice === 'current' ? '★ PRINCIPAL (Se Conserva)' : 'Secundario (Se Elimina)' }}
                  </VChip>
                  <VRadio
                    :model-value="primarySupplierChoice"
                    value="current"
                    density="compact"
                    hide-details
                  />
                </div>
                <div class="text-sm font-weight-bold text-high-emphasis line-clamp-1">
                  {{ props.supplier.name }}
                </div>
                <div class="text-xs text-medium-emphasis mt-1">ID: {{ props.supplier.id }}</div>
                <div class="text-xs text-medium-emphasis">RIF: {{ props.supplier.rif || 'Sin RIF' }}</div>
                <div class="text-xs text-medium-emphasis">Deuda: ${{ (props.supplier.debt ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</div>
              </VCard>
            </VCol>

            <!-- Proveedor Duplicado Seleccionado -->
            <VCol cols="12" sm="6">
              <VCard
                variant="outlined"
                class="pa-4 rounded-lg cursor-pointer transition-all position-relative"
                :class="{
                  'border-primary bg-primary-subtle border-2': primarySupplierChoice === 'duplicate',
                  'border-dashed opacity-80': primarySupplierChoice !== 'duplicate',
                }"
                @click="primarySupplierChoice = 'duplicate'"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VChip
                    size="x-small"
                    :color="primarySupplierChoice === 'duplicate' ? 'primary' : 'secondary'"
                    variant="flat"
                    class="font-weight-bold"
                  >
                    {{ primarySupplierChoice === 'duplicate' ? '★ PRINCIPAL (Se Conserva)' : 'Secundario (Se Elimina)' }}
                  </VChip>
                  <VRadio
                    :model-value="primarySupplierChoice"
                    value="duplicate"
                    density="compact"
                    hide-details
                  />
                </div>
                <div class="text-sm font-weight-bold text-high-emphasis line-clamp-1">
                  {{ duplicateSupplier.name }}
                </div>
                <div class="text-xs text-medium-emphasis mt-1">ID: {{ duplicateSupplier.id }}</div>
                <div class="text-xs text-medium-emphasis">RIF: {{ duplicateSupplier.rif || 'Sin RIF' }}</div>
                <div class="text-xs text-medium-emphasis">Deuda: ${{ (duplicateSupplier.debt ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VCardActions class="px-6 py-4 bg-light-surface border-t d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="loading"
          @click="isVisible = false"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="error"
          variant="flat"
          prepend-icon="tabler-arrows-join-2"
          :loading="loading"
          :disabled="!duplicateSupplier || loading"
          @click="handleMerge"
        >
          Confirmar Fusión
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-white-50 {
  color: rgba(255, 255, 255, 0.8) !important;
}

.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.cursor-pointer {
  cursor: pointer;
}

.border-2 {
  border-width: 2px !important;
}

.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
