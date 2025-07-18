<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  allProducts: {
    type: Array,
    default: () => [],
  },
  monthName: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue", "adjust-prices"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const selectedProductForExclusion = ref(null);
const excludedProducts = ref([]);

const headers = [
  { title: "Producto", key: "name", sortable: false },
  { title: "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Precio Original", key: "unit_cost", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const formatCurrency = (value) => {
  if (!value && value !== 0) return "N/A";
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};

const addProductToExclusions = () => {
  if (!selectedProductForExclusion.value) return;

  const isAlreadyExcluded = excludedProducts.value.some(
    (product) => product.id === selectedProductForExclusion.value.id
  );

  if (isAlreadyExcluded) {
    return;
  }

  excludedProducts.value.push(selectedProductForExclusion.value);
  selectedProductForExclusion.value = null;
};

const removeProductFromExclusions = (productId) => {
  excludedProducts.value = excludedProducts.value.filter(
    (product) => product.id !== productId
  );
};

const handleSubmit = () => {
  const adjustmentData = {
    excludedProducts: excludedProducts.value,
  };

  emit("adjust-prices", adjustmentData);
};

const resetForm = () => {
  excludedProducts.value = [];
  selectedProductForExclusion.value = null;
};

watch(
  () => props.modelValue,
  (newValue) => {
    if (!newValue) {
      resetForm();
    }
  }
);

const isFormValid = computed(() => {
  return true;
});
</script>

<template>
  <VDialog v-model="isVisible" max-width="900" persistent scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div>
          <h4 class="text-h4 mb-1">Reajustar Precios</h4>
          <p class="text-subtitle-1 text-medium-emphasis mb-0">
            {{
              monthName
                ? `Productos caducados - ${monthName}`
                : "Ajustar precios de productos caducados"
            }}
          </p>
        </div>
        <VBtn variant="text" icon size="small" @click="isVisible = false">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div class="mb-6">
          <h5 class="text-h5 mb-4">Productos a Excluir del Reajuste</h5>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Selecciona los productos que NO recibirán el reajuste de precio
          </p>

          <VRow align="center">
            <VCol cols="12" md="9">
              <VAutocomplete
                v-model="selectedProductForExclusion"
                :items="allProducts"
                item-title="custom_display"
                item-value="id"
                return-object
                label="Buscar producto"
                placeholder="Escriba para buscar..."
                variant="outlined"
                clearable
                no-data-text="No se encontraron productos"
                :custom-filter="() => true"
              >
                <template #selection="{ item }">
                  {{ item.raw.id }} - {{ item.raw.name }}
                </template>

                <template #item="{ props: itemProps, item }">
                  <VListItem
                    v-bind="itemProps"
                    :title="`${item.raw.id} - ${item.raw.name}`"
                  >
                  </VListItem>
                </template>
              </VAutocomplete>
            </VCol>
            <VCol cols="12" md="3">
              <VBtn
                color="primary"
                variant="flat"
                @click="addProductToExclusions"
                :disabled="!selectedProductForExclusion"
                block
              >
                Agregar
              </VBtn>
            </VCol>
          </VRow>
        </div>

        <div>
          <div class="d-flex align-center justify-space-between mb-4">
            <h5 class="text-h5">
              Productos Excluidos ({{ excludedProducts.length }})
            </h5>
          </div>

          <div v-if="excludedProducts.length === 0" class="text-center py-8">
            <VIcon
              icon="tabler-package-off"
              size="48"
              class="text-disabled mb-4"
            />
            <p class="text-body-1 text-disabled">
              No hay productos excluidos del reajuste
            </p>
            <p class="text-body-2 text-disabled">
              Todos los productos del mes recibirán el reajuste de precio
            </p>
          </div>

          <VDataTable
            v-else
            :headers="headers"
            :items="excludedProducts"
            item-value="id"
            class="text-no-wrap"
            hide-default-footer
          >
            <template #item.name="{ item }">
              <div class="d-flex align-center gap-x-3">
                <VAvatar
                  v-if="item.photo_url"
                  size="32"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                />
                <VAvatar v-else size="32" variant="tonal" rounded>
                  <VIcon icon="tabler-pill" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-medium">
                    {{ item.name || "Producto sin nombre" }}
                  </span>
                  <span class="text-caption text-disabled">
                    {{ item.active_ingredient || "N/A" }}
                  </span>
                </div>
              </div>
            </template>

            <template #item.laboratory.name="{ item }">
              <span class="font-weight-medium">
                {{ item.laboratory?.name || "N/A" }}
              </span>
            </template>

            <template #item.unit_cost="{ item }">
              <span class="font-weight-medium">
                {{ formatCurrency(item.unit_cost || 0) }}
              </span>
            </template>

            <template #item.actions="{ item }">
              <VTooltip text="Remover de exclusiones">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn
                    v-bind="tooltipProps"
                    size="small"
                    color="error"
                    variant="text"
                    @click="removeProductFromExclusions(item.id)"
                  >
                    <VIcon icon="tabler-trash" size="20" />
                  </IconBtn>
                </template>
              </VTooltip>
            </template>
          </VDataTable>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          variant="outlined"
          @click="isVisible = false"
          class="flex-fill me-2"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="!isFormValid"
          @click="handleSubmit"
          class="flex-fill ms-2"
        >
          Aplicar Reajuste
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
