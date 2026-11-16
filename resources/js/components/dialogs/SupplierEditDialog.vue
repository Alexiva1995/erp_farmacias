<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import axios from "@axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";

const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  isMaster: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors", "edit-existing", "open-master-directory"]);

const isRestaurant = computed(() => brandingStore.isRestaurant);

// Proveedor de tipo gasto (formulario simplificado)
const isExpenseSupplier = computed(() => formData.value.type === "externo" || formData.value.type === "gasto");

// Determina visibilidad de campo según tipo de proveedor
const isFieldVisible = (fieldKey) => {
  if (fieldKey === "name" || fieldKey === "rif" || fieldKey === "social_reason") {
    return true;
  }
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
  master_supplier_id: null,
  sender_email: "",
  name: "",
  logo_url: null,
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
  is_fiscal: true,
  type: "drogueria",
  business_types: [],
};
const formData = ref({ ...baseForm });
const formErrors = ref({});
const logoFile = ref(null);
const localLogoPreviewUrl = ref(null);

const effectiveLogoUrl = computed(() => {
  return localLogoPreviewUrl.value || formData.value.logo_url || null;
});

const handleLogoFileChange = (files) => {
  if (localLogoPreviewUrl.value) {
    URL.revokeObjectURL(localLogoPreviewUrl.value);
    localLogoPreviewUrl.value = null;
  }
  const selected = Array.isArray(files) ? files[0] : files;
  if (selected instanceof File) {
    logoFile.value = selected;
    localLogoPreviewUrl.value = URL.createObjectURL(selected);
  } else {
    logoFile.value = null;
  }
};

const clearSelectedLogo = () => {
  logoFile.value = null;
  if (localLogoPreviewUrl.value) {
    URL.revokeObjectURL(localLogoPreviewUrl.value);
    localLogoPreviewUrl.value = null;
  }
  formData.value.logo_url = null;
};

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


const availableBusinessTypes = [
  { title: "Farmacia & Salud", value: "pharmacy", icon: "tabler-pill", color: "primary" },
  { title: "Restaurante & Comandas", value: "restaurant", icon: "tabler-tools-kitchen-2", color: "success" },
  { title: "Bodegón & Minimarket", value: "minimarket", icon: "tabler-building-store", color: "secondary" },
  { title: "Comida Rápida & Cafetería", value: "fast_food", icon: "tabler-burger", color: "warning" },
];

const isNewSupplier = computed(() => !props.supplier?.id);
const isLookingUpMaster = ref(false);
const masterFoundNotice = ref(null);

// --- Buscador predictivo en Catálogo Maestro (Search-First) ---
const selectedMasterSupplier = ref(null);
const masterSearchQuery = ref("");
const masterSearchResults = ref([]);
const isSearchingMaster = ref(false);
const linkedMasterSupplier = ref(null);
const alreadyLocalMasterSupplier = ref(null);

const showMasterSearch = computed(() => {
  return isNewSupplier.value && !props.isMaster && (isRestaurant.value || !isExpenseSupplier.value);
});

const isRifLocked = computed(() => {
  if (props.isMaster) return false;
  if (isNewSupplier.value) {
    return Boolean(linkedMasterSupplier.value?.rif && String(linkedMasterSupplier.value.rif).trim() !== "");
  }
  return isReadOnly.value || Boolean(props.supplier?.rif && String(props.supplier.rif).trim() !== "");
});

const isSocialReasonLocked = computed(() => {
  if (props.isMaster) return false;
  if (isNewSupplier.value) {
    return Boolean(linkedMasterSupplier.value?.social_reason && String(linkedMasterSupplier.value.social_reason).trim() !== "");
  }
  return isReadOnly.value || Boolean(props.supplier?.social_reason && String(props.supplier.social_reason).trim() !== "");
});

const showSenderEmailInput = computed(() => {
  if (!linkedMasterSupplier.value) return false;
  const intType = linkedMasterSupplier.value.integration_type;
  return Boolean(linkedMasterSupplier.value.has_excel_structure) || intType === "file" || intType === "email" || !intType;
});

const masterOptionSubtitle = (item) => {
  const rifLabel = item.rif ? `RIF: ${item.rif}` : "Sin RIF";
  if (item.is_installed) {
    return `${rifLabel} • Ya está en tu catálogo`;
  }
  if (item.has_excel_structure) {
    return `${rifLabel} • Plantilla Excel preconfigurada`;
  }
  return `${rifLabel} • Disponible en Red Master`;
};

let masterSearchDebounce = null;
const fetchMasterOptions = async (search = "") => {
  if (!showMasterSearch.value) return;
  isSearchingMaster.value = true;
  try {
    const response = await axios.get("/suppliers-master-catalog", {
      params: {
        search: (search || "").trim(),
        status: "all",
        type: isRestaurant.value ? "all" : (formData.value.type || "drogueria"),
        page: 1,
        per_page: 15,
      },
    });
    masterSearchResults.value = response.data?.data || [];
  } catch (error) {
    console.warn("No se pudo consultar el Catálogo Maestro:", error);
  } finally {
    isSearchingMaster.value = false;
  }
};

const handleMasterSearchInput = (val) => {
  const query = (val || "").trim();
  if (selectedMasterSupplier.value && query === (selectedMasterSupplier.value.name || "").trim()) {
    return;
  }
  clearTimeout(masterSearchDebounce);
  masterSearchDebounce = setTimeout(() => {
    fetchMasterOptions(query);
  }, 300);
};

const applyMasterSupplierToForm = (ms) => {
  formData.value.id = ms.master_id || ms.id;
  formData.value.master_supplier_id = ms.master_id || ms.id;
  formData.value.name = ms.name || formData.value.name;
  formData.value.logo_url = ms.logo_url || formData.value.logo_url;
  formData.value.social_reason = ms.social_reason || ms.name || formData.value.social_reason;
  formData.value.rif = ms.rif || formData.value.rif;
  if (ms.address) formData.value.address = ms.address;
  if (ms.sales_phone) formData.value.sales_phone = ms.sales_phone;
  if (ms.collections_phone) formData.value.collections_phone = ms.collections_phone;
  if (ms.payment_email) formData.value.payment_email = ms.payment_email;
  if (ms.credit_days !== undefined && ms.credit_days !== null) formData.value.credit_days = ms.credit_days;
  if (ms.is_indexed !== undefined) formData.value.is_indexed = Boolean(ms.is_indexed);
  if (ms.is_fiscal !== undefined) formData.value.is_fiscal = Boolean(ms.is_fiscal);
  if (ms.type) formData.value.type = ms.type;
  if (Array.isArray(ms.business_types) && ms.business_types.length > 0) {
    formData.value.business_types = [...ms.business_types];
  }
};

const unlinkMasterSupplier = (clearFields = false) => {
  selectedMasterSupplier.value = null;
  linkedMasterSupplier.value = null;
  alreadyLocalMasterSupplier.value = null;
  masterFoundNotice.value = null;
  formData.value.master_supplier_id = null;
  formData.value.sender_email = "";
  if (clearFields) {
    formData.value.id = null;
    formData.value.name = "";
    formData.value.social_reason = "";
    formData.value.rif = "";
    formData.value.address = "";
    formData.value.sales_phone = "";
    formData.value.collections_phone = "";
    formData.value.payment_email = "";
  }
};

const handleSelectMasterSupplier = (item) => {
  if (!item) {
    unlinkMasterSupplier(false);
    return;
  }

  if (item.is_installed) {
    alreadyLocalMasterSupplier.value = item;
    linkedMasterSupplier.value = null;
    masterFoundNotice.value = null;
    formData.value.master_supplier_id = null;
    return;
  }

  alreadyLocalMasterSupplier.value = null;
  linkedMasterSupplier.value = item;
  applyMasterSupplierToForm(item);

  const templateNote = item.has_excel_structure
    ? " Se copiará automáticamente su plantilla de Excel al guardar."
    : "";
  masterFoundNotice.value = `Proveedor oficial #${item.master_id} (${item.name}) vinculado desde la Red Master.${templateNote}`;
};

const useSearchTextAsNewSupplier = () => {
  const raw = (masterSearchQuery.value || "").trim();
  unlinkMasterSupplier(false);
  masterSearchQuery.value = "";
  if (!raw) return;

  if (/^[VEJPGC]-?\d+/i.test(raw)) {
    formData.value.rif = raw.toUpperCase();
    handleRifInput();
  } else {
    formData.value.name = raw;
    if (!formData.value.social_reason) {
      formData.value.social_reason = raw;
    }
  }
};

const handleOpenExistingFromMaster = () => {
  if (!alreadyLocalMasterSupplier.value?.tenant_supplier_id) return;
  emit("edit-existing", {
    id: alreadyLocalMasterSupplier.value.tenant_supplier_id,
    name: alreadyLocalMasterSupplier.value.name,
    rif: alreadyLocalMasterSupplier.value.rif,
  });
};

let lookupTimer = null;
const handleRifInput = () => {
  if (!isNewSupplier.value || linkedMasterSupplier.value) return;
  masterFoundNotice.value = null;
  alreadyLocalMasterSupplier.value = null;
  clearTimeout(lookupTimer);

  const rawRif = (formData.value.rif || "").trim();
  if (rawRif.length < 5) return;

  lookupTimer = setTimeout(async () => {
    isLookingUpMaster.value = true;
    try {
      const res = await axios.get("/suppliers/lookup", { params: { rif: rawRif } });
      const data = res.data?.data || res.data || {};
      if (data.found_in_master && data.supplier) {
        const ms = data.supplier;
        linkedMasterSupplier.value = {
          ...ms,
          master_id: ms.master_id || ms.id,
        };
        applyMasterSupplierToForm(ms);
        masterFoundNotice.value = `¡Proveedor oficial detectado en Catálogo Maestro (#${ms.id}: ${ms.name})! Datos e ID oficial autocompletados.`;
        toast.success(`Proveedor oficial ${ms.name} (#${ms.id}) cargado desde el Catálogo Maestro.`);
      } else if (data.exists_locally) {
        masterFoundNotice.value = `Este proveedor ya está registrado en tu ${isRestaurant.value ? "restaurante" : "farmacia"} (#${data.supplier?.id}).`;
      }
    } catch (e) {
      console.warn("Error en lookup de proveedor:", e);
    } finally {
      isLookingUpMaster.value = false;
    }
  }, 450);
};

const isReadOnly = computed(() => !isNewSupplier.value && !authStore.isAdmin);

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

  if (alreadyLocalMasterSupplier.value) {
    toast.warning("Este proveedor ya está registrado en tu catálogo. Edítalo desde la lista o selecciona otro.");
    return;
  }

  const original = props.supplier || {};
  const current = formData.value;
  const payload = {};
  const transientKeys = ["master_supplier_id", "sender_email"];

  // Asegurar razón social por defecto si se colocó nombre comercial
  if (!current.social_reason && current.name) {
    current.social_reason = current.name;
    formData.value.social_reason = current.name;
  }

  if (isRestaurant.value) {
    Object.entries(current).forEach(([key, value]) => {
      if (transientKeys.includes(key)) return;
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
      if (transientKeys.includes(key)) return;
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
    const cleanData = { ...current };
    if (!cleanData.id) {
      delete cleanData.id;
    }
    if (!cleanData.master_supplier_id) {
      delete cleanData.master_supplier_id;
    }
    if (!cleanData.sender_email || !cleanData.sender_email.trim()) {
      delete cleanData.sender_email;
    } else {
      cleanData.sender_email = cleanData.sender_email.trim();
    }
    if (isRestaurant.value && !cleanData.master_supplier_id) {
      cleanData.type = "externo";
    }
    if (props.isMaster && logoFile.value) {
      cleanData.logo = logoFile.value;
    }
    emit("save", cleanData);
  } else {
    // Si la razón social tiene valor y no está en el payload, incluirla para consistencia
    if (current.social_reason && payload.social_reason === undefined) {
      payload.social_reason = current.social_reason;
    }
    if (props.isMaster && logoFile.value) {
      payload.logo = logoFile.value;
    }
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
  selectedMasterSupplier.value = null;
  masterSearchQuery.value = "";
  linkedMasterSupplier.value = null;
  alreadyLocalMasterSupplier.value = null;
  masterFoundNotice.value = null;
  logoFile.value = null;
  if (localLogoPreviewUrl.value) {
    URL.revokeObjectURL(localLogoPreviewUrl.value);
    localLogoPreviewUrl.value = null;
  }

  const newSupplier = props.supplier;
  if (newSupplier && Object.keys(newSupplier).length > 0) {
    const parsed = JSON.parse(JSON.stringify(newSupplier));
    formData.value = {
      ...baseForm,
      ...parsed,
      business_types: Array.isArray(parsed.business_types)
        ? parsed.business_types
        : (typeof parsed.business_types === 'string'
            ? (JSON.parse(parsed.business_types || '[]') || [])
            : ['pharmacy']),
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

  if (showMasterSearch.value) {
    fetchMasterOptions("");
  }
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
  () => formData.value.type,
  () => {
    if (showMasterSearch.value && props.modelValue) {
      fetchMasterOptions(masterSearchQuery.value);
    }
  },
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

onUnmounted(() => {
  if (lookupTimer) clearTimeout(lookupTimer);
  if (masterSearchDebounce) clearTimeout(masterSearchDebounce);
  if (localLogoPreviewUrl.value) URL.revokeObjectURL(localLogoPreviewUrl.value);
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="720px"
    :fullscreen="$vuetify.display.mobile"
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
              <VImg
                v-if="effectiveLogoUrl"
                :src="effectiveLogoUrl"
                cover
              />
              <VIcon
                v-else
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
                {{ isNewSupplier ? "Registro de aliado comercial" : ((isRestaurant ? (formData.type === 'externo' ? 'Proveedor de Gastos' : 'Proveedor de Insumos') : (formData.type === 'externo' ? 'Proveedor de Gastos' : 'Droguería'))) + (formData.name ? ' | ' + formData.name : '') }}
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

      <!-- Contenido del Formulario en 1 Vista Compacta y 1 Sola Card -->
      <VCardText class="pa-4 pa-sm-5 bg-light flex-grow-1 overflow-y-auto">
        <VForm @submit.prevent="submitForm">
          <VCard variant="outlined" class="pa-4 rounded-lg bg-surface border-card">
            <!-- Selector Compacto de Tipo de Proveedor (solo al crear en modo normal) -->
            <div v-if="isNewSupplier && !isRestaurant && !props.isMaster" class="d-flex justify-center mb-4">
              <VBtnToggle
                v-model="formData.type"
                mandatory
                color="primary"
                variant="outlined"
                density="compact"
                divided
                class="rounded-lg"
              >
                <VBtn value="drogueria" size="small" class="text-none font-weight-bold px-4">
                  <VIcon start icon="tabler-truck-delivery" size="16" />
                  Droguería / Mercancía
                </VBtn>
                <VBtn value="externo" size="small" class="text-none font-weight-bold px-4">
                  <VIcon start icon="tabler-receipt-tax" size="16" />
                  Gastos y Servicios
                </VBtn>
              </VBtnToggle>
            </div>

            <!-- Buscador Predictivo en Red Master (Search-First) -->
            <div v-if="showMasterSearch" class="mb-4">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-database-search" color="primary" size="18" />
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                  Buscar en Red Master
                </span>
              </div>

          <VAutocomplete
            v-model="selectedMasterSupplier"
            v-model:search="masterSearchQuery"
            :items="masterSearchResults"
            :loading="isSearchingMaster"
            item-title="name"
            item-value="master_id"
            return-object
            no-filter
            clearable
            density="compact"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-search"
            label="Buscar proveedor por nombre o RIF"
            @update:search="handleMasterSearchInput"
            @update:model-value="handleSelectMasterSupplier"
          >
            <template #item="{ props: itemProps, item }">
              <VListItem
                v-bind="itemProps"
                :subtitle="masterOptionSubtitle(item.raw)"
              >
                <template #prepend>
                  <VAvatar size="32" color="primary" variant="tonal" rounded="lg" class="me-2">
                    <VImg v-if="item.raw.logo_url" :src="item.raw.logo_url" cover />
                    <VIcon v-else icon="tabler-building-store" size="18" />
                  </VAvatar>
                </template>
                <template #append>
                  <VChip
                    v-if="item.raw.is_installed"
                    color="warning"
                    size="x-small"
                    variant="tonal"
                  >
                    Ya en tu catálogo
                  </VChip>
                  <VChip
                    v-else-if="item.raw.has_excel_structure"
                    color="success"
                    size="x-small"
                    variant="tonal"
                    prepend-icon="tabler-file-spreadsheet"
                  >
                    Excel listo
                  </VChip>
                  <VChip
                    v-else
                    color="info"
                    size="x-small"
                    variant="tonal"
                  >
                    Disponible
                  </VChip>
                </template>
              </VListItem>
            </template>

            <template #no-data>
              <div class="pa-3 d-flex align-center justify-space-between flex-wrap gap-2">
                <span class="text-caption text-medium-emphasis">
                  {{ isSearchingMaster ? 'Buscando en Red Master...' : 'No se encontraron coincidencias en la Red Master.' }}
                </span>
                <VBtn
                  v-if="masterSearchQuery && masterSearchQuery.trim().length > 1 && !isSearchingMaster"
                  variant="tonal"
                  color="primary"
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="useSearchTextAsNewSupplier"
                >
                  Crear "{{ masterSearchQuery.trim() }}" como nuevo
                </VBtn>
              </div>
            </template>
          </VAutocomplete>

          <!-- Aviso si el proveedor ya pertenece al tenant actual -->
          <VAlert
            v-if="alreadyLocalMasterSupplier"
            type="warning"
            variant="tonal"
            density="compact"
            class="mt-3 rounded-lg"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <span class="text-caption font-weight-medium">
                <strong>{{ alreadyLocalMasterSupplier.name }}</strong> ya está registrado en tu catálogo (#{{ alreadyLocalMasterSupplier.tenant_supplier_id }}).
              </span>
              <VBtn
                color="warning"
                variant="flat"
                size="x-small"
                prepend-icon="tabler-edit"
                @click="handleOpenExistingFromMaster"
              >
                Editar existente
              </VBtn>
            </div>
          </VAlert>

              <VDivider class="mt-4" />
            </div>

            <!-- Campos en 2 Columnas con Labels Flotantes -->
            <VRow dense class="gy-2">
              <!-- Campos exclusivos de Master -->
              <VCol cols="12" sm="6" v-if="authStore.isAdmin && props.isMaster">
                <div class="d-flex align-center justify-space-between pa-2 px-3 rounded-lg border bg-light-surface h-100">
                  <span class="text-caption font-weight-bold">
                    {{ formData.is_active ? 'Activo en Master' : 'Inactivo en Master' }}
                  </span>
                  <VSwitch
                    v-model="formData.is_active"
                    color="success"
                    hide-details
                    density="compact"
                  />
                </div>
              </VCol>

              <VCol cols="12" sm="6" v-if="props.isMaster">
                <div class="d-flex align-center gap-3 pa-2 rounded-lg border bg-light-surface h-100">
                  <VAvatar size="38" color="primary" variant="tonal" rounded="lg" class="flex-shrink-0">
                    <VImg v-if="effectiveLogoUrl" :src="effectiveLogoUrl" cover />
                    <VIcon v-else icon="tabler-photo" size="20" />
                  </VAvatar>
                  <div class="flex-grow-1">
                    <VFileInput
                      :model-value="logoFile ? [logoFile] : []"
                      label="Logo Oficial (Master)"
                      accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml"
                      prepend-icon=""
                      prepend-inner-icon="tabler-upload"
                      density="compact"
                      variant="outlined"
                      clearable
                      hide-details="auto"
                      :error-messages="formErrors.logo"
                      @update:model-value="handleLogoFileChange"
                      @click:clear="clearSelectedLogo"
                    />
                  </div>
                </div>
              </VCol>

              <VCol cols="12" v-if="props.isMaster">
                <VSelect
                  v-model="formData.business_types"
                  :items="availableBusinessTypes"
                  label="Disponible para Tipos de Negocio"
                  prepend-inner-icon="tabler-building-community"
                  density="compact"
                  variant="outlined"
                  multiple
                  chips
                  closable-chips
                  hide-details="auto"
                  :error-messages="formErrors.business_types"
                  :readonly="isReadOnly"
                />
              </VCol>

              <!-- Campos Generales en 2 Columnas -->
              <VCol cols="12" sm="6" v-if="isFieldVisible('name')">
                <VTextField
                  v-model="formData.name"
                  label="Nombre Comercial *"
                  prepend-inner-icon="tabler-building-store"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.name"
                  :readonly="isReadOnly"
                />
              </VCol>

              <VCol cols="12" sm="6" v-if="isFieldVisible('rif')">
                <VTextField
                  v-model="formData.rif"
                  label="RIF"
                  prepend-inner-icon="tabler-id-badge-2"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :loading="isLookingUpMaster"
                  :error-messages="formErrors.rif"
                  :readonly="isRifLocked"
                  :hint="isRifLocked && !isReadOnly ? 'No editable (definido oficialmente)' : undefined"
                  @update:model-value="handleRifInput"
                />
              </VCol>

              <VCol cols="12" sm="6" v-if="isFieldVisible('social_reason')">
                <VTextField
                  v-model="formData.social_reason"
                  label="Razón Social"
                  prepend-inner-icon="tabler-certificate"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.social_reason"
                  :readonly="isSocialReasonLocked"
                  :hint="isSocialReasonLocked && !isReadOnly ? 'No editable (definido oficialmente)' : undefined"
                />
              </VCol>

              <VCol cols="12" sm="6" v-if="!isRestaurant && formData.type !== 'externo'">
                <VTextField
                  v-model="formData.payment_email"
                  label="Correo para Notificación de Pagos"
                  type="email"
                  prepend-inner-icon="tabler-mail-dollar"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.payment_email"
                  :readonly="isReadOnly"
                />
              </VCol>

              <VCol cols="12" sm="6" v-if="isFieldVisible('sales_phone')">
                <VTextField
                  v-model="formData.sales_phone"
                  label="Teléfono Ventas"
                  type="tel"
                  prepend-inner-icon="tabler-phone"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.sales_phone"
                  :readonly="isReadOnly"
                />
              </VCol>

              <VCol cols="12" sm="6" v-if="isFieldVisible('collections_phone')">
                <VTextField
                  v-model="formData.collections_phone"
                  label="Teléfono Cobranza"
                  type="tel"
                  prepend-inner-icon="tabler-phone-incoming"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.collections_phone"
                  :readonly="isReadOnly"
                />
              </VCol>

              <VCol cols="12" v-if="isFieldVisible('address')">
                <VTextField
                  v-model="formData.address"
                  label="Dirección Fiscal"
                  prepend-inner-icon="tabler-map-pin"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                  :error-messages="formErrors.address"
                  :readonly="isReadOnly"
                />
              </VCol>

              <!-- Switch de Facturación Fiscal al final -->
              <VCol cols="12" class="mt-2">
                <div class="d-flex align-center justify-space-between px-3 py-2 rounded-lg border bg-light-surface">
                  <div class="d-flex flex-column">
                    <span class="text-caption font-weight-bold leading-tight">Facturación Fiscal Formal</span>
                    <span class="text-xxs text-disabled leading-tight">
                      {{ formData.is_fiscal ? 'Requiere N° de control y desglose formal' : 'Facturación simplificada / informal' }}
                    </span>
                  </div>
                  <VSwitch
                    v-model="formData.is_fiscal"
                    color="primary"
                    hide-details
                    density="compact"
                  />
                </div>
              </VCol>
            </VRow>
          </VCard>
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
              :disabled="Boolean(alreadyLocalMasterSupplier)"
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
