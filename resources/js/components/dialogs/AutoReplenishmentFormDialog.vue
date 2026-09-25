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
  { title: "Stockout-Adjusted ROP PLUS (Inteligencia de Demanda)", value: "stockout_adjusted_rop_plus" },
  { title: "Stockout-Adjusted ROP (Predeterminado)", value: "stockout_adjusted_rop" },
  { title: "Ponderado (Óptimo ROP)",                 value: "weighted"              },
  { title: "Promedio",                              value: "average"               },
  { title: "Ventas",                                value: "sales"                 },
  { title: "Combinado",                             value: "combinado"             },
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
    max-width="720px"
    persistent
    scrollable
  >
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface" :loading="dialogLoading">
      <!-- Header Compacto Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-4 py-3 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="36" class="me-3 elevation-1">
            <VIcon icon="tabler-settings-automation" color="primary" size="20" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-subtitle-1 font-weight-bold leading-tight mb-0 text-white">
              {{ configForm.id ? 'Editar Regla de Reposición' : 'Nueva Regla de Reposición' }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-medium uppercase letter-spacing-1">
              Parámetros de Compras Automáticas
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="text" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <!-- Contenido Scrolleable sin Overcrowding -->
      <VCardText class="pa-4 pa-sm-5 bg-surface" style="max-height: 75vh; overflow-y: auto;">
        <VRow dense>
          <!-- 1. Nombre descriptivo -->
          <VCol cols="12" class="mb-1">
            <VTextField
              v-model="configForm.name"
              label="Nombre descriptivo de la regla"
              density="compact"
              required
              placeholder="Ej: Reposición Semanal Óptima"
              :error-messages="formErrors.name"
            />
          </VCol>

          <!-- 2. Método de Análisis (Ancho completo para no truncar nombres largos) -->
          <VCol cols="12" class="mb-1">
            <VSelect
              v-model="configForm.tipo_filtracion"
              :items="tipoFiltracionOpciones"
              label="Método de Análisis (Algoritmo)"
              density="compact"
              :error-messages="formErrors.tipo_filtracion"
            />
          </VCol>

          <!-- 3. Parámetros Numéricos y Frecuencia (Grid 3 columnas) -->
          <VCol cols="12" sm="4" class="mb-1">
            <VSelect
              v-model="configForm.lapso_de_tiempo"
              :items="lapsoDeTiempoOpciones"
              label="Periodo de Ventas"
              density="compact"
              :error-messages="formErrors.lapso_de_tiempo"
            />
          </VCol>

          <VCol cols="12" sm="4" class="mb-1">
            <VTextField
              v-model.number="configForm.min_solicitar"
              type="number"
              label="Cantidad mín. a pedir"
              density="compact"
              min="0"
              step="any"
              :error-messages="formErrors.min_solicitar"
            />
          </VCol>

          <VCol cols="12" sm="4" class="mb-1">
            <VSelect
              v-model="configForm.schedule_expression"
              :items="scheduleOpciones"
              label="Frecuencia"
              density="compact"
              :error-messages="formErrors.schedule_expression"
            />
          </VCol>

          <!-- 4. Filtros de Alcance (Proveedor y Grupos) -->
          <VCol cols="12" sm="6" class="mb-1">
            <VAutocomplete
              v-model="configForm.supplier_id"
              :items="suppliers"
              label="Proveedor preferido (Opcional)"
              item-title="name"
              item-value="id"
              density="compact"
              clearable
              placeholder="Todos los proveedores"
              :error-messages="formErrors.supplier_id"
            />
          </VCol>

          <VCol cols="12" sm="6" class="mb-1">
            <VAutocomplete
              v-model="configForm.group_ids"
              :items="groups"
              label="Limitar a Grupos (Opcional)"
              item-title="name"
              item-value="id"
              density="compact"
              multiple
              chips
              closable-chips
              placeholder="Todos los grupos"
              :error-messages="formErrors.group_ids"
            />
          </VCol>

          <!-- 5. Umbral de Incremento de Precio -->
          <VCol cols="12" class="mb-2">
            <VTextField
              v-model.number="configForm.max_price_increase_percentage"
              type="number"
              label="Aumento máx. permitido de precio (%) (Opcional)"
              placeholder="Ej: 15 (descarta sobrecostos mayores a +15%)"
              suffix="%"
              density="compact"
              min="0"
              max="500"
              step="any"
              clearable
              hint="Descarta cotizaciones que superen este % de sobrecosto frente al costo base del producto"
              persistent-hint
              :error-messages="formErrors.max_price_increase_percentage"
            />
          </VCol>

          <!-- 6. Tarjeta Contenedora de Exclusiones y Filtros Comerciales -->
          <VCol cols="12">
            <VCard variant="outlined" class="pa-3 rounded-lg bg-light border">
              <div class="d-flex align-center gap-1.5 text-caption font-weight-bold text-high-emphasis uppercase mb-2">
                <VIcon icon="tabler-adjustments-horizontal" size="16" color="primary" />
                Filtros y Exclusiones Comerciales
              </div>

              <VRow dense>
                <VCol cols="12" sm="6" class="py-1">
                  <VSwitch
                    v-model="configForm.con_descuento"
                    label="Usar precios con descuento"
                    density="compact"
                    hide-details
                    color="primary"
                    :error-messages="formErrors.con_descuento"
                  />
                </VCol>

                <VCol cols="12" sm="6" class="py-1">
                  <VSwitch
                    v-model="configForm.exclude_colombian"
                    label="Excluir Plan Colombia"
                    density="compact"
                    hide-details
                    color="warning"
                    :error-messages="formErrors.exclude_colombian"
                  />
                </VCol>

                <VCol cols="12" sm="6" class="py-1">
                  <VSwitch
                    v-model="configForm.exclude_novaventa"
                    label="Excluir Novaventa"
                    density="compact"
                    hide-details
                    color="warning"
                    :error-messages="formErrors.exclude_novaventa"
                  />
                </VCol>

                <VCol cols="12" sm="6" class="py-1">
                  <VSwitch
                    v-model="configForm.include_ignored"
                    label="Incluir ignorados manualmente"
                    density="compact"
                    hide-details
                    color="info"
                    :error-messages="formErrors.include_ignored"
                  />
                </VCol>
              </VRow>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Footer Fijo con Acciones al 50% según Estilo del Sistema -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              block
              height="44"
              class="font-weight-bold rounded-lg text-button"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="44"
              class="font-weight-bold rounded-lg shadow-primary text-button"
              :loading="dialogLoading"
              :disabled="dialogLoading"
              @click="handleSave"
            >
              <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
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
