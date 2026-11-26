<script setup>
import axios from "@/plugins/axios";
import { confirmDialog, toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  settings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["refresh", "close-modal"]);

const brandingStore = useBrandingStore();
const isMinimarket = computed(() => {
  return brandingStore.settings?.business_type === "minimarket" || props.settings?.profitability_calculation_type === "compound";
});

const categories = ref([]);
const loadingList = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const selectedCategoryId = ref(null);

const form = reactive({
  profitability_percentage: null,
  shipping_cost: null,
  packaging_cost: null,
  expense_margin: null,
  profit_margin: null,
  tax_usa: null,
  status: "open",
});

const configuredCategories = computed(() => {
  return categories.value.filter((c) => c.has_rule && c.profitability);
});

const normalizeNullableNumber = (val) => {
  if (val === "" || val === null || val === undefined) return null;
  const parsed = Number(val);

  return Number.isFinite(parsed) ? parsed : null;
};

const resetForm = () => {
  form.profitability_percentage = null;
  form.shipping_cost = null;
  form.packaging_cost = null;
  form.expense_margin = null;
  form.profit_margin = null;
  form.tax_usa = null;
  form.status = "open";
};

const loadCategoryIntoForm = (catId) => {
  if (!catId) {
    resetForm();

    return;
  }

  const found = categories.value.find((c) => c.id === catId);
  if (found?.profitability) {
    form.profitability_percentage = found.profitability.profitability_percentage;
    form.shipping_cost = found.profitability.shipping_cost;
    form.packaging_cost = found.profitability.packaging_cost;
    form.expense_margin = found.profitability.expense_margin;
    form.profit_margin = found.profitability.profit_margin;
    form.tax_usa = found.profitability.tax_usa;
    form.status = found.profitability.status || "open";
  } else {
    resetForm();
  }
};

const fetchCategoriesProfitability = async () => {
  loadingList.value = true;
  try {
    const response = await axios.get("/finances/profitability/categories");
    categories.value = response.data?.data || [];
    if (selectedCategoryId.value) {
      loadCategoryIntoForm(selectedCategoryId.value);
    }
  } catch (error) {
    console.error("Error al cargar categorías de rentabilidad:", error);
    toast.error("No se pudieron cargar las reglas por categoría.");
  } finally {
    loadingList.value = false;
  }
};

watch(
  () => props.dialog,
  (isOpen) => {
    if (isOpen) {
      fetchCategoriesProfitability();
    } else {
      selectedCategoryId.value = null;
      resetForm();
    }
  },
);

watch(selectedCategoryId, (val) => {
  loadCategoryIntoForm(val);
});

const saveCategoryRule = async () => {
  if (!selectedCategoryId.value) return;

  const payload = {
    category_id: selectedCategoryId.value,
    profitability_percentage: normalizeNullableNumber(form.profitability_percentage),
    shipping_cost: normalizeNullableNumber(form.shipping_cost),
    packaging_cost: normalizeNullableNumber(form.packaging_cost),
    expense_margin: normalizeNullableNumber(form.expense_margin),
    profit_margin: normalizeNullableNumber(form.profit_margin),
    tax_usa: normalizeNullableNumber(form.tax_usa),
    status: form.status || "open",
  };

  saving.value = true;
  try {
    await axios.post("/finances/profitability/categories", payload);
    toast.success("Regla por categoría guardada y aplicada a los productos no bloqueados.");
    await fetchCategoriesProfitability();
    emit("refresh");
  } catch (error) {
    console.error("Error al guardar regla de categoría:", error);
    const message = error.response?.data?.message || "Error al guardar la regla de categoría.";
    toast.error(message);
  } finally {
    saving.value = false;
  }
};

const removeCategoryRule = async (category) => {
  const confirmed = await confirmDialog({
    title: "¿Eliminar regla de categoría?",
    text: `Los productos no bloqueados de "${category.name}" volverán a usar la configuración global.`,
    confirmButtonText: "Sí, eliminar",
  });
  if (!confirmed) return;

  deletingId.value = category.id;
  try {
    await axios.delete(`/finances/profitability/categories/${category.id}`);
    toast.success("Regla eliminada. Se restauró la herencia global.");
    if (selectedCategoryId.value === category.id) {
      resetForm();
    }
    await fetchCategoriesProfitability();
    emit("refresh");
  } catch (error) {
    console.error("Error al eliminar regla de categoría:", error);
    toast.error("No se pudo eliminar la regla.");
  } finally {
    deletingId.value = null;
  }
};

const formatSummary = (prof) => {
  if (!prof) return "";
  const parts = [];
  if (isMinimarket.value) {
    if (prof.tax_usa !== null) parts.push(`TAX: ${prof.tax_usa}%`);
    if (prof.shipping_cost !== null) parts.push(`Envío: $${prof.shipping_cost}`);
    if (prof.packaging_cost !== null) parts.push(`Emb.: $${prof.packaging_cost}`);
    if (prof.expense_margin !== null) parts.push(`Gastos: ${prof.expense_margin}%`);
    if (prof.profit_margin !== null) parts.push(`Util.: ${prof.profit_margin}%`);
  } else if (prof.profitability_percentage !== null) {
    parts.push(`Margen: ${prof.profitability_percentage}%`);
  }

  return parts.length ? parts.join(" • ") : "Hereda todo del global";
};
</script>

<template>
  <VDialog
    :model-value="props.dialog"
    max-width="640px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    @update:model-value="emit('close-modal')"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-category-2" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Rentabilidad por Categoría
            </h2>
            <span class="text-white opacity-75 uppercase font-weight-bold text-super-xs mt-1">
              Los campos vacíos heredan automáticamente la configuración global
            </span>
          </div>
          <VSpacer />
          <IconBtn
            color="white"
            variant="tonal"
            size="small"
            class="rounded-lg"
            :disabled="saving"
            @click="emit('close-modal')"
          >
            <VIcon icon="tabler-x" size="20" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-background d-flex flex-column gap-4">
        <VAlert variant="tonal" type="info" density="comfortable" rounded="lg" class="border-0" icon="tabler-info-circle">
          <div class="text-caption text-high-emphasis">
            Puedes definir solo los valores distintos para una categoría (ej. <strong>Envío</strong> en Skincare). Lo que dejes en blanco seguirá heredando del valor global y se actualizará si cambias el global.
          </div>
        </VAlert>

        <VCard variant="flat" class="pa-4 bg-surface rounded-lg border">
          <VRow dense>
            <VCol cols="12" sm="8">
              <VAutocomplete
                v-model="selectedCategoryId"
                :items="categories"
                :loading="loadingList"
                item-title="name"
                item-value="id"
                label="Seleccionar Categoría"
                placeholder="Ej: Skincare, Productos Colombianos..."
                density="compact"
                variant="outlined"
                clearable
                hide-details="auto"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #append>
                      <VChip v-if="item.raw.has_rule" size="x-small" color="primary" variant="tonal">
                        Configurada
                      </VChip>
                    </template>
                  </VListItem>
                </template>
              </VAutocomplete>
            </VCol>
            <VCol cols="12" sm="4">
              <VSwitch
                v-model="form.status"
                true-value="open"
                false-value="closed"
                :label="form.status === 'open' ? 'Regla Activa' : 'Regla Inactiva'"
                color="primary"
                density="compact"
                hide-details
                :disabled="!selectedCategoryId"
              />
            </VCol>
          </VRow>

          <div v-if="selectedCategoryId" class="mt-4">
            <VRow v-if="isMinimarket" dense>
              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="form.tax_usa"
                  label="TAX (USA) (%)"
                  :placeholder="`Hereda global: ${props.settings?.tax_usa ?? 0}%`"
                  type="number"
                  suffix="%"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="form.shipping_cost"
                  label="Envío por Unidad (USD)"
                  :placeholder="`Hereda global: $${props.settings?.shipping_cost ?? 0}`"
                  type="number"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="form.packaging_cost"
                  label="Embalaje por Unidad (USD)"
                  :placeholder="`Hereda global: $${props.settings?.packaging_cost ?? 0}`"
                  type="number"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="form.expense_margin"
                  label="Margen Gasto Fijo (%)"
                  :placeholder="`Hereda global: ${props.settings?.expense_margin ?? 0}%`"
                  type="number"
                  suffix="%"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.profit_margin"
                  label="Margen Ganancia (%)"
                  :placeholder="`Hereda global: ${props.settings?.profit_margin ?? 0}%`"
                  type="number"
                  suffix="%"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
            </VRow>

            <VRow v-else dense>
              <VCol cols="12">
                <AppTextField
                  v-model="form.profitability_percentage"
                  label="Porcentaje de Rentabilidad (%)"
                  :placeholder="`Hereda global: ${props.settings?.default_profitability_percentage ?? 0}%`"
                  type="number"
                  suffix="%"
                  clearable
                  density="compact"
                  hide-details="auto"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end mt-4">
              <VBtn
                color="primary"
                variant="flat"
                :loading="saving"
                :disabled="saving"
                prepend-icon="tabler-device-floppy"
                @click="saveCategoryRule"
              >
                Guardar y Aplicar a Categoría
              </VBtn>
            </div>
          </div>
        </VCard>

        <!-- Reglas Configuradas -->
        <VCard variant="flat" class="pa-4 bg-surface rounded-lg border">
          <div class="text-xs font-weight-bold text-disabled uppercase mb-2">
            Categorías con Regla Configurada ({{ configuredCategories.length }})
          </div>

          <VSkeletonLoader v-if="loadingList" type="list-item-two-line" />

          <div v-else-if="configuredCategories.length === 0" class="text-caption text-medium-emphasis py-2">
            No hay categorías con reglas personalizadas. Todas heredan la configuración global.
          </div>

          <VList v-else density="compact" class="pa-0">
            <VListItem
              v-for="cat in configuredCategories"
              :key="cat.id"
              class="px-2 rounded-lg border mb-1"
            >
              <VListItemTitle class="text-sm font-weight-bold">
                {{ cat.name }}
                <VChip
                  size="x-small"
                  :color="cat.profitability?.status === 'open' ? 'success' : 'secondary'"
                  variant="tonal"
                  class="ms-2"
                >
                  {{ cat.profitability?.status === "open" ? "Activa" : "Inactiva" }}
                </VChip>
              </VListItemTitle>
              <VListItemSubtitle class="text-caption">
                {{ formatSummary(cat.profitability) }}
              </VListItemSubtitle>
              <template #append>
                <IconBtn size="small" color="warning" @click="selectedCategoryId = cat.id">
                  <VIcon icon="tabler-edit" size="18" />
                </IconBtn>
                <IconBtn
                  size="small"
                  color="error"
                  :loading="deletingId === cat.id"
                  :disabled="deletingId === cat.id"
                  @click="removeCategoryRule(cat)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                </IconBtn>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-surface px-6 justify-end">
        <VBtn color="secondary" variant="outlined" @click="emit('close-modal')">
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-none {
  line-height: 1 !important;
}
</style>
