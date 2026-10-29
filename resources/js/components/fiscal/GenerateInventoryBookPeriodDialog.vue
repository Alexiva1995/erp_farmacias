<script setup>
import { reactive } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const currentYear = new Date().getFullYear();
const yearsList = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1];

const monthsList = [
  { value: 1, title: "Enero" },
  { value: 2, title: "Febrero" },
  { value: 3, title: "Marzo" },
  { value: 4, title: "Abril" },
  { value: 5, title: "Mayo" },
  { value: 6, title: "Junio" },
  { value: 7, title: "Julio" },
  { value: 8, title: "Agosto" },
  { value: 9, title: "Septiembre" },
  { value: 10, title: "Octubre" },
  { value: 11, title: "Noviembre" },
  { value: 12, title: "Diciembre" },
];

const generateForm = reactive({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  fortnight: new Date().getDate() <= 15 ? 1 : 2,
});

const handleClose = () => {
  emit("update:modelValue", false);
};

const handleSubmit = () => {
  emit("submit", { ...generateForm });
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="36">
            <VIcon icon="tabler-box" size="20" />
          </VAvatar>
          <span class="text-h6 font-weight-bold">Generar Período de Inventario</span>
        </div>
        <VBtn icon variant="text" size="small" :disabled="isGenerating" @click="handleClose">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <p class="text-caption text-medium-emphasis mb-4">
          Selecciona el período para calcular y consolidar el Libro de Inventario (Art. 177 LISR). Se valorará con el último costo en Bs del período y se sincronizarán compras, ventas y retiros.
        </p>

        <VForm @submit.prevent="handleSubmit">
          <VRow dense>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.year"
                :items="yearsList"
                label="Año Fiscal"
                variant="outlined"
                density="compact"
                hide-details
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.month"
                :items="monthsList"
                item-value="value"
                item-title="title"
                label="Mes"
                variant="outlined"
                density="compact"
                hide-details
              />
            </VCol>

            <VCol cols="12">
              <VRadioGroup
                v-model="generateForm.fortnight"
                label="Quincena (Sujeto Pasivo Especial)"
                inline
                class="mt-2"
                hide-details
              >
                <VRadio :value="1" label="1ra Quincena (01 al 15)" color="primary" />
                <VRadio :value="2" label="2da Quincena (16 al Fin de Mes)" color="primary" />
              </VRadioGroup>
            </VCol>
          </VRow>

          <div class="d-flex justify-end gap-2 mt-5">
            <VBtn variant="outlined" color="secondary" :disabled="isGenerating" @click="handleClose">
              Cancelar
            </VBtn>
            <VBtn color="primary" type="submit" :loading="isGenerating">
              Generar y Calcular
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
