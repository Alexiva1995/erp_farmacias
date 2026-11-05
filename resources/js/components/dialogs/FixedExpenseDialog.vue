<script setup lang="js">
import DialogCloseBtn from "@core/components/DialogCloseBtn.vue";
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

const paymentMethods = [
  "Efectivo",
  "Tarjeta",
  "Pago Móvil",
  "Transferencia",
  "Binance",
  "PayPal",
  "Cambista",
];

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
    max-width="700"
    persistent
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard class="rounded-lg">
      <!-- Encabezado con Avatar e Icono de Cierre -->
      <VCardItem class="pb-3 border-b">
        <div class="d-flex align-center justify-space-between w-100">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="38">
              <VIcon icon="tabler-calendar-repeat" size="20" />
            </VAvatar>
            <div>
              <VCardTitle class="text-h6 font-weight-bold">
                {{ title }}
              </VCardTitle>
              <VCardSubtitle class="text-caption text-medium-emphasis">
                Compromiso recurrente y programación de recordatorios
              </VCardSubtitle>
            </div>
          </div>
          <DialogCloseBtn @click="close" />
        </div>
      </VCardItem>

      <VCardText class="pa-5">
        <VRow dense class="gy-3">
          <!-- Nombre del compromiso -->
          <VCol cols="12" md="7">
            <VTextField
              v-model="form.name"
              label="Nombre *"
              placeholder="Ej: Alquiler Local Central, Internet Fibra..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-file-description"
              hide-details="auto"
            />
          </VCol>

          <!-- Categoría de gasto -->
          <VCol cols="12" md="5">
            <VSelect
              v-model="form.category_id"
              :items="categories"
              item-title="name"
              item-value="id"
              label="Categoría *"
              placeholder="Seleccionar"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-category"
              hide-details="auto"
            />
          </VCol>

          <!-- Monto, Moneda y Método -->
          <VCol cols="12" sm="5">
            <VTextField
              v-model="form.amount"
              type="number"
              min="0"
              step="0.01"
              label="Monto base *"
              placeholder="0.00"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-currency-dollar"
              hide-details="auto"
            />
          </VCol>

          <VCol cols="6" sm="3">
            <VSelect
              v-model="form.currency"
              :items="currencies"
              label="Moneda *"
              density="compact"
              variant="outlined"
              hide-details="auto"
            />
          </VCol>

          <VCol cols="6" sm="4">
            <VSelect
              v-model="form.default_count"
              :items="paymentMethods"
              label="Método sugerido"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-wallet"
              hide-details="auto"
            />
          </VCol>

          <!-- Monto variable (diseño en tarjeta sutil) -->
          <VCol cols="12">
            <div class="d-flex align-center justify-space-between pa-3 rounded bg-var-theme-background border">
              <div class="d-flex flex-column pe-3">
                <span class="text-body-2 font-weight-medium text-high-emphasis">Monto variable</span>
                <span class="text-caption text-medium-emphasis">
                  El importe exacto se confirma al registrar cada pago (ej. servicios de luz, agua o gas).
                </span>
              </div>
              <VSwitch
                v-model="form.is_amount_variable"
                color="primary"
                density="compact"
                hide-details
              />
            </div>
          </VCol>

          <!-- Frecuencia y Aviso de anticipación -->
          <VCol cols="12" sm="7">
            <VSelect
              v-model="selectedPreset"
              :items="presetFrequencies.map(p => ({ title: p.title, value: p.unit === 'custom' ? 'custom' : `${p.unit}_${p.count}` }))"
              item-title="title"
              item-value="value"
              label="Frecuencia de pago *"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-repeat"
              hide-details="auto"
            />
          </VCol>

          <VCol cols="12" sm="5">
            <VTextField
              v-model="form.reminder_days_before"
              type="number"
              min="0"
              max="90"
              label="Anticipación"
              suffix="días antes"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-bell"
              hide-details="auto"
            />
          </VCol>

          <!-- Frecuencia personalizada (si aplica) -->
          <template v-if="isCustomFrequency">
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.interval_count"
                type="number"
                min="1"
                max="365"
                label="Repetir cada *"
                density="compact"
                variant="outlined"
                prepend-inner-icon="tabler-clock"
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
                label="Unidad de tiempo *"
                density="compact"
                variant="outlined"
                hide-details="auto"
              />
            </VCol>
          </template>

          <!-- Fechas: Primer pago y Término -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.start_date"
              type="date"
              label="Primer pago *"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-calendar"
              hide-details="auto"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.end_date"
              type="date"
              label="Fecha límite (opcional)"
              placeholder="Indefinido"
              clearable
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-calendar-off"
              hide-details="auto"
            />
          </VCol>

          <!-- Notas u observaciones -->
          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              label="Notas / Referencias"
              placeholder="Titular del servicio, número de contrato o cuenta de destino..."
              rows="2"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-notes"
              hide-details="auto"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botones de Acción consistentes con el diseño del sistema -->
      <VCardActions class="pa-4 border-t justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-device-floppy"
          :loading="loading"
          :disabled="loading || !form.name.trim() || !form.category_id || !form.amount"
          @click="submit"
        >
          Guardar Gasto Fijo
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
}

.gap-2 {
  gap: 8px !important;
}
</style>
