<script setup lang="js">
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: "Gasto Fijo" },
  templateData: { type: Object, default: () => null },
  categories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save"]);

const currencies = ["USD", "BS", "COP"];

const bsMethods = ["Efectivo", "Tarjeta", "Pago Móvil", "Transferencia", "Cambista"];
const usdMethods = ["Efectivo", "Binance", "PayPal", "Cambista"];
const copMethods = ["Efectivo", "Transferencia", "Cambista"];

const presetFrequencies = [
  { title: "Mensual (cada 1 mes)", unit: "month", count: 1 },
  { title: "Quincenal (cada 2 semanas)", unit: "week", count: 2 },
  { title: "Semanal (cada 1 semana)", unit: "week", count: 1 },
  { title: "Bimestral (cada 2 meses)", unit: "month", count: 2 },
  { title: "Trimestral (cada 3 meses)", unit: "month", count: 3 },
  { title: "Semestral (cada 6 meses)", unit: "month", count: 6 },
  { title: "Anual (cada 1 año)", unit: "year", count: 1 },
  { title: "Personalizado...", unit: "custom", count: 1 },
];

const selectedPreset = ref("month_1");

const form = reactive({
  id: null,
  name: "",
  category_id: null,
  amount: "",
  currency: "USD",
  is_amount_variable: false,
  interval_unit: "month",
  interval_count: 1,
  start_date: new Date().toISOString().substring(0, 10),
  end_date: null,
  reminder_days_before: 3,
  default_count: "Efectivo",
  notes: "",
  is_active: true,
});

const isCustomFrequency = computed(() => selectedPreset.value === "custom");

const availablePaymentMethods = computed(() => {
  if (form.currency === "BS") return bsMethods;
  if (form.currency === "COP") return copMethods;
  return usdMethods;
});

const currencySymbol = computed(() => {
  const map = { BS: "Bs.", USD: "$", COP: "COP" };
  return map[form.currency] || "$";
});

function syncFromPreset(presetKey) {
  if (presetKey === "custom") return;
  const [unit, count] = presetKey.split("_");
  form.interval_unit = unit;
  form.interval_count = Number(count) || 1;
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.templateData) {
        form.id = props.templateData.id ?? null;
        form.name = props.templateData.name ?? "";
        form.category_id = props.templateData.category_id ?? null;
        form.amount = props.templateData.amount ?? "";
        form.currency = props.templateData.currency ?? "USD";
        form.is_amount_variable = Boolean(props.templateData.is_amount_variable);
        form.interval_unit = props.templateData.interval_unit ?? "month";
        form.interval_count = props.templateData.interval_count ?? 1;
        form.start_date = props.templateData.start_date ?? new Date().toISOString().substring(0, 10);
        form.end_date = props.templateData.end_date ?? null;
        form.reminder_days_before = props.templateData.reminder_days_before ?? 3;
        form.default_count = props.templateData.default_count ?? "Efectivo";
        form.notes = props.templateData.notes ?? "";
        form.is_active = props.templateData.is_active ?? true;

        const foundPreset = presetFrequencies.find(
          (p) => p.unit === form.interval_unit && p.count === form.interval_count
        );
        selectedPreset.value = foundPreset ? `${foundPreset.unit}_${foundPreset.count}` : "custom";
      } else {
        form.id = null;
        form.name = "";
        form.category_id = null;
        form.amount = "";
        form.currency = "USD";
        form.is_amount_variable = false;
        form.interval_unit = "month";
        form.interval_count = 1;
        form.start_date = new Date().toISOString().substring(0, 10);
        form.end_date = null;
        form.reminder_days_before = 3;
        form.default_count = "Efectivo";
        form.notes = "";
        form.is_active = true;
        selectedPreset.value = "month_1";
      }
    }
  }
);

watch(selectedPreset, (newPreset) => {
  syncFromPreset(newPreset);
});

watch(
  () => form.currency,
  () => {
    if (!availablePaymentMethods.value.includes(form.default_count)) {
      form.default_count = availablePaymentMethods.value[0];
    }
  }
);

function close() {
  emit("update:modelValue", false);
}

function submit() {
  if (!form.name.trim() || !form.category_id || !form.amount || !form.start_date) {
    return;
  }

  emit("save", {
    ...form,
    amount: Number(form.amount),
    interval_count: Number(form.interval_count),
    reminder_days_before: Number(form.reminder_days_before),
  });
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="800px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    transition="dialog-bottom-transition"
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl bg-surface">
      <!-- Header del Sistema -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-calendar-repeat"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ title }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Módulo de Gestión de Gastos Fijos
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
            @click="close"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-light">
        <!-- Sección 1: Información General -->
        <div class="d-flex align-center gap-2 mb-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-bold text-high-emphasis uppercase letter-spacing-1">
            Información General
          </span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-lg border shadow-sm mb-3"
        >
          <VRow dense>
            <!-- Nombre (8 cols) + Categoría (4 cols) -->
            <VCol cols="12" md="8">
              <VTextField
                v-model="form.name"
                label="Nombre / Concepto del Gasto Fijo"
                placeholder="Ej: Alquiler Local Central, Servicio Eléctrico, Internet..."
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-file-description"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VSelect
                v-model="form.category_id"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Categoría"
                placeholder="Seleccionar categoría"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-category"
                hide-details="auto"
              />
            </VCol>

            <!-- Moneda + Monto + Método de Pago -->
            <VCol cols="12" sm="4">
              <VSelect
                v-model="form.currency"
                :items="currencies"
                label="Moneda"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-coin"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="form.amount"
                type="number"
                min="0"
                step="0.01"
                label="Monto Base / Estimado"
                placeholder="0.00"
                :prefix="currencySymbol"
                variant="outlined"
                density="compact"
                class="rounded-lg font-weight-bold"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" sm="4">
              <VSelect
                v-model="form.default_count"
                :items="availablePaymentMethods"
                label="Método de Pago Sugerido"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-wallet"
                hide-details="auto"
              />
            </VCol>

            <!-- Monto Variable -->
            <VCol cols="12">
              <div class="pa-2 px-3 mt-1 rounded-lg bg-grey-50 border d-flex align-center justify-space-between flex-wrap gap-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-adjustments-dollar" size="20" color="primary" />
                  <div>
                    <div class="text-xs font-weight-bold text-high-emphasis">
                      Monto estimado o variable
                    </div>
                    <div class="text-super-xs text-medium-emphasis">
                      Actívalo en servicios como electricidad, agua o gas donde el monto exacto se confirma al pagar
                    </div>
                  </div>
                </div>
                <VSwitch
                  v-model="form.is_amount_variable"
                  color="primary"
                  density="compact"
                  hide-details
                  inset
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Sección 2: Programación y Recordatorios -->
        <div class="d-flex align-center gap-2 mb-2">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-bold text-high-emphasis uppercase letter-spacing-1">
            Frecuencia y Calendario de Avisos
          </span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-lg border shadow-sm mb-3"
        >
          <VRow dense>
            <!-- Frecuencia + Días de anticipación -->
            <VCol cols="12" sm="6">
              <VSelect
                v-model="selectedPreset"
                :items="presetFrequencies.map(p => ({ title: p.title, value: p.unit === 'custom' ? 'custom' : `${p.unit}_${p.count}` }))"
                item-title="title"
                item-value="value"
                label="Frecuencia de Pago"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-repeat"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.reminder_days_before"
                type="number"
                min="0"
                max="90"
                label="Anticipación del Aviso"
                suffix="días antes"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-bell-ringing"
                hide-details="auto"
              />
            </VCol>

            <!-- Intervalo personalizado -->
            <template v-if="isCustomFrequency">
              <VCol cols="12" sm="6">
                <VTextField
                  v-model.number="form.interval_count"
                  type="number"
                  min="1"
                  max="365"
                  label="Repetir cada"
                  variant="outlined"
                  density="compact"
                  class="rounded-lg"
                  prepend-inner-icon="tabler-hash"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="form.interval_unit"
                  :items="[
                    { title: 'Días', value: 'day' },
                    { title: 'Semanas', value: 'week' },
                    { title: 'Meses', value: 'month' },
                    { title: 'Años', value: 'year' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Unidad de Tiempo"
                  variant="outlined"
                  density="compact"
                  class="rounded-lg"
                  prepend-inner-icon="tabler-clock"
                  hide-details="auto"
                />
              </VCol>
            </template>

            <!-- Fecha Primer Pago + Fecha Fin -->
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.start_date"
                placeholder="Fecha del Primer Pago"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-calendar"
                :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.end_date"
                placeholder="Fecha Límite / Fin (Opcional)"
                clearable
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-calendar-off"
                :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </VCard>

        <!-- Sección 3: Observaciones -->
        <div class="d-flex align-center gap-2 mb-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-bold text-high-emphasis uppercase letter-spacing-1">
            Notas y Referencias
          </span>
        </div>

        <VCard
          variant="flat"
          class="pa-3 bg-white rounded-lg border shadow-sm"
        >
          <VRow dense>
            <VCol cols="12">
              <VTextarea
                v-model="form.notes"
                label="Notas u Observaciones (Opcional)"
                placeholder="Número de contrato, cuenta destino, titular o instrucciones de pago..."
                rows="2"
                variant="outlined"
                density="compact"
                class="rounded-lg"
                prepend-inner-icon="tabler-notes"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 bg-white border-t px-6">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading"
              :disabled="loading || !form.name.trim() || !form.category_id || !form.amount"
              @click="submit"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Gasto Fijo
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

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
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

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
