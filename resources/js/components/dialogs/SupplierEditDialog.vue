<script setup>
import { computed, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";

const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const isRestaurant = computed(() => false);

// Proveedor de tipo gasto (formulario simplificado)
const isExpenseSupplier = computed(() => formData.value.type === "externo" || formData.value.type === "gasto");

// Determina visibilidad de campo según tipo de proveedor
const isFieldVisible = (fieldKey) => {
  if (isExpenseSupplier.value) {
    const expenseFields = brandingStore.settings.expense_supplier_form_fields;
    return !expenseFields || expenseFields.includes(fieldKey);
  }
  return !brandingStore.settings.supplier_form_fields || brandingStore.settings.supplier_form_fields.includes(fieldKey);
};

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
  payment_email: "",
  payment_due_type: "invoice_date",
  invoice_date_reference: "issue_date",
  custom_due_days: null,
  payment_due_reference: "issue_date",
  credit_days: null,
  min_order_amount: null,
  payment_method: "Bs",
  cash_payment: false,
  charges_igtf: false,
  dispatch_days: [],
  order_days: {},
  is_indexed: false,
  is_active: true,
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

  if (isRestaurant.value) {
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
    payload.type = "externo";
  } else {
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
  }

  if (isNewSupplier.value) {
    if (isRestaurant.value) {
      emit("save", {
        ...current,
        type: "externo",
      });
    } else {
      emit("save", { ...current });
    }
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
    if (Array.isArray(days)) {
      days.forEach((d) => {
        if (!Array.isArray(formData.value.order_days[d])) {
          formData.value.order_days[d] = [];
        }
      });
    }
  },
  { immediate: true },
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="920px"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card border-0 elevation-12 d-flex flex-column" style="max-height: 90vh;">
      <!-- Cabecera -->
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
                {{ isNewSupplier ? (isRestaurant ? 'Nuevo Proveedor' : (formData.type === 'externo' ? 'Nuevo Proveedor de Gastos' : 'Nueva Droguería')) : "Editar Proveedor" }}
              </h2>
              <span class="text-super-xs text-white opacity-90 uppercase font-weight-bold letter-spacing-1">
                {{ isNewSupplier ? "Registro de aliado comercial" : (isRestaurant ? 'Proveedor' : (formData.type === 'externo' ? 'Proveedor de Gastos' : 'Droguería')) + (formData.name ? ' | ' + formData.name : '') }}
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

      <!-- Navegación por Pestañas Sólida -->
      <VTabs
        v-if="!isRestaurant"
        v-model="activeTab"
        color="primary"
        grow
        height="46"
        class="bg-white border-b px-2 flex-shrink-0"
      >
        <VTab :value="0" class="text-xs font-weight-bold text-none letter-spacing-05">
          <VIcon icon="tabler-file-description" size="18" class="me-2" />
          Información Fiscal y Comercial
        </VTab>
        <VTab v-if="isFieldVisible('logistics_dispatch')" :value="1" class="text-xs font-weight-bold text-none letter-spacing-05">
          <VIcon icon="tabler-truck" size="18" class="me-2" />
          Logística y Despacho
        </VTab>
      </VTabs>

      <!-- Contenido del Formulario en 2 Columnas -->
      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto">
        <VForm @submit.prevent="submitForm">
          <VWindow v-model="activeTab">
            <!-- PESTAÑA 1: Identificación Fiscal & Contacto/Condiciones (2 Columnas) -->
            <VWindowItem :value="0">
              <VRow dense class="gy-3">
                <!-- Columna Izquierda: Identificación Fiscal -->
                <VCol cols="12" md="6">
                  <VCard variant="outlined" class="pa-4 h-100 rounded-lg bg-surface border-card">
                    <div class="d-flex align-center gap-2 mb-3">
                      <VIcon icon="tabler-building-bank" color="primary" size="20" />
                      <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Identificación Fiscal</span>
                    </div>

                    <VRow dense>
                      <VCol cols="12" v-if="!isRestaurant">
                        <AppSelect
                          v-model="formData.type"
                          :items="[
                            { title: 'Droguerías / Mercancía (Inventario)', value: 'drogueria' },
                            { title: 'Gastos y Servicios Operativos', value: 'externo' },
                          ]"
                          label="Tipo de Proveedor *"
                          prepend-inner-icon="tabler-category"
                          :error-messages="formErrors.type"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>

                      <VCol cols="12" v-if="authStore.isAdmin">
                        <div class="d-flex align-center justify-space-between pa-3 rounded-lg border bg-light-surface">
                          <div class="d-flex flex-column">
                            <span class="text-caption font-weight-bold">Estado del Proveedor</span>
                            <span class="text-xxs text-disabled">
                              {{ formData.is_active ? 'Proveedor activo para compras y sincronizaciones' : 'Desactivado (al final de la lista, sin conexiones)' }}
                            </span>
                          </div>
                          <VSwitch
                            v-model="formData.is_active"
                            color="success"
                            hide-details
                            density="compact"
                          />
                        </div>
                      </VCol>

                      <VCol cols="12" v-if="isFieldVisible('name')">
                        <AppTextField
                          v-model="formData.name"
                          label="Nombre Comercial *"
                          placeholder="Ej: Droguería Nena"
                          prepend-inner-icon="tabler-building-store"
                          :error-messages="formErrors.name"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>

                      <VCol cols="12" v-if="isFieldVisible('social_reason')">
                        <AppTextField
                          v-model="formData.social_reason"
                          :label="'Razón Social' + ((isRestaurant || formData.type !== 'externo') ? '' : ' *')"
                          placeholder="Ej: Droguería Nena C.A."
                          prepend-inner-icon="tabler-certificate"
                          :error-messages="formErrors.social_reason"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>

                      <VCol cols="12" v-if="isFieldVisible('rif')">
                        <AppTextField
                          v-model="formData.rif"
                          :label="'RIF' + (isRestaurant ? '' : ' *')"
                          placeholder="J-12345678-9"
                          prepend-inner-icon="tabler-id-badge-2"
                          :error-messages="formErrors.rif"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>

                      <VCol cols="12" v-if="isFieldVisible('address')">
                        <AppTextField
                          v-model="formData.address"
                          :label="'Dirección Fiscal' + ((isRestaurant || formData.type !== 'externo') ? '' : ' *')"
                          placeholder="Ubicación o dirección completa"
                          prepend-inner-icon="tabler-map-pin"
                          :error-messages="formErrors.address"
                          :readonly="!authStore.isAdmin"
                        />
                      </VCol>
                    </VRow>
                  </VCard>
                </VCol>

                <!-- Columna Derecha: Contacto y Condiciones de Pago -->
                <VCol cols="12" md="6">
                  <VCard variant="outlined" class="pa-4 h-100 rounded-lg bg-surface border-card d-flex flex-column justify-space-between">
                    <div>
                      <div class="d-flex align-center gap-2 mb-3">
                        <VIcon icon="tabler-cash-banknote" color="primary" size="20" />
                        <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Contacto y Condiciones</span>
                      </div>

                      <VRow dense>
                        <VCol cols="12" sm="6" v-if="isFieldVisible('sales_phone')">
                          <AppTextField
                            v-model="formData.sales_phone"
                            label="Teléfono Ventas"
                            type="tel"
                            placeholder="04121234567"
                            prepend-inner-icon="tabler-phone"
                            :error-messages="formErrors.sales_phone"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" sm="6" v-if="isFieldVisible('collections_phone')">
                          <AppTextField
                            v-model="formData.collections_phone"
                            label="Teléfono Cobranza"
                            type="tel"
                            placeholder="04147654321"
                            prepend-inner-icon="tabler-phone-incoming"
                            :error-messages="formErrors.collections_phone"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" v-if="!isRestaurant && formData.type !== 'externo'">
                          <AppTextField
                            v-model="formData.payment_email"
                            label="Correo para Notificación de Pagos"
                            type="email"
                            placeholder="pagos@drogueria.com"
                            prepend-inner-icon="tabler-mail-dollar"
                            :error-messages="formErrors.payment_email"
                            hint="Se enviará automáticamente el soporte de pago a este correo"
                            persistent-hint
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" sm="6" v-if="isFieldVisible('payment_due_type')">
                          <AppSelect
                            v-model="formData.payment_due_type"
                            :items="[
                              { title: 'Fecha de factura', value: 'invoice_date' },
                              { title: 'Pronto pago', value: 'early_payment' },
                              { title: 'Personalizado', value: 'custom' },
                            ]"
                            label="Condición de Vencimiento"
                            prepend-inner-icon="tabler-calendar-stats"
                            :error-messages="formErrors.payment_due_type"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" sm="6" v-if="isFieldVisible('invoice_date_reference') || isFieldVisible('custom_due_days') || isFieldVisible('payment_due_reference')">
                          <AppSelect
                            v-if="formData.payment_due_type === 'invoice_date' && isFieldVisible('invoice_date_reference')"
                            v-model="formData.invoice_date_reference"
                            :items="[
                              { title: 'Fecha Emisión', value: 'issue_date' },
                              { title: 'Fecha Recibo', value: 'receipt_date' },
                              { title: 'Fecha Vencimiento', value: 'expiration_date' },
                            ]"
                            label="Referencia de Fecha"
                            prepend-inner-icon="tabler-timeline"
                            :error-messages="formErrors.invoice_date_reference"
                            :readonly="!authStore.isAdmin"
                          />
                          <AppTextField
                            v-else-if="formData.payment_due_type === 'custom' && isFieldVisible('custom_due_days')"
                            v-model.number="formData.custom_due_days"
                            label="Días de Plazo"
                            type="number"
                            prepend-inner-icon="tabler-numbers"
                            :error-messages="formErrors.custom_due_days"
                            :readonly="!authStore.isAdmin"
                          />
                          <AppSelect
                            v-else-if="formData.payment_due_type === 'early_payment' && isFieldVisible('payment_due_reference')"
                            v-model="formData.payment_due_reference"
                            :items="[
                              { title: 'Fecha Emisión', value: 'issue_date' },
                              { title: 'Fecha Recibo', value: 'receipt_date' },
                            ]"
                            label="Calcular desde"
                            prepend-inner-icon="tabler-clock-play"
                            :error-messages="formErrors.payment_due_reference"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" sm="6" v-if="isFieldVisible('credit_days')">
                          <AppTextField
                            v-model.number="formData.credit_days"
                            label="Días de Crédito Habitual"
                            type="number"
                            placeholder="Ej: 15"
                            prepend-inner-icon="tabler-calendar-time"
                            :error-messages="formErrors.credit_days"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>

                        <VCol cols="12" :sm="isFieldVisible('credit_days') ? 6 : 12">
                          <AppTextField
                            v-model.number="formData.min_order_amount"
                            label="Pedido Mínimo"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            prepend-inner-icon="tabler-shopping-cart-dollar"
                            :error-messages="formErrors.min_order_amount"
                            :readonly="!authStore.isAdmin"
                          />
                        </VCol>
                      </VRow>
                    </div>

                    <!-- Bloque de Moneda & Parámetros Financieros -->
                    <div v-if="isFieldVisible('payment_method') || isFieldVisible('is_indexed')" class="d-flex align-center flex-wrap justify-space-between gap-2 py-2 px-3 border rounded-lg bg-light-surface mt-3">
                      <div v-if="isFieldVisible('payment_method')" class="d-flex align-center gap-2">
                        <span class="text-xs font-weight-bold text-high-emphasis">Moneda:</span>
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
                            class="me-2 text-xs"
                          />
                        </VRadioGroup>
                      </div>

                      <div class="d-flex align-center gap-3">
                        <VCheckbox
                          v-if="isFieldVisible('is_indexed')"
                          v-model="formData.is_indexed"
                          label="Indexar (USD)"
                          color="primary"
                          hide-details
                          density="compact"
                          class="font-weight-bold text-xs"
                        />
                        <VCheckbox
                          v-if="isFieldVisible('charges_igtf')"
                          v-model="formData.charges_igtf"
                          label="Cobra IGTF"
                          color="primary"
                          hide-details
                          density="compact"
                          class="font-weight-bold text-xs"
                        />
                      </div>
                    </div>
                  </VCard>
                </VCol>
              </VRow>
            </VWindowItem>

            <!-- PESTAÑA 2: Logística y Despacho -->
            <VWindowItem v-if="isFieldVisible('logistics_dispatch')" :value="1">
              <div class="d-flex flex-column gap-4">
                <!-- 1. Días de Despacho -->
                <VCard variant="outlined" class="pa-4 rounded-lg bg-surface border-card">
                  <div class="d-flex align-center gap-2 mb-1">
                    <VIcon icon="tabler-truck-delivery" color="primary" size="20" />
                    <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Días de Despacho (Entrega en Farmacia)</span>
                  </div>
                  <p class="text-xs text-medium-emphasis mb-3">
                    Selecciona los días de la semana en que el proveedor realiza entregas de mercancía en la sucursal.
                  </p>

                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="dia in dias"
                      :key="dia.value"
                      :color="formData.dispatch_days?.includes(dia.value) ? 'primary' : 'default'"
                      :variant="formData.dispatch_days?.includes(dia.value) ? 'flat' : 'outlined'"
                      class="cursor-pointer font-weight-bold px-4 py-2 text-sm transition-all"
                      rounded="lg"
                      @click="
                        formData.dispatch_days.includes(dia.value)
                          ? formData.dispatch_days.splice(formData.dispatch_days.indexOf(dia.value), 1)
                          : formData.dispatch_days.push(dia.value)
                      "
                    >
                      <VIcon v-if="formData.dispatch_days?.includes(dia.value)" icon="tabler-check" size="16" class="me-1" />
                      {{ dia.label.toUpperCase() }}
                    </VChip>
                  </div>
                </VCard>

                <!-- 2. Programación de Pedidos -->
                <VCard v-if="formData.dispatch_days?.length" variant="outlined" class="pa-4 rounded-lg bg-surface border-card">
                  <div class="d-flex align-center gap-2 mb-1">
                    <VIcon icon="tabler-calendar-event" color="primary" size="20" />
                    <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Días Límite para Transmitir la Orden de Compra</span>
                  </div>
                  <p class="text-xs text-medium-emphasis mb-3">
                    Para cada día de entrega seleccionado, indica qué días se debe generar y enviar la orden de compra.
                  </p>

                  <VRow dense class="gy-3">
                    <VCol v-for="diaD in formData.dispatch_days" :key="diaD" cols="12" md="6">
                      <VCard variant="outlined" class="pa-3 rounded-lg border-subtle">
                        <div class="d-flex align-center justify-space-between mb-2">
                          <div class="d-flex align-center gap-2">
                            <VIcon icon="tabler-truck" size="18" color="primary" />
                            <span class="text-xs font-weight-black text-high-emphasis text-uppercase">
                              Entrega del {{ diasFull[diaD] }}
                            </span>
                          </div>
                          <span class="text-xxs text-medium-emphasis">Hacer pedido el:</span>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mt-2">
                          <VChip
                            v-for="dia in dias"
                            :key="dia.value"
                            :color="formData.order_days[diaD]?.includes(dia.value) ? 'primary' : 'default'"
                            :variant="formData.order_days[diaD]?.includes(dia.value) ? 'flat' : 'outlined'"
                            class="cursor-pointer font-weight-medium"
                            size="small"
                            rounded="md"
                            @click="
                              formData.order_days[diaD]?.includes(dia.value)
                                ? formData.order_days[diaD].splice(formData.order_days[diaD].indexOf(dia.value), 1)
                                : (formData.order_days[diaD] = [...(formData.order_days[diaD] || []), dia.value])
                            "
                          >
                            <VIcon v-if="formData.order_days[diaD]?.includes(dia.value)" icon="tabler-check" size="12" class="me-1" />
                            {{ dia.label }}
                          </VChip>
                        </div>
                      </VCard>
                    </VCol>
                  </VRow>
                </VCard>

                <VCard v-else variant="outlined" class="pa-6 rounded-lg bg-surface border-card text-center opacity-75">
                  <VIcon icon="tabler-calendar-off" size="40" color="disabled" class="mb-2" />
                  <div class="text-sm font-weight-bold text-medium-emphasis">Sin días de despacho asignados</div>
                  <div class="text-xs text-disabled mt-1">Selecciona arriba los días de entrega del proveedor para programar las órdenes de compra.</div>
                </VCard>
              </div>
            </VWindowItem>
          </VWindow>
        </VForm>
      </VCardText>

      <!-- Pie del Modal -->
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
              height="44"
              class="font-weight-bold rounded-lg shadow-primary text-button"
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

.border-card {
  border-color: rgba(var(--v-border-color), 0.15) !important;
}

.border-subtle {
  border-color: rgba(var(--v-border-color), 0.12) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.35) !important;
}

.text-super-xs {
  font-size: 0.7rem !important;
}

.text-xxs {
  font-size: 0.68rem !important;
}

.letter-spacing-1 {
  letter-spacing: 0.06em !important;
}

.letter-spacing-05 {
  letter-spacing: 0.03em !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.cursor-pointer {
  cursor: pointer;
}

.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
