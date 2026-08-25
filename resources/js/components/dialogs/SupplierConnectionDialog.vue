<script setup>
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier:   { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'saved']);

// ─── Estado ───────────────────────────────────────────────────────────────────
const loading       = ref(false);
const saving        = ref(false);
const showPassword  = ref(false);

const form = ref({
  type:         'ftp',
  host:         '',
  port:         '',
  username:     '',
  password:     '',
  path:         '',
  invoice_path: '',
  pasv:         true,
  has_header:   false,
});

const hasExistingPassword = ref(false);
const lastConnection      = ref(null);
const errors              = ref({});

const isFtp  = computed(() => ['ftp', 'sftp'].includes(form.value.type));
const isHttp = computed(() => ['http', 'api'].includes(form.value.type));
const isDronenaBot = computed(() => form.value.type === 'dronena_bot' || (props.supplier?.name && props.supplier.name.toUpperCase().includes('NENA')));

const typeOptions = computed(() => {
  const options = [
    { title: 'FTP',  value: 'ftp',  icon: 'tabler-server', description: 'Conexión por protocolo FTP estándar' },
    { title: 'SFTP', value: 'sftp', icon: 'tabler-lock',   description: 'FTP seguro sobre SSH' },
    { title: 'HTTP / API', value: 'api', icon: 'tabler-api', description: 'Endpoint REST con autenticación por token' },
  ];

  if (props.supplier?.name && (props.supplier.name.toUpperCase().includes('NENA') || props.supplier.name.toUpperCase().includes('DRONENA'))) {
    options.unshift({
      title: 'Bot Dronena',
      value: 'dronena_bot',
      icon: 'tabler-robot',
      description: 'Extracción automática directa del portal web de Dronena',
    });
  }

  return options;
});

const defaultPort = computed(() => {
  if (form.value.type === 'ftp')  return 21;
  if (form.value.type === 'sftp') return 22;
  return '';
});

// ─── Métodos ──────────────────────────────────────────────────────────────────
const fetchConfig = async () => {
  if (!props.supplier?.id) return;
  loading.value = true;
  errors.value  = {};
  try {
    const { data } = await axios.get(`/suppliers/${props.supplier.id}/connection-config`);
    if (data) {
      form.value = {
        type:         data.type         ?? 'ftp',
        host:         data.host         ?? '',
        port:         data.port         ?? '',
        username:     data.username     ?? '',
        password:     '',
        path:         data.path         ?? '',
        invoice_path: data.invoice_path ?? '',
        pasv:         data.pasv         ?? true,
        has_header:   data.has_header   ?? false,
      };
      hasExistingPassword.value = data.has_password ?? false;
      lastConnection.value      = data.last_connection;
    } else {
      // Sin configuración: reset al default
      form.value = { type: 'ftp', host: '', port: '', username: '', password: '', path: '', invoice_path: '', pasv: true, has_header: false };
      hasExistingPassword.value = false;
      lastConnection.value      = null;
    }
  } catch {
    toast.error('No se pudo cargar la configuración.');
  } finally {
    loading.value = false;
  }
};

const saveConfig = async () => {
  errors.value = {};
  saving.value = true;
  try {
    const payload = { ...form.value };
    if (payload.type === 'dronena_bot' && !payload.host) {
      payload.host = 'https://www.dronena.com/NuevaExperiencia/';
    }
    // Si no envió nueva contraseña y ya existía una, no mandamos el campo
    if (!payload.password && hasExistingPassword.value) {
      delete payload.password;
    }
    await axios.post(`/suppliers/${props.supplier.id}/connection-config`, payload);
    toast.success('Configuración guardada correctamente.');
    emit('saved');
    close();
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      toast.error('Revisa los campos marcados en rojo.');
    } else {
      toast.error('Error al guardar la configuración.');
    }
  } finally {
    saving.value = false;
  }
};

const close = () => {
  emit('update:modelValue', false);
  errors.value = {};
};

// Al abrir el diálogo, muestra el puerto por defecto si el campo está vacío
watch(() => form.value.type, (newType) => {
  if (!form.value.port) {
    form.value.port = defaultPort.value;
  }
});

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) fetchConfig();
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="680px"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-plug-connected" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Configuración de Conexión
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              {{ props.supplier?.name ?? 'Proveedor' }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light" style="overflow-y: auto;">

        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center align-center py-12">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <VForm v-else @submit.prevent="saveConfig">

          <!-- Estado de conexión -->
          <VAlert
            v-if="lastConnection"
            type="success"
            variant="tonal"
            density="compact"
            icon="tabler-circle-check"
            class="mb-4 rounded-xl"
          >
            Última sincronización exitosa: <strong>{{ lastConnection }}</strong>
          </VAlert>
          <VAlert
            v-else
            type="warning"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="mb-4 rounded-xl"
          >
            Este proveedor <strong>no ha sido sincronizado</strong> aún o no tiene conexión configurada.
          </VAlert>

          <!-- Sección 1: Tipo de conexión -->
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Tipo de Conexión</span>
          </div>

          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
            <VRow>
              <VCol
                v-for="opt in typeOptions"
                :key="opt.value"
                cols="12"
                md="4"
              >
                <div
                  class="type-option-card pa-3 text-center rounded-xl border cursor-pointer"
                  :class="form.type === opt.value ? 'type-option-active' : 'type-option-inactive'"
                  @click="form.type = opt.value"
                >
                  <VIcon :icon="opt.icon" size="26" class="mb-1" />
                  <div class="text-subtitle-2 font-weight-black">{{ opt.title }}</div>
                  <div class="text-super-xs text-medium-emphasis">{{ opt.description }}</div>
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Sección 2: Servidor (Oculto o simplificado en Dronena Bot) -->
          <div v-if="form.type !== 'dronena_bot'" class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator secondary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
              {{ isFtp ? 'Servidor FTP' : 'Endpoint de la API' }}
            </span>
          </div>

          <VCard v-if="form.type !== 'dronena_bot'" variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
            <VRow>
              <VCol cols="12" :md="isFtp ? 8 : 12">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">
                  {{ isFtp ? 'Host / IP del servidor' : 'URL de Login (endpoint)' }}
                </span>
                <AppTextField
                  v-model="form.host"
                  :placeholder="isFtp ? 'ftp.proveedor.com' : 'https://api.proveedor.com/login'"
                  prepend-inner-icon="tabler-server"
                  :error-messages="errors.host"
                />
              </VCol>
              <VCol v-if="isFtp" cols="12" md="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Puerto</span>
                <AppTextField
                  v-model.number="form.port"
                  type="number"
                  :placeholder="form.type === 'sftp' ? '22' : '21'"
                  prepend-inner-icon="tabler-hash"
                  :error-messages="errors.port"
                />
              </VCol>
            </VRow>
          </VCard>

          <VAlert
            v-else
            type="info"
            variant="tonal"
            density="compact"
            icon="tabler-robot"
            class="mb-4 rounded-xl"
          >
            El <strong>Bot Dronena</strong> accederá automáticamente a <code>https://www.dronena.com/NuevaExperiencia/</code>. Solo necesitas ingresar el <strong>Usuario</strong> y la <strong>Contraseña</strong> de la cuenta.
          </VAlert>

          <!-- Sección 3: Credenciales -->
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Credenciales de Acceso</span>
          </div>

          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
            <VRow>
              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Usuario</span>
                <AppTextField
                  v-model="form.username"
                  placeholder="usuario_ftp"
                  prepend-inner-icon="tabler-user"
                  :error-messages="errors.username"
                />
              </VCol>
              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">
                  {{ hasExistingPassword ? 'Nueva Contraseña (dejar vacío para no cambiar)' : 'Contraseña' }}
                </span>
                <AppTextField
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  :placeholder="hasExistingPassword ? '••••••••' : 'Contraseña de acceso'"
                  prepend-inner-icon="tabler-lock"
                  :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="errors.password"
                  @click:append-inner="showPassword = !showPassword"
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Sección 4: Rutas (Solo para FTP / API estándar) -->
          <template v-if="form.type !== 'dronena_bot'">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator secondary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                {{ isFtp ? 'Rutas de Archivos' : 'Endpoints de Datos' }}
              </span>
            </div>

            <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
              <VRow>
                <VCol cols="12" md="6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">
                    {{ isFtp ? 'Ruta de productos' : 'Endpoint de Productos' }}
                  </span>
                  <AppTextField
                    v-model="form.path"
                    :placeholder="isFtp ? '/inventario/productos.txt' : '/api/v1/productos'"
                    prepend-inner-icon="tabler-folder"
                    :error-messages="errors.path"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">
                    {{ isFtp ? 'Ruta de facturas' : 'Endpoint de Facturas' }}
                  </span>
                  <AppTextField
                    v-model="form.invoice_path"
                    :placeholder="isFtp ? '/facturas/' : '/api/v1/facturas'"
                    prepend-inner-icon="tabler-file-invoice"
                    :error-messages="errors.invoice_path"
                  />
                </VCol>
              </VRow>
            </VCard>
          </template>

          <!-- Sección 5: Opciones FTP -->
          <template v-if="isFtp">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Opciones FTP</span>
            </div>

            <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
              <VRow>
                <VCol cols="12" md="6">
                  <div class="d-flex align-center justify-space-between pa-3 bg-light rounded-xl border-dashed-2">
                    <div>
                      <div class="text-sm font-weight-black text-high-emphasis">Modo Pasivo (PASV)</div>
                      <div class="text-super-xs text-disabled">Recomendado si hay firewall o NAT</div>
                    </div>
                    <VSwitch v-model="form.pasv" color="primary" hide-details density="compact" />
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="d-flex align-center justify-space-between pa-3 bg-light rounded-xl border-dashed-2">
                    <div>
                      <div class="text-sm font-weight-black text-high-emphasis">Archivo con encabezado</div>
                      <div class="text-super-xs text-disabled">La primera línea es el nombre de columnas</div>
                    </div>
                    <VSwitch v-model="form.has_header" color="primary" hide-details density="compact" />
                  </div>
                </VCol>
              </VRow>
            </VCard>
          </template>

        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="saving"
              @click="saveConfig"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Config.
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
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.type-option-card {
  transition: all 0.2s ease;
}

.type-option-active {
  background-color: rgba(var(--v-theme-primary), 0.08);
  border-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-primary));
}

.type-option-inactive {
  background-color: white;
  border-color: rgba(var(--v-border-color), 0.2) !important;
}

.type-option-inactive:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}
</style>
