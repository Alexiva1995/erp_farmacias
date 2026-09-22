<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import SupplierConnectionHistoryDialog from "@/components/dialogs/SupplierConnectionHistoryDialog.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "saved"]);

// ─── Estado ───────────────────────────────────────────────────────────────────
const loading = ref(false);
const saving = ref(false);
const isHistoryOpen = ref(false);
const showPassword = ref(false);
const showFtpOrdersPassword = ref(false);

const connectionsMap = ref({});

const getDefaultFormForType = (type) => ({
  type: type,
  host: type === "dronena_bot" ? "https://www.dronena.com/NuevaExperiencia/" : "",
  port: type === "sftp" ? 22 : (type === "ftp" ? 21 : ""),
  username: "",
  password: "",
  path: "",
  invoice_path: "",
  pasv: true,
  has_header: false,
  ftp_orders_enabled: false,
  ftp_orders_host: "",
  ftp_orders_port: 21,
  ftp_orders_username: "",
  ftp_orders_password: "",
  ftp_orders_path: "",
});

const form = ref(getDefaultFormForType("ftp"));

const hasExistingPassword = ref(false);
const hasExistingFtpOrdersPassword = ref(false);
const lastConnection = ref(null);
const errors = ref({});

const isFtp = computed(() => ["ftp", "sftp"].includes(form.value.type));
const isHttp = computed(() => ["http", "api"].includes(form.value.type));
const isEmail = computed(() => ["file", "email"].includes(form.value.type));
const isDronenaBot = computed(() => form.value.type === "dronena_bot" || (props.supplier?.name && props.supplier.name.toUpperCase().includes("NENA")));
const isMafarta = computed(() => props.supplier?.name && (props.supplier.name.toUpperCase().includes("MAFARTA") || props.supplier.name.toUpperCase().includes("COBECA") || props.supplier.id === 23));

const typeOptions = computed(() => {
  const options = [
    { title: "FTP", value: "ftp", icon: "tabler-server" },
    { title: "SFTP", value: "sftp", icon: "tabler-lock" },
    { title: "HTTP / API", value: "api", icon: "tabler-api" },
    { title: "Correo Gmail", value: "file", icon: "tabler-mail" },
  ];

  if (props.supplier?.name && (props.supplier.name.toUpperCase().includes("NENA") || props.supplier.name.toUpperCase().includes("DRONENA"))) {
    options.unshift({
      title: "Bot Dronena",
      value: "dronena_bot",
      icon: "tabler-robot",
    });
  }

  return options;
});

const loadFormForType = (type) => {
  const existing = connectionsMap.value[type];
  if (existing) {
    form.value = {
      type: existing.type || type,
      host: existing.host ?? "",
      port: existing.port ?? (type === "sftp" ? 22 : (type === "ftp" ? 21 : "")),
      username: existing.username ?? "",
      password: "",
      path: existing.path ?? "",
      invoice_path: existing.invoice_path ?? "",
      pasv: existing.pasv ?? true,
      has_header: existing.has_header ?? false,
      ftp_orders_enabled: existing.ftp_orders_enabled ?? (connectionsMap.value["ftp"] ? true : false),
      ftp_orders_host: existing.ftp_orders_host ?? connectionsMap.value["ftp"]?.host ?? "",
      ftp_orders_port: existing.ftp_orders_port ?? connectionsMap.value["ftp"]?.port ?? 21,
      ftp_orders_username: existing.ftp_orders_username ?? connectionsMap.value["ftp"]?.username ?? "",
      ftp_orders_password: "",
      ftp_orders_path: existing.ftp_orders_path ?? connectionsMap.value["ftp"]?.path ?? "",
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

// ─── Métodos ──────────────────────────────────────────────────────────────────
const fetchConfig = async () => {
  if (!props.supplier?.id) return;
  loading.value = true;
  errors.value = {};
  try {
    const { data } = await axios.get(`/suppliers/${props.supplier.id}/connection-config`);
    if (data && data.connections) {
      connectionsMap.value = data.connections;
    } else if (data && data.type) {
      connectionsMap.value = { [data.type]: data };
    } else {
      connectionsMap.value = {};
    }

    const availableTypes = Object.keys(connectionsMap.value);
    const initialType =
      availableTypes.find((t) => t === "dronena_bot") ||
      availableTypes.find((t) => t === "api") ||
      availableTypes.find((t) => t === "file") ||
      availableTypes.find((t) => t === "ftp" || t === "sftp") ||
      "ftp";

    loadFormForType(initialType);
    hasExistingFtpOrdersPassword.value = data?.ftp_orders_has_pass ?? false;
  } catch {
    toast.error("No se pudo cargar la configuración de conexiones.");
  } finally {
    loading.value = false;
  }
};

const saveConfig = async () => {
  errors.value = {};
  saving.value = true;
  try {
    const payload = { ...form.value };
    if (payload.type === "file") {
      payload.host = payload.username;
      payload.port = null;
      payload.path = null;
      payload.invoice_path = null;
      delete payload.password;
    } else if (payload.type === "dronena_bot" && !payload.host) {
      payload.host = "https://www.dronena.com/NuevaExperiencia/";
    }

    if (!payload.password && hasExistingPassword.value) {
      delete payload.password;
    }
    if (!payload.ftp_orders_password && hasExistingFtpOrdersPassword.value) {
      delete payload.ftp_orders_password;
    }

    const { data } = await axios.post(`/suppliers/${props.supplier.id}/connection-config`, payload);

    if (data?.connection) {
      connectionsMap.value[data.connection.type] = {
        ...connectionsMap.value[data.connection.type],
        ...data.connection,
      };
      hasExistingPassword.value = data.connection.has_password ?? hasExistingPassword.value;
    }

    toast.success(`Configuración de ${form.value.type.toUpperCase()} guardada correctamente.`);
    emit("saved");
    close();
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      toast.error("Revisa los campos marcados en rojo.");
    } else {
      toast.error("Error al guardar la configuración.");
    }
  } finally {
    saving.value = false;
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
    max-width="780px"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Header -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary">
            <VIcon icon="tabler-plug" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Configuración de Conexión
            </h2>
            <span class="text-super-xs opacity-90 font-weight-bold uppercase letter-spacing-1">
              {{ props.supplier?.name ?? 'Proveedor' }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <!-- Selector Compacto de Protocolos (Segmented Tabs) -->
      <div class="px-4 py-2 bg-white border-b flex-shrink-0">
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-xxs font-weight-black text-disabled text-uppercase letter-spacing-1">
            Protocolos Disponibles (Independientes):
          </span>
          <span v-if="lastConnection" class="text-xxs text-success font-weight-bold d-flex align-center gap-1">
            <VIcon icon="tabler-circle-check" size="14" color="success" />
            Última sinc: {{ lastConnection }}
          </span>
          <span v-else class="text-xxs text-medium-emphasis">
            Sin sincronizaciones previas en este protocolo
          </span>
        </div>

        <VTabs
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

      <!-- Contenido de Formulario Compacto -->
      <VCardText class="pa-4 bg-light flex-grow-1 overflow-y-auto">
        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center align-center py-10">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <VForm v-else @submit.prevent="saveConfig">
          <!-- 1. Correo Gmail (Excel) -->
          <template v-if="isEmail">
            <VCard variant="outlined" class="pa-4 bg-white rounded-lg border-card mb-3">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-mail" color="primary" size="20" />
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Extracción Automática desde Gmail</span>
              </div>
              <p class="text-xs text-medium-emphasis mb-3">
                El sistema revisará automáticamente la bandeja de entrada y procesará los archivos Excel adjuntos recibidos desde este remitente.
              </p>

              <VRow dense>
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

          <!-- 2. Bot Dronena -->
          <template v-else-if="form.type === 'dronena_bot'">
            <VCard variant="outlined" class="pa-4 bg-white rounded-lg border-card mb-3">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-robot" color="primary" size="20" />
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Credenciales Bot Dronena</span>
              </div>
              <p class="text-xs text-medium-emphasis mb-3">
                Extracción web directa desde <code>https://www.dronena.com/NuevaExperiencia/</code>.
              </p>

              <VRow dense>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.username"
                    label="Usuario Dronena *"
                    placeholder="ej: usuario_dronena"
                    prepend-inner-icon="tabler-user"
                    :error-messages="errors.username"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    :label="hasExistingPassword ? 'Contraseña (dejar vacío para mantener)' : 'Contraseña Dronena *'"
                    :placeholder="hasExistingPassword ? '••••••••' : 'Contraseña de acceso'"
                    prepend-inner-icon="tabler-lock"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    :error-messages="errors.password"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>
              </VRow>
            </VCard>
          </template>

          <!-- 3. FTP / SFTP / HTTP API -->
          <template v-else>
            <VCard variant="outlined" class="pa-4 bg-white rounded-lg border-card mb-3">
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center gap-2">
                  <VIcon :icon="isFtp ? 'tabler-server' : 'tabler-api'" color="primary" size="20" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    {{ isFtp ? 'Servidor ' + form.type.toUpperCase() : 'Endpoint y Credenciales API' }}
                  </span>
                </div>
              </div>

              <VRow dense>
                <!-- Servidor y Puerto -->
                <VCol cols="12" :md="isFtp ? 8 : 12">
                  <AppTextField
                    v-model="form.host"
                    :label="isFtp ? 'Host / Servidor IP *' : 'URL Endpoint Login / Base *'"
                    :placeholder="isFtp ? 'ftp.proveedor.com' : 'https://api.proveedor.com/login'"
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

                <!-- Credenciales -->
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.username"
                    label="Usuario / Token Cliente *"
                    placeholder="usuario_conexion"
                    prepend-inner-icon="tabler-user"
                    :error-messages="errors.username"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    :label="hasExistingPassword ? 'Contraseña (dejar vacío para mantener)' : 'Contraseña / Clave *'"
                    :placeholder="hasExistingPassword ? '••••••••' : 'Contraseña de acceso'"
                    prepend-inner-icon="tabler-lock"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    :error-messages="errors.password"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>

                <!-- Rutas de Archivos -->
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.path"
                    :label="isFtp ? 'Ruta Archivo Productos' : 'Endpoint Productos'"
                    :placeholder="isFtp ? '/inventario/productos.txt' : '/api/v1/productos'"
                    prepend-inner-icon="tabler-folder"
                    :error-messages="errors.path"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="form.invoice_path"
                    :label="isFtp ? 'Ruta Directorio Facturas' : 'Endpoint Facturas'"
                    :placeholder="isFtp ? '/facturas/' : '/api/v1/facturas'"
                    prepend-inner-icon="tabler-file-invoice"
                    :error-messages="errors.invoice_path"
                  />
                </VCol>
              </VRow>

              <!-- Opciones FTP en una sola fila compacta -->
              <div v-if="isFtp" class="d-flex align-center flex-wrap justify-space-between gap-3 pt-2 mt-2 border-t">
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

          <!-- Transmisión de Pedidos FTP / EDI Secundaria (si aplica) -->
          <div v-if="isHttp || isMafarta || form.type === 'dronena_bot'" class="mt-2">
            <VCard variant="outlined" class="pa-4 bg-white rounded-lg border-card">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-truck-delivery" color="primary" size="18" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    Transmisión de Pedidos FTP / EDI
                  </span>
                </div>
                <VSwitch
                  v-model="form.ftp_orders_enabled"
                  color="primary"
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
                      label="Host FTP Pedidos"
                      placeholder="ftp.drogueriascobeca.com"
                      prepend-inner-icon="tabler-server"
                      :error-messages="errors.ftp_orders_host"
                    />
                  </VCol>
                  <VCol cols="12" md="4">
                    <AppTextField
                      v-model.number="form.ftp_orders_port"
                      type="number"
                      label="Puerto"
                      placeholder="21"
                      prepend-inner-icon="tabler-hash"
                      :error-messages="errors.ftp_orders_port"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.ftp_orders_username"
                      label="Usuario / Código Cliente"
                      placeholder="ej: 31373"
                      prepend-inner-icon="tabler-user"
                      :error-messages="errors.ftp_orders_username"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="form.ftp_orders_password"
                      :type="showFtpOrdersPassword ? 'text' : 'password'"
                      :label="hasExistingFtpOrdersPassword ? 'Contraseña (dejar vacío para mantener)' : 'Contraseña FTP'"
                      :placeholder="hasExistingFtpOrdersPassword ? '••••••••' : 'Contraseña FTP'"
                      prepend-inner-icon="tabler-lock"
                      :append-inner-icon="showFtpOrdersPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      :error-messages="errors.ftp_orders_password"
                      @click:append-inner="showFtpOrdersPassword = !showFtpOrdersPassword"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextField
                      v-model="form.ftp_orders_path"
                      label="Ruta Remota de Pedidos"
                      placeholder="ej: /pedidos (opcional)"
                      prepend-inner-icon="tabler-folder"
                      :error-messages="errors.ftp_orders_path"
                    />
                  </VCol>
                </VRow>
              </div>
            </VCard>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <!-- Footer con acciones equilibradas -->
      <VCardActions class="pa-4 bg-white border-t d-flex align-center justify-space-between">
        <VBtn
          color="info"
          variant="tonal"
          height="42"
          prepend-icon="tabler-history"
          class="font-weight-bold rounded-lg text-xs"
          @click="isHistoryOpen = true"
        >
          Historial de Sincronizaciones
        </VBtn>

        <div class="d-flex align-center gap-2">
          <VBtn
            color="secondary"
            variant="outlined"
            height="42"
            class="font-weight-bold rounded-lg text-xs px-4"
            @click="close"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            height="42"
            class="font-weight-bold rounded-lg shadow-primary text-xs px-4"
            :loading="saving"
            @click="saveConfig"
          >
            <VIcon start icon="tabler-device-floppy" size="16" />
            Guardar {{ form.type.toUpperCase() }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <!-- Diálogo de Historial de Conexiones -->
    <SupplierConnectionHistoryDialog
      v-model="isHistoryOpen"
      :supplier="props.supplier"
    />
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
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
