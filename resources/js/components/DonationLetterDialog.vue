<script setup>
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  initialProducts: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "generate"]);

const institutionName = ref("");
const donationProducts = ref([]);

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      donationProducts.value = JSON.parse(
        JSON.stringify(props.initialProducts)
      );

      institutionName.value = "";
    }
  }
);

const donationHeaders = [
  { title: "Producto", key: "product_name" },
  { title: "Unds.", key: "expired_quantity", align: "end" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const discardProduct = (productToDiscard) => {
  donationProducts.value = donationProducts.value.filter(
    (p) => p.id !== productToDiscard.id
  );
  toast.success(
    `"${productToDiscard.product_name}" descartado de la donación.`
  );
};

const handleGenerate = () => {
  if (!institutionName.value.trim()) {
    toast.warning("Por favor, ingrese el nombre de la institución.");
    return;
  }
  if (donationProducts.value.length === 0) {
    toast.warning("No se puede generar una donación sin productos.");
    return;
  }
  emit("generate", {
    institution: institutionName.value,
    products: donationProducts.value,
  });
};

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard :loading="props.loading">
      <VCardTitle class="d-flex align-center">
        <span>Generar Carta de Donativo</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="py-6">
        <AppTextField
          v-model="institutionName"
          label="Nombre de la Institución"
          class="mb-6"
        />
        <p class="font-weight-medium mb-2">Productos a donar:</p>
        <VDataTable
          :headers="donationHeaders"
          :items="donationProducts"
          density="compact"
          class="mb-4"
          height="300px"
          fixed-header
          no-data-text="No hay productos seleccionados para donar."
        >
          <template #item.actions="{ item }">
            <VTooltip text="Descartar de la donación">
              <template #activator="{ props: tooltipProps }">
                <IconBtn v-bind="tooltipProps" @click="discardProduct(item)">
                  <VIcon icon="tabler-trash" color="error" />
                </IconBtn>
              </template>
            </VTooltip>
          </template>
          <template #bottom></template>
        </VDataTable>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-fill me-2"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleGenerate"
          class="flex-fill ms-2"
        >
          Generar Carta
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
