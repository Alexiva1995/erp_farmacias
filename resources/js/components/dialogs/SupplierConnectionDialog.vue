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

// ─── Computed ─────────────────────────────────────────────────────────────────
const isFtp  = computed(() => ['ftp', 'sftp'].includes(form.value.type));
const isHttp = computed(() => ['http', 'api'].includes(form.value.type));

const typeOptions = [
  { title: 'FTP',  value: 'ftp',  icon: 'tabler-server', description: 'Conexión por protocolo FTP estándar' },
  { title: 'SFTP', value: 'sftp', icon: 'tabler-lock',   description: 'FTP seguro sobre SSH' },
  { title: 'HTTP / API', value: 'api', icon: 'tabler-api', description: 'Endpoint REST con autenticación por token' },
];

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
    <VCard>
      <!-- ── Cabecera ──────────────────────────────────────────── -->
      <VCardTitle class="d-flex align-center pa-5 bg-primary text-white">
        <VAvatar color="white" variant="tonal" size="36" rounded class="me-3">
          <VIcon icon="tabler-plug-connected" size="20" />
        </VAvatar>
        <div>
          <div class="text-h6 font-weight-bold">Configuración de Conexión</div>
          <div class="text-caption text-white opacity-70">{{ props.supplier?.name }}</div>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="text" color="white" size="small" @click="close" />
      </VCardTitle>

      <VDivider />

      <!-- ── Cuerpo ────────────────────────────────────────────── -->
      <VCardText class="pa-6" style="overflow-y: auto;">
        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center align-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <VForm v-else @submit.prevent="saveConfig">

          <!-- Última conexión -->
          <VAlert
            v-if="lastConnection"
            type="success"
            variant="tonal"
            density="compact"
            icon="tabler-circle-check"
            class="mb-5"
          >
            Última sincronización exitosa: <strong>{{ lastConnection }}</strong>
          </VAlert>
          <VAlert
            v-else
            type="warning"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="mb-5"
          >
            Este proveedor <strong>no ha sido sincronizado</strong> aún o no tiene conexión configurada.
          </VAlert>

          <!-- ── Sección 1: Tipo de conexión ── -->
          <div class="text-overline text-primary font-weight-bold mb-3">Tipo de Conexión</div>
          <VRow>
            <VCol
              v-for="opt in typeOptions"
              :key="opt.value"
              cols="12"
              md="4"
            >
              <VCard
                :variant="form.type === opt.value ? 'elevated' : 'outlined'"
                :color="form.type === opt.value ? 'primary' : undefined"
                class="cursor-pointer pa-3 text-center transition-all"
                :class="form.type === opt.value ? 'border-primary' : ''"
                style="transition: all 0.2s;"
                @click="form.type = opt.value"
              >
                <VIcon :icon="opt.icon" size="28" class="mb-1" />
                <div class="text-subtitle-2 font-weight-bold">{{ opt.title }}</div>
                <div class="text-caption text-medium-emphasis">{{ opt.description }}</div>
              </VCard>
            </VCol>
          </VRow>

          <!-- ── Sección 2: Servidor ── -->
          <div class="text-overline text-primary font-weight-bold mt-6 mb-3">
            {{ isFtp ? 'Servidor FTP' : 'Endpoint de la API' }}
          </div>
          <VRow>
            <VCol cols="12" :md="isFtp ? 8 : 12">
              <AppTextField
                v-model="form.host"
                :label="isFtp ? 'Host / IP del servidor' : 'URL de Login (endpoint)'"
                :placeholder="isFtp ? 'ftp.proveedor.com' : 'https://api.proveedor.com/login'"
                prepend-inner-icon="tabler-server"
                :error-messages="errors.host"
              />
            </VCol>
            <VCol v-if="isFtp" cols="12" md="4">
              <AppTextField
                v-model.number="form.port"
                label="Puerto"
                type="number"
                :placeholder="form.type === 'sftp' ? '22' : '21'"
                prepend-inner-icon="tabler-hash"
                :error-messages="errors.port"
              />
            </VCol>
          </VRow>

          <!-- ── Sección 3: Credenciales ── -->
          <div class="text-overline text-primary font-weight-bold mt-5 mb-3">Credenciales</div>
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.username"
                label="Usuario"
                placeholder="usuario_ftp"
                prepend-inner-icon="tabler-user"
                :error-messages="errors.username"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.password"
                :label="hasExistingPassword ? 'Nueva Contraseña (dejar vacío para no cambiar)' : 'Contraseña'"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="hasExistingPassword ? '••••••••' : 'Contraseña de acceso'"
                prepend-inner-icon="tabler-lock"
                :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                :error-messages="errors.password"
                @click:append-inner="showPassword = !showPassword"
              />
            </VCol>
          </VRow>

          <!-- ── Sección 4: Rutas ── -->
          <div class="text-overline text-primary font-weight-bold mt-5 mb-3">
            {{ isFtp ? 'Rutas de Archivos' : 'Endpoints de Datos' }}
          </div>
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.path"
                :label="isFtp ? 'Ruta del archivo de productos' : 'Endpoint de Productos'"
                :placeholder="isFtp ? '/inventario/productos.txt' : '/api/v1/productos'"
                prepend-inner-icon="tabler-folder"
                :error-messages="errors.path"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.invoice_path"
                :label="isFtp ? 'Ruta de la carpeta de facturas' : 'Endpoint de Facturas'"
                :placeholder="isFtp ? '/facturas/' : '/api/v1/facturas'"
                prepend-inner-icon="tabler-file-invoice"
                :error-messages="errors.invoice_path"
              />
            </VCol>
          </VRow>

          <!-- ── Sección 5: Opciones FTP ── -->
          <template v-if="isFtp">
            <div class="text-overline text-primary font-weight-bold mt-5 mb-3">Opciones FTP</div>
            <VRow>
              <VCol cols="12" md="6">
                <VCard variant="tonal" color="secondary" class="pa-4 rounded-lg">
                  <div class="d-flex align-center justify-space-between">
                    <div>
                      <div class="text-subtitle-2 font-weight-bold">Modo Pasivo (PASV)</div>
                      <div class="text-caption text-medium-emphasis">Recomendado si hay firewall o NAT</div>
                    </div>
                    <VSwitch v-model="form.pasv" color="primary" hide-details />
                  </div>
                </VCard>
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="tonal" color="secondary" class="pa-4 rounded-lg">
                  <div class="d-flex align-center justify-space-between">
                    <div>
                      <div class="text-subtitle-2 font-weight-bold">El archivo tiene encabezado</div>
                      <div class="text-caption text-medium-emphasis">La primera línea es el nombre de columnas</div>
                    </div>
                    <VSwitch v-model="form.has_header" color="primary" hide-details />
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </template>

        </VForm>
      </VCardText>

      <VDivider />

      <!-- ── Footer ────────────────────────────────────────────── -->
      <VCardActions class="pa-4 d-flex gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          class="flex-grow-1"
          @click="close"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="flex-grow-1"
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          @click="saveConfig"
        >
          Guardar Configuración
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
