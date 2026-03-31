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
  },
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
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-calculator-check"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Unidad Tributaria
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Configuración del Valor Fiscal
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <!-- Contenido Premium -->
      <VCardText class="pa-4 pa-sm-6 bg-light">
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span
            class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1"
            >Ajuste de Valor</span
          >
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-lg elevation-1 border"
        >
          <VRow>
            <!-- Valor UT -->
            <VCol cols="12">
              <AppTextField
                v-model.number="tempUT"
                label="Valor de la UT (Bs.)"
                placeholder="0.00"
                prefix="Bs."
                type="number"
                step="0.01"
                prepend-inner-icon="tabler-currency-dollar"
                density="comfortable"
                :rules="[(v) => v > 0 || 'El valor debe ser mayor a 0']"
              />
            </VCol>

            <!-- Fecha -->
            <VCol cols="12">
              <AppDateTimePicker
                v-model="effectiveDate"
                label="Fecha de Vigencia"
                placeholder="Desde..."
                prepend-inner-icon="tabler-calendar-event"
                density="comfortable"
                :config="{
                  altInput: true,
                  altFormat: 'd/m/Y',
                  dateFormat: 'Y-m-d',
                }"
              />
            </VCol>

            <!-- Notas -->
            <VCol cols="12">
              <AppTextarea
                v-model="notes"
                label="Observaciones (Opcional)"
                placeholder="Detalles sobre el ajuste..."
                prepend-inner-icon="tabler-note"
                rows="3"
                counter="500"
                density="comfortable"
                :rules="[(v) => !v || v.length <= 500 || 'Máximo 500 caracteres']"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="saveUT"
              :disabled="!tempUT || tempUT <= 0"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
                class="me-2"
              />
              Guardar Ajuste
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.leading-none {
  line-height: 1 !important;
}
</style>
