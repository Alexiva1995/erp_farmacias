<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier:   { type: Object, default: () => ({}) },
  errors:     { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

// ─── Estado ────────────────────────────────────────────────────────────────
const activeTab  = ref(0);
const formData   = ref({ dispatch_days: [], order_days: {} });
const formErrors = ref({});

const opciones = [
  { label: "Bs",      value: "Bs" },
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
  monday: "Lunes", tuesday: "Martes", wednesday: "Miércoles",
  thursday: "Jueves", friday: "Viernes", saturday: "Sábado",
};

const isNewSupplier = computed(() => !formData.value.id);

// ─── Métodos ───────────────────────────────────────────────────────────────
const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  activeTab.value  = 0;
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const original = props.supplier || {};
  const current  = formData.value;
  const payload  = {};

  Object.entries(current).forEach(([key, value]) => {
    const originalValue = original[key];
    const hasChanged = typeof value === "object"
      ? JSON.stringify(value) !== JSON.stringify(originalValue)
      : value !== originalValue;

    if (hasChanged && ["payment_due_type", "payment_due_reference", "custom_due_days"].includes(key)) {
      payload[key] = value === undefined ? null : value;
      return;
    }

    const isFilled = Array.isArray(value)
      ? value.length > 0
      : typeof value === "object" && value !== null
        ? Object.values(value).some(v => Array.isArray(v) ? v.length > 0 : !!v)
        : typeof value === "boolean" ? true : value !== null && value !== "" && value !== undefined;

    if (hasChanged && isFilled) payload[key] = value;
  });

  const currentRef = formData.value.invoice_date_reference;
  if (formData.value.payment_due_type === "invoice_date") {
    payload.invoice_date_reference = currentRef;
  }

  if (
    typeof formData.value.order_days === "object" &&
    Object.values(formData.value.order_days).some(v => Array.isArray(v) && v.length > 0)
  ) {
    payload.order_days = formData.value.order_days;
  }

  emit("save", payload);
};

// ─── Watchers ──────────────────────────────────────────────────────────────
watch(() => props.errors, (v) => { formErrors.value = v || {}; }, { deep: true });

watch(
  () => props.supplier,
  (newSupplier) => {
    if (newSupplier && Object.keys(newSupplier).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newSupplier));
      const normalized = {};
      (formData.value.dispatch_days || []).forEach((day) => {
        const old = newSupplier.order_days;
        normalized[day] = (old && !Array.isArray(old) && Array.isArray(old[day])) ? [...old[day]] : [];
      });
      formData.value.order_days = normalized;
    } else {
      formData.value = { name: "", dispatch_days: [], order_days: {} };
    }
    formErrors.value = {};
    activeTab.value  = 0;
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
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData">

      <!-- ── Header ─────────────────────────────────────────────────── -->
      <div
        class="d-flex align-center px-5 py-4"
        style="background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.8) 100%);"
      >
        <VAvatar color="white" variant="tonal" size="38" rounded class="me-3">
          <VIcon icon="tabler-truck-delivery" size="20" color="white" />
        </VAvatar>
        <div>
          <p class="text-subtitle-1 font-weight-bold ma-0" style="color: #fff !important; line-height: 1.2;">
            {{ isNewSupplier ? "Añadir Proveedor" : "Editar Proveedor" }}
          </p>
          <p class="text-caption ma-0" style="color: rgba(255, 255, 255, 75%) !important;">
            {{ isNewSupplier ? "Completa los datos del nuevo proveedor" : formData.name }}
          </p>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="text" size="small" style="color: #fff;" @click="closeDialog" />
      </div>

      <!-- ── Tabs ───────────────────────────────────────────────────── -->
      <VTabs v-model="activeTab" color="primary" density="compact">
        <VTab :value="0" prepend-icon="tabler-info-circle">
          <span class="text-caption font-weight-medium">General</span>
        </VTab>
        <VTab :value="1" prepend-icon="tabler-truck">
          <span class="text-caption font-weight-medium">Logística</span>
        </VTab>
      </VTabs>
      <VDivider />

      <!-- ── Contenido Tabs ─────────────────────────────────────────── -->
      <VCardText class="pa-5" style="min-block-size: 320px;">
        <VTabsWindow v-model="activeTab">

          <!-- Tab 1 ─ General ────────────────────────────── -->
          <VTabsWindowItem :value="0">
            <VForm @submit.prevent="submitForm">
              <!-- Fila 1: Nombre + Razón Social -->
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
              </VRow>

              <!-- Fila 2: RIF + Dirección -->
              <VRow dense class="mt-1">
                <VCol cols="12" md="4">
                  <AppTextField
                    v-model="formData.rif"
                    label="RIF"
                    placeholder="J-12345678-9"
                    prepend-inner-icon="tabler-id"
                    :error-messages="formErrors.rif"
                  />
                </VCol>
                <VCol cols="12" md="8">
                  <AppTextField
                    v-model="formData.address"
                    label="Dirección"
                    placeholder="Dirección fiscal"
                    prepend-inner-icon="tabler-map-pin"
                    :error-messages="formErrors.address"
                  />
                </VCol>
              </VRow>

              <!-- Fila 3: Teléfonos -->
              <VRow dense class="mt-1">
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
                    prepend-inner-icon="tabler-phone-call"
                    :error-messages="formErrors.collections_phone"
                  />
                </VCol>
              </VRow>

              <!-- Separador Pago -->
              <div class="d-flex align-center gap-2 mt-4 mb-3">
                <VIcon icon="tabler-credit-card" size="16" color="primary" />
                <span class="text-caption text-primary font-weight-bold text-uppercase">Pago</span>
                <VDivider />
              </div>

              <!-- Fila 4: Días crédito + Forma de pago -->
              <VRow dense>
                <VCol cols="12" md="4">
                  <AppTextField
                    v-model.number="formData.credit_days"
                    label="Días de Crédito"
                    type="number"
                    placeholder="0"
                    prepend-inner-icon="tabler-calendar-time"
                    :error-messages="formErrors.credit_days"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <AppSelect
                    v-model="formData.payment_due_type"
                    :items="[
                      { title: 'Fecha de factura', value: 'invoice_date' },
                      { title: 'Pronto pago',      value: 'early_payment' },
                      { title: 'Personalizado',    value: 'custom' },
                    ]"
                    label="Límite de Pago"
                    prepend-inner-icon="tabler-calendar-stats"
                    :error-messages="formErrors.payment_due_type"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <!-- Campo condicional según payment_due_type -->
                  <AppSelect
                    v-if="formData.payment_due_type === 'invoice_date'"
                    v-model="formData.invoice_date_reference"
                    :items="[
                      { title: 'Fecha Recibo',     value: 'receipt_date' },
                      { title: 'Fecha Vencimiento',value: 'expiration_date' },
                      { title: 'Fecha Emisión',    value: 'issue_date' },
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
                      { title: 'Fecha Recibo',  value: 'receipt_date' },
                    ]"
                    label="Contar desde"
                    prepend-inner-icon="tabler-clock-play"
                    :error-messages="formErrors.payment_due_reference"
                  />
                </VCol>
              </VRow>

              <!-- Fila 5: Forma de pago (radio) -->
              <VRow dense class="mt-1">
                <VCol cols="12">
                  <div class="d-flex align-center gap-4">
                    <span class="text-body-2 text-medium-emphasis">Moneda:</span>
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
                      />
                    </VRadioGroup>
                  </div>
                </VCol>
              </VRow>
            </VForm>
          </VTabsWindowItem>

          <!-- Tab 2 ─ Logística ──────────────────────────── -->
          <VTabsWindowItem :value="1">
            <!-- Días de despacho -->
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-calendar-check" size="16" color="primary" />
              <span class="text-caption text-primary font-weight-bold text-uppercase">Días de entrega del proveedor</span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
              <VChip
                v-for="dia in dias"
                :key="dia.value"
                :color="formData.dispatch_days?.includes(dia.value) ? 'primary' : undefined"
                :variant="formData.dispatch_days?.includes(dia.value) ? 'flat' : 'outlined'"
                class="cursor-pointer"
                size="small"
                @click="
                  formData.dispatch_days.includes(dia.value)
                    ? formData.dispatch_days.splice(formData.dispatch_days.indexOf(dia.value), 1)
                    : formData.dispatch_days.push(dia.value)
                "
              >
                {{ dia.label }}
              </VChip>
            </div>

            <!-- Días de pedido por día de despacho -->
            <template v-if="formData.dispatch_days?.length">
              <VDivider class="mb-3" />
              <div class="d-flex align-center gap-2 mb-3">
                <VIcon icon="tabler-clock-edit" size="16" color="warning" />
                <span class="text-caption text-warning font-weight-bold text-uppercase">Días para hacer el pedido</span>
              </div>

              <div
                v-for="diaD in formData.dispatch_days"
                :key="diaD"
                class="mb-3"
              >
                <div class="text-caption font-weight-bold mb-1 text-medium-emphasis">
                  Para entregar el {{ diasFull[diaD] }}, pedir:
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    v-for="dia in dias"
                    :key="dia.value"
                    :color="formData.order_days[diaD]?.includes(dia.value) ? 'warning' : undefined"
                    :variant="formData.order_days[diaD]?.includes(dia.value) ? 'flat' : 'outlined'"
                    class="cursor-pointer"
                    size="x-small"
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
            </template>

            <VAlert
              v-else
              type="info"
              variant="tonal"
              density="compact"
              icon="tabler-info-circle"
              class="mt-2"
            >
              Selecciona al menos un día de entrega arriba para configurar los días de pedido.
            </VAlert>
          </VTabsWindowItem>

        </VTabsWindow>
      </VCardText>

      <VDivider />

      <!-- ── Footer ─────────────────────────────────────────────────── -->
      <VCardActions class="pa-4 gap-3">
        <VBtn color="secondary" variant="outlined" class="flex-grow-1" @click="closeDialog">
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="flex-grow-1"
          prepend-icon="tabler-device-floppy"
          @click="submitForm"
        >
          {{ isNewSupplier ? "Crear Proveedor" : "Guardar Cambios" }}
        </VBtn>
      </VCardActions>

    </VCard>
  </VDialog>
</template>
