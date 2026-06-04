<script setup>
import { computed, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

// ─── Estado ────────────────────────────────────────────────────────────────
const activeTab = ref(0);
const baseForm = {
  id: null,
  name: "",
  social_reason: "",
  rif: "",
  address: "",
  sales_phone: "",
  collections_phone: "",
  payment_due_type: "invoice_date",
  invoice_date_reference: "issue_date",
  custom_due_days: null,
  payment_due_reference: "issue_date",
  payment_method: "Bs",
  dispatch_days: [],
  order_days: {},
  is_indexed: false,
  type: "drogueria",
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
  activeTab.value = 0;
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
  activeTab.value = 0;
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
    max-width="700px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar
              color="white"
              variant="flat"
              size="40"
              class="me-3 elevation-2"
            >
              <VIcon
                icon="tabler-truck-delivery"
                color="primary"
                size="24"
              />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                {{ isNewSupplier ? (formData.type === 'externo' ? 'Nuevo Proveedor Externo' : 'Nueva Droguería') : "Editar Proveedor" }}
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
                {{ isNewSupplier ? "Registro de aliado comercial" : (formData.type === 'externo' ? 'Proveedor Externo' : 'Droguería') + ' | ' + formData.name }}
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeDialog"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- Contenido con Tabs -->
      <VCardText class="pa-0 bg-light">
        <VTabs
          v-model="activeTab"
          color="primary"
          grow
          height="48"
          class="bg-white border-b"
        >
          <VTab :value="0" class="text-xs font-weight-black uppercase letter-spacing-1">
            <VIcon icon="tabler-info-circle" size="18" class="me-2" />
            Información General
          </VTab>
          <VTab v-if="formData.type !== 'externo'" :value="1" class="text-xs font-weight-black uppercase letter-spacing-1">
            <VIcon icon="tabler-truck" size="18" class="me-2" />
            Logística y Despacho
          </VTab>
        </VTabs>

        <div class="pa-4 pa-sm-6 overflow-y-auto" style="max-block-size: 70vh;">
          <VForm @submit.prevent="submitForm">
            <VWindow v-model="activeTab" class="overflow-visible">
              <!-- Tab 1: General -->
              <VWindowItem :value="0">
                <div class="d-flex flex-column gap-6">
                  <!-- Identificación -->
                  <VCard variant="flat" class="border pa-4 bg-white rounded-lg">
                    <div class="d-flex align-center gap-2 mb-4">
                      <div class="header-indicator primary"></div>
                      <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Identificación Fiscal</span>
                    </div>

                    <VRow dense>
                      <VCol cols="12" md="6">
                        <AppTextField
                          v-model="formData.name"
                          label="Nombre Comercial"
                          placeholder="Ej: Droguería Nena"
                          prepend-inner-icon="tabler-user"
                          :error-messages="formErrors.name"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                      <VCol cols="12" md="6">
                        <AppTextField
                          v-model="formData.social_reason"
                          :label="'Razón Social' + (formData.type === 'externo' ? ' *' : '')"
                          placeholder="Ej: Inversiones Nena C.A."
                          prepend-inner-icon="tabler-building"
                          :error-messages="formErrors.social_reason"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                      <VCol cols="12" md="5">
                        <AppTextField
                          v-model="formData.rif"
                          label="RIF"
                          placeholder="J-12345678-9"
                          prepend-inner-icon="tabler-id-badge-2"
                          :error-messages="formErrors.rif"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                      <VCol cols="12" md="7">
                        <AppTextField
                          v-model="formData.address"
                          :label="'Dirección Fiscal' + (formData.type === 'externo' ? ' *' : '')"
                          placeholder="Ubicación completa"
                          prepend-inner-icon="tabler-map-pin"
                          :error-messages="formErrors.address"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                    </VRow>
                  </VCard>

                  <!-- Contacto y Pagos -->
                  <VCard v-if="formData.type !== 'externo'" variant="flat" class="border pa-4 bg-white rounded-lg">
                    <div class="d-flex align-center gap-2 mb-4">
                      <div class="header-indicator secondary"></div>
                      <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Contacto y Condiciones</span>
                    </div>

                    <VRow dense>
                      <VCol cols="12" md="6">
                        <AppTextField
                          v-model="formData.sales_phone"
                          label="Teléfono Ventas"
                          type="tel"
                          placeholder="4121234567"
                          prepend-inner-icon="tabler-phone"
                          :error-messages="formErrors.sales_phone"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                      <VCol cols="12" md="6">
                        <AppTextField
                          v-model="formData.collections_phone"
                          label="Teléfono Cobranza"
                          type="tel"
                          placeholder="4147654321"
                          prepend-inner-icon="tabler-phone-incoming"
                          :error-messages="formErrors.collections_phone"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>

                      <VCol cols="12" md="6">
                        <AppSelect
                          v-model="formData.payment_due_type"
                          :items="[
                            { title: 'Fecha de factura', value: 'invoice_date' },
                            { title: 'Pronto pago', value: 'early_payment' },
                            { title: 'Personalizado', value: 'custom' },
                          ]"
                          label="Tipo de Pago"
                          prepend-inner-icon="tabler-calendar-stats"
                          :error-messages="formErrors.payment_due_type"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                      <VCol cols="12" md="6">
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
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                        <AppTextField
                          v-else-if="formData.payment_due_type === 'custom'"
                          v-model.number="formData.custom_due_days"
                          label="Días Plazo"
                          type="number"
                          prepend-inner-icon="tabler-numbers"
                          :error-messages="formErrors.custom_due_days"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                        <AppSelect
                          v-else-if="formData.payment_due_type === 'early_payment'"
                          v-model="formData.payment_due_reference"
                          :items="[
                            { title: 'Fecha Emisión', value: 'issue_date' },
                            { title: 'Fecha Recibo', value: 'receipt_date' },
                          ]"
                          label="Calcular desde"
                          prepend-inner-icon="tabler-clock-play"
                          :error-messages="formErrors.payment_due_reference"
                          class="shadow-sm"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                    </VRow>

                    <div class="d-flex align-center flex-wrap gap-4 py-3 px-4 border rounded-lg bg-var-theme-background mt-4 border-dashed">
                      <div class="d-flex align-center gap-2">
                        <VIcon icon="tabler-coin" size="16" color="primary" />
                        <span class="text-xs font-weight-black text-uppercase text-disabled letter-spacing-1">Moneda:</span>
                      </div>
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
                      <VDivider vertical class="mx-2 hidden-sm-and-down" />
                      <VCheckbox
                        v-model="formData.is_indexed"
                        label="Indexar (USD)"
                        color="primary"
                        hide-details
                        density="compact"
                        class="font-weight-bold"
                      />
                    </div>
                  </VCard>
                </div>
              </VWindowItem>

              <!-- Tab 2: Logística -->
              <VWindowItem :value="1">
                <div class="d-flex flex-column gap-6">
                  <VCard variant="flat" class="border pa-4 bg-white rounded-lg">
                    <div class="d-flex align-center gap-2 mb-4">
                      <div class="header-indicator primary"></div>
                      <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Días de Despacho</span>
                    </div>

                    <div class="bg-var-theme-background pa-4 rounded-lg border border-opacity-25 mb-2 text-center">
                      <p class="text-xs text-secondary font-weight-bold uppercase mb-4 letter-spacing-1">Selecciona los días de entrega del proveedor</p>
                      <div class="d-flex flex-wrap justify-center gap-2">
                        <VChip
                          v-for="dia in dias"
                          :key="dia.value"
                          :color="formData.dispatch_days?.includes(dia.value) ? 'primary' : undefined"
                          :variant="formData.dispatch_days?.includes(dia.value) ? 'flat' : 'outlined'"
                          class="cursor-pointer font-weight-black px-4 transition-all"
                          rounded="lg"
                          @click="
                            formData.dispatch_days.includes(dia.value)
                              ? formData.dispatch_days.splice(formData.dispatch_days.indexOf(dia.value), 1)
                              : formData.dispatch_days.push(dia.value)
                          "
                        >
                          <VIcon v-if="formData.dispatch_days?.includes(dia.value)" icon="tabler-check" size="14" class="me-1" />
                          {{ dia.label.toUpperCase() }}
                        </VChip>
                      </div>
                    </div>
                  </VCard>

                  <template v-if="formData.dispatch_days?.length">
                    <VCard variant="flat" class="border pa-4 bg-white rounded-lg">
                      <div class="d-flex align-center gap-2 mb-4">
                        <div class="header-indicator secondary"></div>
                        <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Programación de Pedidos</span>
                      </div>

                      <VRow dense>
                        <VCol v-for="diaD in formData.dispatch_days" :key="diaD" cols="12" sm="6" lg="4">
                          <div class="pa-3 border rounded-lg h-100 bg-var-theme-background-secondary shadow-sm row-hover-effect">
                            <div class="d-flex align-center gap-2 mb-3">
                              <VAvatar size="20" color="secondary" variant="flat" class="elevation-1">
                                <VIcon icon="tabler-truck" size="12" color="white" />
                              </VAvatar>
                              <span class="text-xs font-weight-black text-uppercase letter-spacing-05">{{ diasFull[diaD] }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                              <VChip
                                v-for="dia in dias"
                                :key="dia.value"
                                :color="formData.order_days[diaD]?.includes(dia.value) ? 'secondary' : undefined"
                                :variant="formData.order_days[diaD]?.includes(dia.value) ? 'flat' : 'tonal'"
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
                    </VCard>
                  </template>

                  <VCard v-else variant="flat" class="border pa-8 bg-white rounded-lg text-center opacity-70">
                    <VIcon icon="tabler-calendar-off" size="48" color="disabled" class="mb-2" />
                    <p class="text-subtitle-2 font-weight-bold text-disabled uppercase letter-spacing-1">Logística no configurada</p>
                    <p class="text-xs text-disabled">Debes seleccionar al menos un día de despacho para habilitar la programación de pedidos.</p>
                  </VCard>
                </div>
              </VWindowItem>
            </VWindow>
          </VForm>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer Premium -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
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
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
              {{ isNewSupplier ? "Crear Proveedor" : "Guardar Cambios" }}
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
  border-radius: 16px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.bg-var-theme-background-secondary {
  background-color: rgba(var(--v-theme-secondary), 0.03);
}

.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
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

.letter-spacing-05 {
  letter-spacing: 0.5px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed {
  border-style: dashed !important;
}

.transition-all {
  transition: all 0.25s ease-in-out;
}

.row-hover-effect:hover {
  background-color: rgba(var(--v-theme-secondary), 0.08);
}
</style>
