<script setup>
import { ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  startDate: {
    type: String,
    default: "",
  },
  endDate: {
    type: String,
    default: "",
  },
  defaultFiscalDate: {
    type: String,
    default: "",
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const { mobile } = useDisplay();

const retentionDate = ref(props.defaultFiscalDate);

watch(
  () => [props.modelValue, props.defaultFiscalDate],
  ([newVal, newDefaultDate]) => {
    if (newVal) {
      retentionDate.value = newDefaultDate || new Date().toISOString().split("T")[0];
    }
  },
);

const closeDialog = () => {
  if (props.loading) return;
  emit("update:modelValue", false);
};

const handleConfirm = () => {
  if (!retentionDate.value || props.loading) return;
  emit("confirm", retentionDate.value);
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="520"
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
              icon="tabler-wand"
              size="24"
              color="warning"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Generar Todas las Retenciones
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Procesamiento Masivo por Rango
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
            :disabled="props.loading"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <!-- Contenido Premium -->
      <VCardText class="pa-4 pa-sm-6 bg-light">
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4 rounded-lg border-0"
          density="comfortable"
        >
          <template #prepend>
            <VIcon icon="tabler-info-circle" color="info" class="me-2" />
          </template>
          <span class="text-xs font-weight-medium">
            Se procesarán todas las facturas pendientes registradas entre <strong>{{ props.startDate }}</strong> y <strong>{{ props.endDate }}</strong>. Se creará un comprobante de retención por cada proveedor.
          </span>
        </VAlert>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-lg elevation-1 border"
        >
          <VRow>
            <!-- Selector Editable de Fecha Fiscal de Emisión -->
            <VCol cols="12">
              <AppDateTimePicker
                v-model="retentionDate"
                label="Fecha Fiscal de Emisión (Modificable)"
                placeholder="Selecciona la fecha fiscal..."
                prepend-inner-icon="tabler-calendar"
                density="comfortable"
                :config="{
                  altInput: true,
                  altFormat: 'd/m/Y',
                  dateFormat: 'Y-m-d',
                }"
              />
              <span class="text-super-xs text-disabled d-block mt-1">
                Puedes modificar esta fecha si deseas que los comprobantes salgan con un día distinto al fin de quincena por defecto.
              </span>
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
              height="46"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              :disabled="props.loading"
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
              color="warning"
              variant="flat"
              height="46"
              block
              class="font-weight-black rounded-lg shadow-warning text-button uppercase"
              :loading="props.loading"
              :disabled="props.loading || !retentionDate"
              @click="handleConfirm"
            >
              <VIcon
                start
                icon="tabler-wand"
                size="18"
                class="me-2"
              />
              Generar Retenciones
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.shadow-warning {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-warning), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.leading-none {
  line-height: 1 !important;
}
</style>
