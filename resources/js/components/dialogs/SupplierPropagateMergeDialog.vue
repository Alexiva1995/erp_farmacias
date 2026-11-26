<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "merged"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const loading = ref(false);
const loadingSuppliers = ref(false);
const availableSuppliers = ref([]);
const targetSupplierId = ref(null);
const sourceSupplierId = ref("");

const fetchMasterSuppliers = async () => {
  loadingSuppliers.value = true;
  try {
    const response = await axios.get("/suppliers/available-suppliers", {
      headers: { "X-Master-Catalog-Context": "1" },
    });
    availableSuppliers.value = response.data.data || response.data || [];
  } catch (error) {
    console.error("Error al cargar proveedores del Master:", error);
    toast.error("No se pudo cargar la lista de proveedores del Master.");
  } finally {
    loadingSuppliers.value = false;
  }
};

const filterSupplier = (itemTitle, queryText, item) => {
  if (!queryText) return true;
  const text = queryText.toLowerCase().trim();
  const raw = item.raw;
  const name = String(raw.name || "").toLowerCase();
  const rif = String(raw.rif || "").toLowerCase();
  const id = String(raw.id || "").toLowerCase();
  return name.includes(text) || rif.includes(text) || id.includes(text);
};

const selectedTargetSupplier = computed(() => {
  if (!targetSupplierId.value) return null;
  return availableSuppliers.value.find((s) => Number(s.id) === Number(targetSupplierId.value)) || null;
});

const parsedSourceId = computed(() => {
  const num = Number(String(sourceSupplierId.value || "").trim());
  return Number.isInteger(num) && num > 0 ? num : null;
});

const canSubmit = computed(() => {
  return Boolean(
    targetSupplierId.value
    && parsedSourceId.value
    && Number(targetSupplierId.value) !== parsedSourceId.value
    && !loading.value,
  );
});

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      targetSupplierId.value = null;
      sourceSupplierId.value = "";
      fetchMasterSuppliers();
    }
  },
);

const handlePropagateMerge = async () => {
  if (!canSubmit.value) {
    toast.warning("Selecciona el proveedor principal del Master e ingresa un ID duplicado válido.");
    return;
  }

  const confirm = await Swal.fire({
    title: "¿Reparar Fusión en Todos los Tenants?",
    html: `
      <div class="text-left text-sm">
        <p class="mb-2">Se conservará en todos los tenants: <strong>#${selectedTargetSupplier.value?.id} - ${selectedTargetSupplier.value?.name}</strong>.</p>
        <p class="mb-2 text-error">Se fusionará y eliminará el ID huérfano: <strong>#${parsedSourceId.value}</strong>.</p>
        <p class="text-xs text-muted">Los productos, facturas, órdenes y movimientos asociados al ID #${parsedSourceId.value} se reasignarán al proveedor principal en cada tenant donde exista.</p>
      </div>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, Reparar en Tenants",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (!confirm.isConfirmed) return;

  loading.value = true;
  try {
    const response = await axios.post(
      "/suppliers/propagate-merge",
      {
        target_supplier_id: Number(targetSupplierId.value),
        source_supplier_id: parsedSourceId.value,
      },
      {
        headers: { "X-Master-Catalog-Context": "1" },
      },
    );

    toast.success(response.data?.message || "Fusión propagada a los tenants correctamente.");
    emit("merged", response.data?.data);
    isVisible.value = false;
  } catch (error) {
    console.error("Error al propagar fusión a tenants:", error);
    toast.error(error.response?.data?.message || "No se pudo reparar la fusión en los tenants.");
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="640" :fullscreen="$vuetify.display.mobile" persistent>
    <VCard class="rounded-xl border-0 shadow-lg d-flex flex-column overflow-hidden">
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="px-6 py-4 bg-primary d-flex align-center justify-space-between text-white">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1 flex-shrink-0">
              <VIcon color="primary" size="22">tabler-arrows-join-2</VIcon>
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-subtitle-1 font-weight-black text-white mb-0">
                Reparar Fusión de Proveedor en Tenants
              </h2>
              <span class="text-caption text-white opacity-85 font-weight-medium">
                Reasigna un ID eliminado en el Master hacia su proveedor principal en todos los tenants
              </span>
            </div>
          </div>
          <VBtn icon variant="tonal" color="white" size="x-small" class="rounded-lg" @click="isVisible = false">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6">
        <VAlert
          color="info"
          variant="tonal"
          icon="tabler-info-circle"
          density="compact"
          class="mb-5 rounded-lg text-xs"
        >
          Usa esta herramienta cuando un proveedor ya fue fusionado/eliminado en el Master (ej. ID 1043), pero todavía aparece en las farmacias o tenants.
        </VAlert>

        <VRow>
          <VCol cols="12">
            <VAutocomplete
              v-model="targetSupplierId"
              :items="availableSuppliers"
              :loading="loadingSuppliers"
              :custom-filter="filterSupplier"
              item-title="name"
              item-value="id"
              label="Proveedor Principal en Master (Destino)"
              placeholder="Buscar por ID, nombre o RIF (ej. 1032 INTERCONTINENTAL)..."
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-building-warehouse"
              clearable
              no-data-text="No se encontraron proveedores en el Master"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :title="item.raw.name"
                  :subtitle="`ID: #${item.raw.id} | RIF: ${item.raw.rif || 'N/A'}`"
                />
              </template>
            </VAutocomplete>
          </VCol>

          <VCol cols="12">
            <VTextField
              v-model="sourceSupplierId"
              type="number"
              min="1"
              label="ID Duplicado / Huérfano en Tenants (Origen a eliminar)"
              placeholder="Ej. 1043"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-hash"
              hint="Este ID será fusionado dentro del proveedor principal en cada tenant donde siga existiendo."
              persistent-hint
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-3 border-t flex-shrink-0">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              block
              height="44"
              class="font-weight-bold rounded-lg"
              :disabled="loading"
              @click="isVisible = false"
            >
              CANCELAR
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="44"
              class="font-weight-black rounded-lg"
              :loading="loading"
              :disabled="!canSubmit"
              @click="handlePropagateMerge"
            >
              <VIcon start icon="tabler-arrows-join-2" size="18" />
              REPARAR EN TENANTS
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
