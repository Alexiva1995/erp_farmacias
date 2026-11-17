<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, ref, watch } from "vue";
import SupplierConnectionHistoryDialog from "@/components/dialogs/SupplierConnectionHistoryDialog.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  isMaster: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

// ─── Estado ───────────────────────────────────────────────────────────────────
const loading = ref(false);
const saving = ref(false);
const deletingProtocol = ref(false);
const isHistoryOpen = ref(false);
const showPassword = ref(false);
const showFtpOrdersPassword = ref(false);

const connectionsMap = ref({});
const masterTemplatesMap = ref({});
const masterEnabledTypes = ref([]);
const hasInheritedTechnicalConfig = computed(() =>
  !isMasterContext.value
  && Boolean(
    masterTemplatesMap.value[form.value.type]?.host
    || connectionsMap.value[form.value.type]?.host,
  ),
);
const allowedTypesFromMaster = ref(["file"]);
const isMasterContext = computed(() => props.isMaster);

const PROTOCOL_META = {
  api: { title: "HTTP / API", value: "api", icon: "tabler-api" },
  http: { title: "HTTP / API", value: "http", icon: "tabler-api" },
  ftp: { title: "FTP", value: "ftp", icon: "tabler-server" },
  sftp: { title: "SFTP", value: "sftp", icon: "tabler-shield-lock" },
  file: { title: "Correo Gmail (Excel)", value: "file", icon: "tabler-mail" },
  email: { title: "Correo Gmail (Excel)", value: "email", icon: "tabler-mail" },
};

const getDefaultFormForType = (type) => {
  const tpl = masterTemplatesMap.value[type] || {};
  const defaultPort = type === "sftp" ? 22 : (type === "ftp" ? 21 : "");

  return {
    type,
    host: tpl.host ?? "",
    port: tpl.port ?? defaultPort,
    username: "",
    password: "",
    path: tpl.path ?? "",
    invoice_path: tpl.invoice_path ?? "",
    pasv: tpl.pasv ?? true,
    has_header: tpl.has_header ?? false,
    ftp_orders_enabled: false,
    ftp_orders_host: "",
    ftp_orders_port: 21,
    ftp_orders_username: "",
    ftp_orders_password: "",
    ftp_orders_path: "",
    credit_days_email: "",
    credit_days_cod_col: tpl.credit_days_cod_col ?? "",
    credit_days_col: tpl.credit_days_col ?? "",
  };
};

const form = ref(getDefaultFormForType("file"));
const hasExistingPassword = ref(false);
const hasExistingFtpOrdersPassword = ref(false);
const lastConnection = ref(null);
const errors = ref({});

const isFtp = computed(() => ["ftp", "sftp"].includes(form.value.type));
const isHttp = computed(() => ["http", "api"].includes(form.value.type));
const isEmail = computed(() => ["file", "email"].includes(form.value.type));

// En Master el administrador decide qué canales habilitar; en Tenant solo aparecen los autorizados por Master
const typeOptions = computed(() => {
  const rawList = isMasterContext.value
    ? ["api", "ftp", "sftp", "file"]
    : (allowedTypesFromMaster.value?.length ? allowedTypesFromMaster.value : ["file"]);

  const seen = new Set();
  const options = [];
  for (const rawType of rawList) {
    const t = String(rawType || "").toLowerCase().trim();
    if (!t || t.includes("bot") || seen.has(t)) continue;
    seen.add(t);
    options.push(PROTOCOL_META[t] || { title: t.toUpperCase(), value: t, icon: "tabler-plug" });
  }
  return options;
});

const hasAvailableProtocols = computed(() => typeOptions.value.length > 0);

const activeMasterTemplate = computed(() => masterTemplatesMap.value[form.value.type] || null);

const loadFormForType = (type) => {
  const existing = connectionsMap.value[type];
  const tpl = masterTemplatesMap.value[type] || {};
  const defaultPort = type === "sftp" ? 22 : (type === "ftp" ? 21 : "");

  if (existing) {
    form.value = {
      type: existing.type || type,
      host: existing.host || tpl.host || "",
      port: existing.port ?? tpl.port ?? defaultPort,
      username: existing.username ?? "",
      password: "",
      path: existing.path || tpl.path || "",
      invoice_path: existing.invoice_path || tpl.invoice_path || "",
      pasv: existing.pasv ?? tpl.pasv ?? true,
      has_header: existing.has_header ?? tpl.has_header ?? false,
      ftp_orders_enabled: existing.ftp_orders_enabled ?? Boolean(connectionsMap.value.ftp && type !== "ftp" && type !== "sftp"),
      ftp_orders_host: existing.ftp_orders_host ?? connectionsMap.value.ftp?.host ?? "",
      ftp_orders_port: existing.ftp_orders_port ?? connectionsMap.value.ftp?.port ?? 21,
      ftp_orders_username: existing.ftp_orders_username ?? connectionsMap.value.ftp?.username ?? "",
      ftp_orders_password: "",
      ftp_orders_path: existing.ftp_orders_path ?? connectionsMap.value.ftp?.path ?? "",
      credit_days_email: existing.credit_days_email ?? "",
      credit_days_cod_col: existing.credit_days_cod_col || tpl.credit_days_cod_col || "",
      credit_days_col: existing.credit_days_col || tpl.credit_days_col || "",
    };
    hasExistingPassword.value = existing.has_password ?? false;
    lastConnection.value = existing.last_connection ?? null;
  } else {
    form.value = getDefaultFormForType(type);
    hasExistingPassword.value = false;
    lastConnection.value = null;
  }
};

const switchType = (newType) => {
  if (!newType || form.value.type === newType) return;
  errors.value = {};
  loadFormForType(newType);
};

// ─── Métodos API ──────────────────────────────────────────────────────────────
const getRequestConfig = () => {
  return isMasterContext.value
    ? { headers: { "X-Master-Catalog-Context": "1" } }
    : {};
};

const fetchConfig = async () => {
  if (!props.supplier?.id) return;
  loading.value = true;
  errors.value = {};

  try {
    const { data } = await axios.get(
      `/suppliers/${props.supplier.id}/connection-config`,
      getRequestConfig(),
    );

    const rawConnections = data?.connections || {};
    const filteredConnections = {};
    for (const [k, v] of Object.entries(rawConnections)) {
      if (!String(k).toLowerCase().includes("bot")) {
        filteredConnections[k] = v;
      }
    }
    connectionsMap.value = filteredConnections;
    masterTemplatesMap.value = data?.master_templates || {};
    masterEnabledTypes.value = Object.keys(filteredConnections);

    if (Array.isArray(data?.allowed_types)) {
      allowedTypesFromMaster.value = data.allowed_types.filter(
        (t) => !String(t).toLowerCase().includes("bot"),
      );
    } else {
      allowedTypesFromMaster.value = ["file"];
    }

    const availableConfigured = Object.keys(connectionsMap.value);
    const firstAllowed = typeOptions.value[0]?.value || "file";
    const initialType =
      typeOptions.value.find((opt) => availableConfigured.includes(opt.value))?.value
      || firstAllowed;

    loadFormForType(initialType);
    hasExistingFtpOrdersPassword.value = data?.ftp_orders_has_pass ?? false;
  } catch (error) {
    console.error("Error cargando configuración de conexiones:", error);
    toast.error("No se pudo cargar la configuración de conexiones.");
  } finally {
    loading.value = false;
  }
};

const saveMasterChannels = async () => {
  const previousTypes = Object.keys(connectionsMap.value);
  const selectedTypes = [...masterEnabledTypes.value];

  const toEnable = selectedTypes.filter((t) => !previousTypes.includes(t));
  const toDisable = previousTypes.filter((t) => !selectedTypes.includes(t));

  for (const type of toEnable) {
    const payload = {
      type,
      host: type === "file" ? `${props.supplier?.name || "Proveedor"} (Excel)` : null,
    };
    await axios.post(`/suppliers/${props.supplier.id}/connection-config`, payload, getRequestConfig());
  }

  for (const type of toDisable) {
    await axios.delete(`/suppliers/${props.supplier.id}/connection-config/${type}`, getRequestConfig());
  }

  toast.success("Canales habilitados actualizados en el Catálogo Maestro.");
  emit("saved");
  close();
};

const saveConfig = async () => {
  errors.value = {};

  if (isMasterContext.value) {
    saving.value = true;
    try {
      await saveMasterChannels();
    } catch (err) {
      toast.error(err.response?.data?.message || "Error al actualizar los canales en Master.");
    } finally {
      saving.value = false;
    }
    return;
  }

  if (!isMasterContext.value && !form.value.username && form.value.type !== "file") {
    errors.value = { username: ["El usuario o código de cliente es obligatorio."] };
    toast.error("Por favor ingresa tu usuario o código de cliente.");
    return;
  }

  saving.value = true;
  try {
    const payload = { ...form.value };

    if (payload.type === "file") {
      payload.host = isMasterContext.value
        ? `${props.supplier?.name || "Proveedor"} (Excel)`
        : (payload.username || payload.host);
      payload.port = null;
      payload.path = null;
      payload.invoice_path = null;
      delete payload.password;
    }

    if (isMasterContext.value) {
      delete payload.username;
      delete payload.password;
      delete payload.ftp_orders_enabled;
      delete payload.ftp_orders_host;
      delete payload.ftp_orders_port;
      delete payload.ftp_orders_username;
      delete payload.ftp_orders_password;
      delete payload.ftp_orders_path;
    } else {
      if (!payload.password && hasExistingPassword.value) {
        delete payload.password;
      }
      if (!payload.ftp_orders_password && hasExistingFtpOrdersPassword.value) {
        delete payload.ftp_orders_password;
      }
    }

    const { data } = await axios.post(
      `/suppliers/${props.supplier.id}/connection-config`,
      payload,
      getRequestConfig(),
    );

    if (data?.connection) {
      connectionsMap.value[data.connection.type] = {
        ...connectionsMap.value[data.connection.type],
        ...data.connection,
      };
      form.value.username = data.connection.username ?? form.value.username;
      hasExistingPassword.value = data.connection.has_password ?? hasExistingPassword.value;
    }

    toast.success(data?.message || `Configuración de ${form.value.type.toUpperCase()} guardada correctamente.`);
    emit("saved");
    close();
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      toast.error(err.response?.data?.message || "Revisa los campos marcados.");
    } else {
      toast.error(err.response?.data?.message || "Error al guardar la configuración.");
    }
  } finally {
    saving.value = false;
  }
};

const deleteProtocolConfig = async () => {
  if (!props.supplier?.id || !form.value.type) return;

  const typeName = form.value.type.toUpperCase();
  const result = await Swal.fire({
    title: `¿Eliminar conexión ${typeName}?`,
    text: `Esta acción removerá la configuración de tipo ${typeName} para este proveedor.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  deletingProtocol.value = true;
  try {
    await axios.delete(
      `/suppliers/${props.supplier.id}/connection-config/${form.value.type}`,
      getRequestConfig(),
    );
    toast.success(`Conexión ${typeName} eliminada correctamente.`);
    delete connectionsMap.value[form.value.type];
    loadFormForType(form.value.type);
    emit("saved");
  } catch (err) {
    console.error("Error al eliminar conexión:", err);
    toast.error(err.response?.data?.message || "No se pudo eliminar la conexión.");
  } finally {
    deletingProtocol.value = false;
  }
};

const close = () => {
  emit("update:modelValue", false);
  errors.value = {};
};

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) fetchConfig();
  },
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="850px"
    :fullscreen="$vuetify.display.mobile"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Encabezado -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary">
            <VImg v-if="props.supplier?.logo_url" :src="props.supplier.logo_url" :alt="props.supplier?.name" cover />
            <VIcon v-else icon="tabler-plug" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              {{ isMasterContext ? 'Canales Habilitados (Master)' : 'Configuración de Conexión' }}
            </h2>
            <span class="text-super-xs opacity-90 font-weight-bold uppercase letter-spacing-1">
              {{ props.supplier?.name ?? 'Proveedor' }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <!-- Barra de Estado / Protocolos -->
      <div class="px-4 py-2 bg-surface border-b flex-shrink-0">
        <div class="d-flex align-center justify-space-between flex-wrap gap-2" :class="{ 'mb-1': !isMasterContext && hasAvailableProtocols && typeOptions.length > 1 }">
          <span v-if="isMasterContext" class="text-xxs font-weight-black text-disabled text-uppercase letter-spacing-1">
            Habilitar Canales para Farmacias:
          </span>
          <VSpacer v-else />
          <div class="d-flex align-center gap-2">
            <template v-if="!isMasterContext">
              <span v-if="lastConnection" class="text-xxs text-success font-weight-bold d-flex align-center gap-1">
                <VIcon icon="tabler-circle-check" size="14" color="success" />
                Última sinc: {{ lastConnection }}
              </span>
              <span v-else class="text-xxs text-medium-emphasis">
                Sin sincronizaciones previas
              </span>
              <VBtn
                variant="text"
                color="primary"
                density="compact"
                size="small"
                prepend-icon="tabler-history"
                class="font-weight-bold text-xxs px-1 text-none"
                @click="isHistoryOpen = true"
              >
                Historial
              </VBtn>
            </template>
            <VChip v-else size="x-small" color="secondary" variant="tonal" class="font-weight-bold">
              Modo Centro de Datos
            </VChip>
          </div>
        </div>

        <VTabs
          v-if="!isMasterContext && hasAvailableProtocols && typeOptions.length > 1"
          :model-value="form.type"
          color="primary"
          density="compact"
          class="protocol-tabs"
          height="38"
          @update:model-value="switchType"
        >
          <VTab
            v-for="opt in typeOptions"
            :key="opt.value"
            :value="opt.value"
            class="protocol-tab-item text-xs font-weight-bold text-none rounded-lg me-1"
          >
            <VIcon :icon="opt.icon" size="16" class="me-1" />
            {{ opt.title }}
            <VChip
              v-if="connectionsMap[opt.value]"
              size="x-small"
              color="success"
              variant="flat"
              class="ms-1 px-1 font-weight-bold protocol-badge"
            >
              ✓
            </VChip>
          </VTab>
        </VTabs>
      </div>

      <!-- Contenido de Formulario -->
      <VCardText class="pa-4 bg-background flex-grow-1 overflow-y-auto">
        <div v-if="loading" class="d-flex justify-center align-center py-10">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <div v-else-if="!hasAvailableProtocols" class="text-center py-10">
          <VIcon icon="tabler-plug-off" size="40" color="disabled" class="mb-2" />
          <p class="text-body-2 font-weight-bold text-high-emphasis mb-1">
            Este proveedor no posee canales de sincronización habilitados
          </p>
          <p class="text-caption text-medium-emphasis">
            Solo los proveedores comerciales con canales autorizados desde el Catálogo Maestro admiten sincronización automática.
          </p>
        </div>

        <VCard v-else-if="isMasterContext" variant="outlined" class="pa-4 bg-surface rounded-lg border-card">
          <div class="d-flex align-center gap-2 mb-3">
            <VIcon icon="tabler-plug-connected" color="primary" size="20" />
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
              Canales Permitidos para Farmacias
            </span>
          </div>
          <p class="text-xs text-medium-emphasis mb-3">
            Marca los protocolos que este proveedor soporta. Las farmacias solo verán y configurarán sus credenciales en los canales habilitados aquí.
          </p>
          <VRow dense>
            <VCol v-for="opt in typeOptions" :key="opt.value" cols="12" sm="6">
              <VCheckbox
                v-model="masterEnabledTypes"
                :value="opt.value"
                :label="opt.title"
                color="primary"
                density="compact"
                hide-details
              />
            </VCol>
          </VRow>
        </VCard>

        <VForm v-else @submit.prevent="saveConfig">
          <VAlert
            v-if="isMasterContext"
            color="info"
            variant="tonal"
            density="compact"
            icon="tabler-shield-check"
            class="mb-3 rounded-lg text-xs"
          >
            Al guardar este protocolo en el Master, quedará habilitado automáticamente como canal oficial para todas las farmacias conectadas. No guarde credenciales privadas de clientes aquí.
          </VAlert>

          <VAlert
            v-else-if="activeMasterTemplate && activeMasterTemplate.has_structure"
            color="success"
            variant="tonal"
            density="compact"
            icon="tabler-database-check"
            class="mb-3 rounded-lg text-xs"
          >
            Plantilla de servidor y estructura de archivo preconfigurada por el Master. Solo complete sus credenciales o carpeta asignada.
          </VAlert>

          <!-- 1. Correo Gmail (Excel) -->
          <template v-if="isEmail">
            <VCard variant="outlined" class="pa-4 bg-surface rounded-lg border-card mb-3">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-mail" color="primary" size="20" />
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                  {{ isMasterContext ? 'Canal Correo / Archivo Excel Homologado' : 'Extracción Automática desde Gmail' }}
                </span>
              </div>
              <p class="text-xs text-medium-emphasis mb-3">
                {{
                  isMasterContext
                    ? 'Habilita la recepción e importación automática de listas de precios e inventarios en Excel para este proveedor.'
                    : 'El sistema revisará automáticamente la bandeja de entrada y procesará los archivos Excel adjuntos recibidos desde este remitente.'
                }}
              </p>

              <VRow v-if="!isMasterContext" dense>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.username"
                    label="Correo del Remitente del Proveedor *"
                    placeholder="pedidos@proveedor.com"
                    prepend-inner-icon="tabler-mail"
                    :error-messages="errors.username"
                  />
                </VCol>
              </VRow>
            </VCard>
          </template>

          <!-- 2. FTP / SFTP / HTTP API -->
          <template v-else>
            <VCard variant="outlined" class="pa-4 bg-surface rounded-lg border-card mb-3">
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center gap-2">
                  <VIcon :icon="isFtp ? 'tabler-server' : 'tabler-api'" color="primary" size="20" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    {{ isFtp ? 'Servidor ' + form.type.toUpperCase() : 'Endpoint y Credenciales API' }}
                  </span>
                </div>
              </div>

              <VRow dense>
                <!-- Servidor y Puerto (oculto para tenant si ya viene preconfigurado) -->
                <template v-if="!hasInheritedTechnicalConfig">
                  <VCol cols="12" :md="isFtp ? 8 : 12">
                    <AppTextField
                      v-model="form.host"
                      :label="isFtp ? 'Host / Servidor IP *' : 'URL Endpoint Login / Base *'"
                      :placeholder="isFtp ? 'Ej: ftp.proveedor.com' : 'Ej: https://api.proveedor.com/login'"
                      prepend-inner-icon="tabler-server"
                      :error-messages="errors.host"
                    />
                  </VCol>
                  <VCol v-if="isFtp" cols="12" md="4">
                    <AppTextField
                      v-model.number="form.port"
                      type="number"
                      label="Puerto *"
                      :placeholder="form.type === 'sftp' ? '22' : '21'"
                      prepend-inner-icon="tabler-hash"
                      :error-messages="errors.port"
                    />
                  </VCol>
                </template>

                <!-- Credenciales privadas (solo Tenant) -->
                <template v-if="!isMasterContext">
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.username"
                      label="Usuario / Código Cliente *"
                      placeholder="Ingresa tu usuario o código asignado"
                      prepend-inner-icon="tabler-user"
                      :error-messages="errors.username"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      :label="hasExistingPassword ? 'Contraseña / Token (dejar vacío para mantener)' : 'Contraseña / Token *'"
                      :placeholder="hasExistingPassword ? '••••••••' : 'Contraseña o token de acceso'"
                      prepend-inner-icon="tabler-lock"
                      :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      :error-messages="errors.password"
                      @click:append-inner="showPassword = !showPassword"
                    />
                  </VCol>
                </template>

                <!-- Rutas de Archivos / Endpoints (oculto para tenant si ya viene preconfigurado) -->
                <template v-if="!hasInheritedTechnicalConfig">
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.path"
                      :label="isFtp ? 'Ruta Archivo Productos / Inventario' : 'Endpoint Productos'"
                      :placeholder="isFtp ? 'Ej: Existencia/inventario.txt' : 'Ej: /api/v1/articulos'"
                      prepend-inner-icon="tabler-folder"
                      :error-messages="errors.path"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.invoice_path"
                      :label="isFtp ? 'Ruta Directorio Facturas' : 'Endpoint Facturas'"
                      :placeholder="isFtp ? 'Ej: Facturas/7586' : 'Ej: /api/v1/facturas'"
                      prepend-inner-icon="tabler-file-invoice"
                      :error-messages="errors.invoice_path"
                    />
                  </VCol>
                </template>
              </VRow>

              <!-- Opciones FTP -->
              <div v-if="isFtp && !hasInheritedTechnicalConfig" class="d-flex align-center flex-wrap justify-space-between gap-3 pt-2 mt-2 border-t">
                <div class="d-flex align-center gap-2">
                  <VSwitch v-model="form.pasv" color="primary" hide-details density="compact" />
                  <span class="text-xs font-weight-bold text-high-emphasis">Modo Pasivo (PASV)</span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VSwitch v-model="form.has_header" color="primary" hide-details density="compact" />
                  <span class="text-xs font-weight-bold text-high-emphasis">Archivo con encabezado</span>
                </div>
              </div>
            </VCard>
          </template>

          <!-- Enlace de Días de Crédito vía Correo (Opcional para canales no-email en Tenant) -->
          <div v-if="!isMasterContext && !isEmail" class="mt-2">
            <VCard variant="outlined" class="pa-4 bg-surface rounded-lg border-card mb-3">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon icon="tabler-clock-dollar" color="primary" size="18" />
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                  Enlace de Días de Crédito vía Correo (Opcional)
                </span>
              </div>
              <p class="text-xs text-medium-emphasis mb-3">
                Si el canal {{ form.type.toUpperCase() }} no incluye los días de crédito por producto, configura el correo y columnas del Excel adjunto para enlazarlos por <strong>Código de Barra</strong>.
              </p>

              <VRow dense>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.credit_days_email"
                    label="Correo del Proveedor"
                    placeholder="catalogo@proveedor.com"
                    prepend-inner-icon="tabler-mail"
                    :error-messages="errors.credit_days_email"
                  />
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <AppTextField
                    v-model="form.credit_days_cod_col"
                    label="Columna Código de Barra"
                    placeholder="Ej: A"
                    prepend-inner-icon="tabler-barcode"
                    :error-messages="errors.credit_days_cod_col"
                  />
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <AppTextField
                    v-model="form.credit_days_col"
                    label="Columna Días Crédito"
                    placeholder="Ej: F"
                    prepend-inner-icon="tabler-clock-dollar"
                    :error-messages="errors.credit_days_col"
                  />
                </VCol>
              </VRow>
            </VCard>
          </div>

          <!-- Transmisión complementaria de pedidos FTP si aplica cuando la principal es API -->
          <div v-if="!isMasterContext && isHttp" class="mt-2">
            <VCard variant="outlined" class="pa-4 bg-surface rounded-lg border-card">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-upload" color="secondary" size="18" />
                  <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">
                    Envío de Pedidos por FTP (Opcional)
                  </span>
                </div>
                <VSwitch
                  v-model="form.ftp_orders_enabled"
                  color="secondary"
                  hide-details
                  density="compact"
                  :label="form.ftp_orders_enabled ? 'Habilitado' : 'Deshabilitado'"
                />
              </div>

              <div v-if="form.ftp_orders_enabled" class="pt-2">
                <VRow dense>
                  <VCol cols="12" md="8">
                    <AppTextField
                      v-model="form.ftp_orders_host"
                      label="Servidor FTP Pedidos *"
                      placeholder="ftp.proveedor.com"
                      :error-messages="errors.ftp_orders_host"
                    />
                  </VCol>
                  <VCol cols="12" md="4">
                    <AppTextField
                      v-model.number="form.ftp_orders_port"
                      type="number"
                      label="Puerto *"
                      placeholder="21"
                      :error-messages="errors.ftp_orders_port"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.ftp_orders_username"
                      label="Usuario FTP *"
                      :error-messages="errors.ftp_orders_username"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.ftp_orders_password"
                      :type="showFtpOrdersPassword ? 'text' : 'password'"
                      :label="hasExistingFtpOrdersPassword ? 'Contraseña FTP (dejar vacío para mantener)' : 'Contraseña FTP'"
                      :append-inner-icon="showFtpOrdersPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      :error-messages="errors.ftp_orders_password"
                      @click:append-inner="showFtpOrdersPassword = !showFtpOrdersPassword"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextField
                      v-model="form.ftp_orders_path"
                      label="Ruta Carpeta de Pedidos"
                      placeholder="/pedidos"
                      :error-messages="errors.ftp_orders_path"
                    />
                  </VCol>
                </VRow>
              </div>
            </VCard>
          </div>
        </VForm>
      </VCardText>

      <!-- Acciones -->
      <VCardActions class="pa-3 bg-surface border-t flex-column gap-2">
        <div v-if="hasAvailableProtocols && connectionsMap[form.type]" class="w-100 d-flex justify-start">
          <VBtn
            color="error"
            variant="text"
            height="32"
            prepend-icon="tabler-trash"
            class="font-weight-bold rounded-lg text-xs px-2 text-none"
            :loading="deletingProtocol"
            @click="deleteProtocolConfig"
          >
            {{ isMasterContext ? `Deshabilitar ${form.type.toUpperCase()}` : `Eliminar protocolo ${form.type.toUpperCase()}` }}
          </VBtn>
        </div>

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
              v-if="hasAvailableProtocols"
              color="primary"
              variant="flat"
              size="large"
              block
              height="44"
              class="font-weight-black rounded-lg shadow-primary text-button"
              :loading="saving"
              :disabled="saving"
              @click="saveConfig"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <!-- Diálogo de Historial de Conexiones -->
    <SupplierConnectionHistoryDialog
      v-if="!isMasterContext"
      v-model="isHistoryOpen"
      :supplier="props.supplier"
    />
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-secondary)), rgb(var(--v-theme-primary))) !important;
}

.detail-dialog-card {
  border-radius: 14px !important;
}

.protocol-tabs {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-radius: 8px;
  padding: 2px;
}

.protocol-tab-item {
  border-radius: 6px !important;
}

.protocol-badge {
  font-size: 0.6rem !important;
  height: 16px !important;
}

.border-card {
  border-color: rgba(var(--v-border-color), 0.15) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1) !important;
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
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
