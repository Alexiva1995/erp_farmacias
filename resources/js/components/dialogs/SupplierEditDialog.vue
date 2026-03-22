<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

// ─── Estado ────────────────────────────────────────────────────────────────
const activeTab = ref('general');
const baseForm = {
  id: null,
  name: "",
  social_reason: "",
  rif: "",
  address: "",
  sales_phone: "",
  collections_phone: "",
  payment_due_type: null,
  invoice_date_reference: null,
  custom_due_days: null,
  payment_due_reference: null,
  payment_method: null,
  dispatch_days: [],
  order_days: {},
  is_indexed: false,
};
const formData = ref({ ...baseForm });
const formErrors = ref({});

const opciones = [
  { label: "Bs", value: "Bs" },
  { label: "Divisas", value: "Divisas" },
];

const dias = [
  { label: "Lun", value: "monday" },
  { label: "Mar", value: "tuesday" },
  { label: "Mié", value: "wednesday" },
  { label: "Jue", value: "thursday" },
  { label: "Vie", value: "friday" },
  { label: "Sáb", value: "saturday" },
];

const diasFull = {
  monday: "Lunes",
  tuesday: "Martes",
  wednesday: "Miércoles",
  thursday: "Jueves",
  friday: "Viernes",
  saturday: "Sábado",
};

const isNewSupplier = computed(() => !formData.value.id);

// ─── Métodos ───────────────────────────────────────────────────────────────
const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  activeTab.value = "general";
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const original = props.supplier || {};
  const current = formData.value;
  const payload = {};

  Object.entries(current).forEach(([key, value]) => {
    const originalValue = original[key];
    const hasChanged =
      typeof value === "object" && value !== null
        ? JSON.stringify(value) !== JSON.stringify(originalValue)
        : value !== originalValue;

    if (hasChanged) {
      payload[key] = value === "" ? null : value;
    }
  });

  // Consistencia de pagos
  if (payload.payment_due_type !== undefined) {
    if (current.payment_due_type === "invoice_date") {
      payload.invoice_date_reference = current.invoice_date_reference;
      payload.custom_due_days = null;
      payload.payment_due_reference = null;
    } else if (current.payment_due_type === "early_payment") {
      payload.payment_due_reference = current.payment_due_reference;
      payload.custom_due_days = null;
      payload.invoice_date_reference = null;
    } else if (current.payment_due_type === "custom") {
      payload.custom_due_days = current.custom_due_days;
      payload.payment_due_reference = null;
      payload.invoice_date_reference = null;
    }
  }

  if (payload.order_days !== undefined || payload.dispatch_days !== undefined) {
    payload.order_days = current.order_days;
    payload.dispatch_days = current.dispatch_days;
  }

  if (isNewSupplier.value) {
    emit("save", { ...current });
  } else {
    emit("save", payload);
  }
};

// ─── Watchers ──────────────────────────────────────────────────────────────
watch(
  () => props.errors,
  (v) => {
    formErrors.value = v || {};
  },
  { deep: true },
);

const initForm = () => {
  const newSupplier = props.supplier;
  if (newSupplier && Object.keys(newSupplier).length > 0) {
    formData.value = {
      ...baseForm,
      ...JSON.parse(JSON.stringify(newSupplier)),
    };
    const normalized = {};
    (formData.value.dispatch_days || []).forEach((day) => {
      const old = newSupplier.order_days;
      normalized[day] =
        old && !Array.isArray(old) && Array.isArray(old[day])
          ? [...old[day]]
          : [];
    });
    formData.value.order_days = normalized;
  } else {
    formData.value = { ...baseForm };
  }
  formErrors.value = {};
  activeTab.value = "general";
};

watch(
  () => [props.modelValue, props.supplier],
  ([isOpen]) => {
    if (isOpen) {
      initForm();
    }
  },
  { deep: true, immediate: true },
);

watch(
  () => formData.value.dispatch_days,
  (days) => {
    days.forEach((d) => {
      if (!Array.isArray(formData.value.order_days[d]))
        formData.value.order_days[d] = [];
    });
  },
  { immediate: true },
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="660px"
    :fullscreen="$vuetify.display.mobile"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData">
      <!-- ── Header ─────────────────────────────────────────────────── -->
      <div class="dialog-header-premium">
        <VAvatar color="white" variant="tonal" size="38" rounded class="me-3">
          <VIcon icon="tabler-truck-delivery" size="20" color="white" />
        </VAvatar>
        <div>
          <p class="text-subtitle-1 font-weight-bold header-title">
            {{ isNewSupplier ? "Añadir Proveedor" : "Editar Proveedor" }}
          </p>
          <p class="text-caption header-subtitle">
            {{
              isNewSupplier
                ? "Completa los datos del nuevo proveedor"
                : formData.name
            }}
          </p>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          color="white"
          @click="closeDialog"
        />
      </div>

      <!-- ── Tabs ───────────────────────────────────────────────────── -->
      <VTabs v-model="activeTab" color="primary" density="compact">
        <VTab value="general" prepend-icon="tabler-info-circle">
          <span class="text-caption font-weight-medium">General</span>
        </VTab>
        <VTab value="logistica" prepend-icon="tabler-truck">
          <span class="text-caption font-weight-medium">Logística</span>
        </VTab>
      </VTabs>
      <VDivider />

      <!-- ── Contenido Tabs ─────────────────────────────────────────── -->
      <VCardText class="pa-5">
        <VTabsWindow v-model="activeTab">
          <!-- Tab 1 ─ General ────────────────────────────── -->
          <VTabsWindowItem value="general">
            <VForm @submit.prevent="submitForm">
              <!-- Sección: Identificación -->
              <div class="d-flex align-center gap-2 mb-4">
                <VAvatar color="primary" variant="tonal" size="24" rounded="sm">
                  <VIcon icon="tabler-id" size="16" />
                </VAvatar>
                <span class="text-subtitle-2 font-weight-bold text-uppercase text-primary letter-spacing-05">Identificación</span>
                <VDivider class="opacity-20" />
              </div>

              <VRow dense>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.name"
                    label="Nombre Comercial *"
                    placeholder="Droguería Nena"
                    prepend-inner-icon="tabler-user"
                    :error-messages="formErrors.name"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.social_reason"
                    label="Razón Social"
                    placeholder="Inversiones Nena C.A."
                    prepend-inner-icon="tabler-building"
                    :error-messages="formErrors.social_reason"
                  />
                </VCol>
                <VCol cols="12" md="5">
                  <AppTextField
                    v-model="formData.rif"
                    label="RIF"
                    placeholder="J-12345678-9"
                    prepend-inner-icon="tabler-id-badge-2"
                    :error-messages="formErrors.rif"
                  />
                </VCol>
                <VCol cols="12" md="7">
                  <AppTextField
                    v-model="formData.address"
                    label="Dirección"
                    placeholder="Dirección fiscal"
                    prepend-inner-icon="tabler-map-pin"
                    :error-messages="formErrors.address"
                  />
                </VCol>
              </VRow>

              <!-- Sección: Contacto -->
              <div class="d-flex align-center gap-2 mt-6 mb-4">
                <VAvatar color="info" variant="tonal" size="24" rounded="sm">
                  <VIcon icon="tabler-phone-call" size="16" />
                </VAvatar>
                <span class="text-subtitle-2 font-weight-bold text-uppercase text-info letter-spacing-05">Contacto</span>
                <VDivider class="opacity-20" />
              </div>

              <VRow dense>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.sales_phone"
                    label="Tel. Ventas"
                    type="tel"
                    placeholder="4121234567"
                    prepend-inner-icon="tabler-phone"
                    :error-messages="formErrors.sales_phone"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.collections_phone"
                    label="Tel. Cobranza"
                    type="tel"
                    placeholder="4147654321"
                    prepend-inner-icon="tabler-phone-incoming"
                    :error-messages="formErrors.collections_phone"
                  />
                </VCol>
              </VRow>

              <!-- Sección: Condiciones de Pago -->
              <div class="d-flex align-center gap-2 mt-6 mb-4">
                <VAvatar color="success" variant="tonal" size="24" rounded="sm">
                  <VIcon icon="tabler-credit-card" size="16" />
                </VAvatar>
                <span class="text-subtitle-2 font-weight-bold text-uppercase text-success letter-spacing-05">Condiciones de Pago</span>
                <VDivider class="opacity-20" />
              </div>

              <VRow dense>
                <VCol cols="12" md="4">
                  <AppSelect
                    v-model="formData.payment_due_type"
                    :items="[
                      { title: 'Fecha de factura', value: 'invoice_date' },
                      { title: 'Pronto pago', value: 'early_payment' },
                      { title: 'Personalizado', value: 'custom' },
                    ]"
                    label="Límite de Pago"
                    prepend-inner-icon="tabler-calendar-stats"
                    :error-messages="formErrors.payment_due_type"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <AppSelect
                    v-if="formData.payment_due_type === 'invoice_date'"
                    v-model="formData.invoice_date_reference"
                    :items="[
                      { title: 'Fecha Recibo', value: 'receipt_date' },
                      { title: 'Fecha Vencimiento', value: 'expiration_date' },
                      { title: 'Fecha Emisión', value: 'issue_date' },
                    ]"
                    label="Referencia"
                    prepend-inner-icon="tabler-timeline"
                    :error-messages="formErrors.invoice_date_reference"
                  />
                  <AppTextField
                    v-else-if="formData.payment_due_type === 'custom'"
                    v-model.number="formData.custom_due_days"
                    label="Días personalizados"
                    type="number"
                    placeholder="0"
                    prepend-inner-icon="tabler-numbers"
                    :error-messages="formErrors.custom_due_days"
                  />
                  <AppSelect
                    v-else-if="formData.payment_due_type === 'early_payment'"
                    v-model="formData.payment_due_reference"
                    :items="[
                      { title: 'Fecha Emisión', value: 'issue_date' },
                      { title: 'Fecha Recibo', value: 'receipt_date' },
                    ]"
                    label="Contar desde"
                    prepend-inner-icon="tabler-clock-play"
                    :error-messages="formErrors.payment_due_reference"
                  />
                </VCol>

                <VCol cols="12">
                  <div class="d-flex align-center gap-4 py-2 px-3 border rounded-lg bg-light-surface mt-2 border-dashed">
                    <span class="text-caption font-weight-bold text-disabled text-uppercase">Moneda de Pago:</span>
                    <VRadioGroup
                      v-model="formData.payment_method"
                      density="compact"
                      hide-details
                      inline
                    >
                      <VRadio
                        v-for="op in opciones"
                        :key="op.value"
                        :label="op.label"
                        :value="op.value"
                        color="primary"
                        class="me-2"
                      />
                    </VRadioGroup>
                    <VSpacer />
                    <VCheckbox
                      v-model="formData.is_indexed"
                      label="Indexación (USD)"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>
                </VCol>
              </VRow>
            </VForm>
          </VTabsWindowItem>

          <!-- Tab 2 ─ Logística ──────────────────────────── -->
          <VTabsWindowItem value="logistica">
            <div class="pa-2">
              <div class="d-flex align-center gap-2 mb-4">
                <VAvatar color="primary" variant="tonal" size="24" rounded="sm">
                  <VIcon icon="tabler-calendar-check" size="16" />
                </VAvatar>
                <span class="text-subtitle-2 font-weight-bold text-uppercase text-primary letter-spacing-05">Días de Entrega</span>
                <VDivider class="opacity-20" />
              </div>

              <div class="bg-light-surface pa-4 rounded-lg border mb-6">
                <p class="text-caption text-medium-emphasis mb-3">Marca los días en los que el proveedor realiza despachos:</p>
                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    v-for="dia in dias"
                    :key="dia.value"
                    :color="formData.dispatch_days?.includes(dia.value) ? 'primary' : undefined"
                    :variant="formData.dispatch_days?.includes(dia.value) ? 'flat' : 'outlined'"
                    class="cursor-pointer transition-all"
                    rounded="lg"
                    @click="
                      formData.dispatch_days.includes(dia.value)
                        ? formData.dispatch_days.splice(formData.dispatch_days.indexOf(dia.value), 1)
                        : formData.dispatch_days.push(dia.value)
                    "
                  >
                    <VIcon v-if="formData.dispatch_days?.includes(dia.value)" icon="tabler-check" size="14" class="me-1" />
                    {{ dia.label }}
                  </VChip>
                </div>
              </div>

              <!-- Días de pedido por día de despacho -->
              <template v-if="formData.dispatch_days?.length">
                <div class="d-flex align-center gap-2 mb-4">
                  <VAvatar color="warning" variant="tonal" size="24" rounded="sm">
                    <VIcon icon="tabler-clock-edit" size="16" />
                  </VAvatar>
                  <span class="text-subtitle-2 font-weight-bold text-uppercase text-warning letter-spacing-05">Configuración de Pedidos</span>
                  <VDivider class="opacity-20" />
                </div>

                <VRow dense>
                  <VCol v-for="diaD in formData.dispatch_days" :key="diaD" cols="12" sm="6">
                    <div class="pa-3 border rounded-lg h-100 bg-light-surface">
                      <div class="d-flex align-center gap-2 mb-2">
                        <VIcon icon="tabler-truck" size="14" class="text-disabled" />
                        <span class="text-caption font-weight-black text-uppercase">{{ diasFull[diaD] }}</span>
                      </div>
                      <p class="text-xs text-disabled mb-2">Selecciona cuándo pedir:</p>
                      <div class="d-flex flex-wrap gap-1">
                        <VChip
                          v-for="dia in dias"
                          :key="dia.value"
                          :color="formData.order_days[diaD]?.includes(dia.value) ? 'warning' : undefined"
                          :variant="formData.order_days[diaD]?.includes(dia.value) ? 'flat' : 'outlined'"
                          class="cursor-pointer"
                          size="x-small"
                          rounded="md"
                          @click="
                            formData.order_days[diaD]?.includes(dia.value)
                              ? formData.order_days[diaD].splice(formData.order_days[diaD].indexOf(dia.value), 1)
                              : (formData.order_days[diaD] = [...(formData.order_days[diaD] || []), dia.value])
                          "
                        >
                          {{ dia.label }}
                        </VChip>
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </template>

              <VAlert
                v-else
                type="info"
                variant="tonal"
                density="compact"
                icon="tabler-info-circle"
                class="mt-2 rounded-lg"
              >
                Activa al menos un día de entrega arriba para configurar la programación de pedidos.
              </VAlert>
            </div>
          </VTabsWindowItem>
        </VTabsWindow>
        <div class="mb-2" />
      </VCardText>

      <VDivider />

      <!-- ── Footer ─────────────────────────────────────────────────── -->
      <VCardActions class="pa-4 gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          class="flex-grow-1"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="flex-grow-1"
          prepend-icon="tabler-device-floppy"
          @click="submitForm"
        >
          {{ isNewSupplier ? "Crear" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog></template>

<style scoped>
.dialog-header-premium {
  display: flex;
  align-items: center;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 80%) 100%);
  padding-block: 16px;
  padding-inline: 20px;
}

.header-title {
  display: block;
  margin: 0;
  color: #fff !important;
  line-height: 1.2;
}

.header-subtitle {
  display: block;
  margin: 0;
  color: rgba(255, 255, 255, 75%) !important;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 2%) !important;
}

.text-xs {
  font-size: 0.7rem !important;
}

.transition-all {
  transition: all 0.2s ease-in-out;
}

.letter-spacing-05 {
  letter-spacing: 0.5px !important;
}

.border-dashed {
  border-style: dashed !important;
}

.dialog-content-min-height {
  min-block-size: 320px !important;
}
</style>
