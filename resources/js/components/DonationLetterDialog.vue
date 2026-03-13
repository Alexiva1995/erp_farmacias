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
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: false,
    value: (item) => item.product?.laboratory?.name || "—"
  },
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
    max-width="900px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
    :fullscreen="$vuetify.display.xs"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-6">
        <span class="text-h5 font-weight-bold">Generar Carta de Donación</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-6" style="overflow-y: auto;">
        <div class="mb-6">
          <p class="text-h6 font-weight-medium mb-1">Información de la Institución</p>
          <p class="text-body-2 text-medium-emphasis mb-4">Nombre de la institución que recibirá la donación</p>
          <AppTextField
            v-model="institutionName"
            label="Nombre de la Institución"
            variant="outlined"
            density="comfortable"
          />
        </div>

        <VDivider class="my-8" />

        <div class="mb-4">
          <p class="text-h6 font-weight-medium mb-1">Productos a Donar</p>
          <p class="text-body-2 text-medium-emphasis">Lista de productos seleccionados para la donación</p>
        </div>

        <VDataTable
          :headers="donationHeaders"
          :items="donationProducts"
          density="comfortable"
          class="rounded-lg"
          no-data-text="No hay productos seleccionados para donar."
        >
          <template #item.product_name="{ item }">
            <span class="font-weight-medium">{{ item.product_name?.toUpperCase() || "" }}</span>
          </template>

          <template #item.laboratory_name="{ item }">
            <span>{{ item.product?.laboratory?.name || "—" }}</span>
          </template>

          <template #item.actions="{ item }">
            <VTooltip text="Descartar de la donación" location="top">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="error"
                  @click="discardProduct(item)"
                >
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
            </VTooltip>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
          size="large"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleGenerate"
          class="flex-grow-1 w-0"
          size="large"
        >
          Generar Carta
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
