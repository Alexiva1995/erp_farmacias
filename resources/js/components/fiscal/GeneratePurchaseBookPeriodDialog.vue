<script setup>
import { reactive, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const yearsList = [2024, 2025, 2026, 2027, 2028];

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
            <VIcon icon="tabler-calendar-plus" size="20" />
          </VAvatar>
          <span class="text-h6 font-weight-bold">Generar Período Fiscal</span>
        </div>
        <VBtn icon variant="text" size="small" :disabled="isGenerating" @click="handleClose">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <p class="text-caption text-medium-emphasis mb-4">
          Selecciona el año, mes y quincena para inicializar y sincronizar automáticamente las facturas y comprobantes de retención del período seleccionado.
        </p>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.year"
                :items="yearsList"
                label="Año Fiscal"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-calendar"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.month"
                :items="monthsList"
                item-title="title"
                item-value="value"
                label="Mes"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-calendar-month"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="generateForm.fortnight"
                :items="[
                  { title: '1era Quincena (Día 01 al 15)', value: 1 },
                  { title: '2da Quincena (Día 16 al fin de mes)', value: 2 },
                ]"
                item-title="title"
                item-value="value"
                label="Quincena (si aplica Sujeto Pasivo Especial)"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-clock"
              />
            </VCol>
          </VRow>

          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="text" color="secondary" :disabled="isGenerating" @click="handleClose">
              Cancelar
            </VBtn>
            <VBtn
              color="primary"
              type="submit"
              prepend-icon="tabler-calendar-check"
              :loading="isGenerating"
              :disabled="isGenerating"
            >
              Generar Período
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
