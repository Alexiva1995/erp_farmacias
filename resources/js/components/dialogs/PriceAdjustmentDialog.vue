<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  initialProducts: {
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

const selectedProducts = ref([]);

const headers = [
  { title: "Producto", key: "product.name", sortable: false },
  { title: "Nº Lote", key: "lot_number", sortable: false },
  { title: "Precio Original", key: "cost_per_unit", sortable: false },
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

const removeProduct = (productId) => {
  selectedProducts.value = selectedProducts.value.filter(
    (product) => product.id !== productId
  );
};

const handleSubmit = () => {
  if (selectedProducts.value.length === 0) {
    return;
  }

  const adjustmentData = {
    products: selectedProducts.value,
  };

  emit("adjust-prices", adjustmentData);
};

const resetForm = () => {
  selectedProducts.value = [];
};

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      selectedProducts.value = [...props.initialProducts];
    } else {
      resetForm();
    }
  }
);

watch(
  () => props.initialProducts,
  (newProducts) => {
    if (props.modelValue) {
      selectedProducts.value = [...newProducts];
    }
  },
  { deep: true }
);

const isFormValid = computed(() => {
  return selectedProducts.value.length > 0;
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
        <!-- Lista de Productos -->
        <div>
          <div class="d-flex align-center justify-space-between mb-4">
            <h5 class="text-h5">
              Productos Seleccionados ({{ selectedProducts.length }})
            </h5>
          </div>

          <div v-if="selectedProducts.length === 0" class="text-center py-8">
            <VIcon
              icon="tabler-package-off"
              size="48"
              class="text-disabled mb-4"
            />
            <p class="text-body-1 text-disabled">
              No hay productos seleccionados para el reajuste
            </p>
          </div>

          <VDataTable
            v-else
            :headers="headers"
            :items="selectedProducts"
            item-value="id"
            class="text-no-wrap"
            hide-default-footer
          >
            <template #item.product.name="{ item }">
              <div class="d-flex align-center gap-x-3">
                <VAvatar
                  v-if="item.product?.photo_url"
                  size="32"
                  variant="tonal"
                  rounded
                  :image="item.product.photo_url"
                />
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-medium">
                    {{
                      item.product?.name || item.name || "Producto sin nombre"
                    }}
                  </span>
                  <span class="text-caption text-disabled">
                    {{ item.product?.active_ingredient || "N/A" }}
                  </span>
                </div>
              </div>
            </template>

            <template #item.lot_number="{ item }">
              <span class="font-weight-medium">{{
                item.lot_number || "N/A"
              }}</span>
            </template>

            <template #item.cost_per_unit="{ item }">
              <span class="font-weight-medium">
                {{ formatCurrency(item.cost_per_unit || item.cost || 0) }}
              </span>
            </template>

            <template #item.actions="{ item }">
              <VTooltip text="Remover del reajuste">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn
                    v-bind="tooltipProps"
                    size="small"
                    color="error"
                    variant="text"
                    @click="removeProduct(item.id)"
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
        <VSpacer />
        <VBtn variant="outlined" @click="isVisible = false"> Cancelar </VBtn>
        <VBtn color="primary" :disabled="!isFormValid" @click="handleSubmit">
          Aplicar Reajuste
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
