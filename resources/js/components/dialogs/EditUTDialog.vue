<script setup>
import { ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  currentValue: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "save"]);

const { mobile } = useDisplay();

const tempUT = ref(props.currentValue);
const effectiveDate = ref(new Date().toISOString().split("T")[0]);
const notes = ref("");

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      tempUT.value = props.currentValue;
      effectiveDate.value = new Date().toISOString().split("T")[0];
      notes.value = "";
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
};

const saveUT = () => {
  if (!tempUT.value || tempUT.value <= 0) return;
  
  const data = {
    value: tempUT.value,
    effective_date: effectiveDate.value,
    notes: notes.value || null,
  };
  emit("save", data);
  closeDialog();
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="500"
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Toolbar Premium (Móvil) -->
      <VToolbar v-if="mobile" color="primary" dense flat>
        <VBtn icon @click="closeDialog">
          <VIcon icon="tabler-x" />
        </VBtn>
        <VToolbarTitle class="text-sm font-weight-black uppercase">Unidad Tributaria</VToolbarTitle>
        <VSpacer />
        <VBtn
          color="white"
          variant="text"
          class="font-weight-black text-xs"
          @click="saveUT"
          :disabled="!tempUT || tempUT <= 0"
        >
          GUARDAR
        </VBtn>
      </VToolbar>

      <!-- Cabecera Premium (Escritorio) -->
      <VCardTitle v-else class="pa-6 pb-0 d-flex align-center">
        <VAvatar color="primary" variant="tonal" size="44" class="me-4 rounded-lg">
          <VIcon icon="tabler-calculator-check" size="24" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-lg font-weight-black uppercase leading-none mb-1">Unidad Tributaria</span>
          <span class="text-xs text-disabled font-weight-medium">Configuración del valor fiscal (UT)</span>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="secondary"
          size="32"
          class="rounded-lg"
          @click="closeDialog"
        />
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
          <!-- Valor UT -->
          <VCol cols="12">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Valor de la UT (Bs.)</span>
            <VTextField
              v-model.number="tempUT"
              type="number"
              step="0.01"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              color="primary"
              class="premium-input pt-0"
              placeholder="0.00"
              prefix="Bs."
              :rules="[(v) => v > 0 || 'El valor debe ser mayor a 0']"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-currency-dollar" size="18" color="disabled" class="me-2" />
              </template>
            </VTextField>
          </VCol>

          <!-- Fecha -->
          <VCol cols="12">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha de Vigencia</span>
            <AppDateTimePicker
              v-model="effectiveDate"
              placeholder="Desde..."
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              color="primary"
              class="premium-input pt-0"
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" size="18" color="disabled" class="me-2" />
              </template>
            </AppDateTimePicker>
          </VCol>

          <!-- Notas -->
          <VCol cols="12">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Observaciones (Opcional)</span>
            <VTextarea
              v-model="notes"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              color="primary"
              class="premium-input pt-0"
              placeholder="Detalles sobre el ajuste..."
              rows="3"
              counter="500"
              :rules="[(v) => !v || v.length <= 500 || 'Máximo 500 caracteres']"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-note" size="18" color="disabled" class="me-2" />
              </template>
            </VTextarea>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botones (Escritorio) -->
      <VCardActions v-if="!mobile" class="pa-6 pt-0 d-flex gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          block
          class="rounded-lg font-weight-black text-xs flex-grow-1"
          @click="closeDialog"
        >
          CANCELAR
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          block
          class="rounded-lg font-weight-black text-xs flex-grow-1 shadow-sm"
          @click="saveUT"
          :disabled="!tempUT || tempUT <= 0"
        >
          GUARDAR AJUSTE
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
