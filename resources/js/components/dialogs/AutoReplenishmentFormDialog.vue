<script setup lang="js">
import { computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  configForm: {
    type: Object,
    required: true,
  },
  dialogLoading: {
    type: Boolean,
    default: false,
  },
  formErrors: {
    type: Object,
    default: () => ({}),
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  groups: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "save"]);

const visible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const tipoFiltracionOpciones = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Días", value: "7 days" },
  { title: "15 Días", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const scheduleOpciones = [
  { title: "Todos los días a las 6:00 AM", value: "0 6 * * *" },
  { title: "Todos los días a las 8:00 AM", value: "0 8 * * *" },
  { title: "Cada Lunes a las 6:00 AM", value: "0 6 * * 1" },
  { title: "Cada 12 Horas", value: "0 */12 * * *" },
  { title: "Cada Hora", value: "0 * * * *" },
];

function close() {
  visible.value = false;
}

function handleSave() {
  emit("save");
}
</script>

<template>
  <VDialog
    v-model="visible"
    max-width="680px"
    persistent
    scrollable
  >
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface" :loading="dialogLoading">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-settings-automation" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              {{ configForm.id ? 'Editar Regla' : 'Nueva Regla' }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Reposición Automática
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light" style="overflow-y: auto;">
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="configForm.name"
              label="Nombre descriptivo de la regla"
              required
              placeholder="Ej: Reposición Diaria Urgentes"
              :error-messages="formErrors.name"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              v-model="configForm.tipo_filtracion"
              :items="tipoFiltracionOpciones"
              label="Método de Análisis"
              :error-messages="formErrors.tipo_filtracion"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              v-model="configForm.lapso_de_tiempo"
              :items="lapsoDeTiempoOpciones"
              label="Periodo de Ventas"
              :error-messages="formErrors.lapso_de_tiempo"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model.number="configForm.min_solicitar"
              type="number"
              label="Cantidad mínima a solicitar"
              min="0"
              step="any"
              :error-messages="formErrors.min_solicitar"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              v-model="configForm.schedule_expression"
              :items="scheduleOpciones"
              label="Frecuencia de Ejecución"
              hint="Expresión cron programada para la automatización"
              persistent-hint
              :error-messages="formErrors.schedule_expression"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              v-model="configForm.supplier_id"
              :items="suppliers"
              label="Proveedor preferido (Opcional)"
              item-title="name"
              item-value="id"
              clearable
              placeholder="Todos los proveedores"
              :error-messages="formErrors.supplier_id"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              v-model="configForm.group_ids"
              :items="groups"
              label="Limitar a Grupos de Producto (Opcional)"
              item-title="name"
              item-value="id"
              multiple
              chips
              closable-chips
              placeholder="Todos los grupos"
              :error-messages="formErrors.group_ids"
            />
          </VCol>

          <VCol cols="12">
            <VSwitch
              v-model="configForm.con_descuento"
              label="Usar precios con descuento del proveedor"
              color="primary"
              :error-messages="formErrors.con_descuento"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSwitch
              v-model="configForm.exclude_colombian"
              label="Excluir productos Plan Colombia"
              color="warning"
              :error-messages="formErrors.exclude_colombian"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSwitch
              v-model="configForm.exclude_novaventa"
              label="Excluir productos Novaventa"
              color="warning"
              :error-messages="formErrors.exclude_novaventa"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="dialogLoading"
              :disabled="dialogLoading"
              @click="handleSave"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Regla
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
