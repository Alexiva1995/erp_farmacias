<script setup lang="js">
import { computed, reactive, watch } from "vue";

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
    max-width="720"
    persistent
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard class="pa-2">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" class="me-2">
            <VIcon icon="tabler-calendar-repeat" size="24" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          {{ title }}
        </VCardTitle>
        <VCardSubtitle>
          Configure el compromiso recurrente y su calendario de recordatorios
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <VRow dense>
          <!-- Nombre -->
          <VCol cols="12" md="8">
            <VTextField
              v-model="form.name"
              label="Nombre del gasto fijo *"
              placeholder="Ej: Alquiler Local Central, Servicio de Luz, Internet Fibra..."
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Categoría -->
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.category_id"
              :items="categories"
              item-title="name"
              item-value="id"
              label="Categoría *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Monto y Moneda -->
          <VCol cols="12" sm="4">
            <VTextField
              v-model="form.amount"
              type="number"
              min="0"
              step="0.01"
              label="Monto base / estimado *"
              placeholder="0.00"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" sm="3">
            <VSelect
              v-model="form.currency"
              :items="currencies"
              label="Moneda *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" sm="5">
            <VSelect
              v-model="form.default_count"
              :items="paymentMethods"
              label="Método de pago sugerido"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Monto variable -->
          <VCol cols="12">
            <VSwitch
              v-model="form.is_amount_variable"
              color="primary"
              density="compact"
              label="Monto estimado / variable (ej. luz, agua, gas: se confirma el monto exacto al pagar)"
            />
          </VCol>

          <VDivider class="my-2" />

          <!-- Frecuencia -->
          <VCol cols="12" sm="6">
            <VSelect
              v-model="selectedPreset"
              :items="presetFrequencies.map(p => ({ title: p.title, value: p.unit === 'custom' ? 'custom' : `${p.unit}_${p.count}` }))"
              item-title="title"
              item-value="value"
              label="Frecuencia de pago *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Días de anticipación -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.reminder_days_before"
              type="number"
              min="0"
              max="90"
              label="Avisar con anticipación *"
              suffix="días antes"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Personalizado (si aplica) -->
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
              />
            </VCol>
          </template>

          <!-- Primer pago y Fin -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.start_date"
              type="date"
              label="Fecha del primer pago *"
              density="compact"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.end_date"
              type="date"
              label="Fecha de finalización (opcional)"
              placeholder="Indefinido si está vacío"
              clearable
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Notas -->
          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              label="Notas u observaciones"
              placeholder="Número de contrato, titular del servicio, referencia de pago..."
              rows="2"
              density="compact"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pt-0 px-4 pb-3">
        <VSpacer />
        <VBtn variant="text" color="secondary" @click="close">
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
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
