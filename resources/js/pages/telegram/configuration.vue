<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import TelegramChannelsManager from './components/TelegramChannelsManager.vue'

// ==================== ESTADO DEL FORMULARIO ====================
const activeTab = ref('credentials')
const showToken = ref(false)

const initialConfig = ref({
  bot_token: '',
  chat_id: '',
  admin_chat_id: '',
  webhook_url: '',
  is_active: true,
})

const configForm = reactive({
  bot_token: '',
  chat_id: '',
  admin_chat_id: '',
  webhook_url: '',
  is_active: true,
})

// Estados de Carga
const loadingConfig = ref(false)
const savingConfig = ref(false)
const registeringWebhook = ref(false)
const checkingStatus = ref(false)
const webhookInfo = ref(null)

// Notificaciones Toast
const snackbar = reactive({
  show: false,
  text: '',
  color: 'success',
})

// ==================== COMPUTED & VALIDACIONES ====================
const isDirty = computed(() => {
  return JSON.stringify(configForm) !== JSON.stringify(initialConfig.value)
})

const isWebhookConfigured = computed(() => {
  return Boolean(webhookInfo.value && webhookInfo.value.url)
})

// ==================== MÉTODOS DE COMUNICACIÓN API ====================
const showToast = (text, color = 'success') => {
  snackbar.text = text
  snackbar.color = color
  snackbar.show = true
}

const fetchConfig = async () => {
  loadingConfig.value = true
  try {
    const { data } = await axios.get('/api/telegram/config')
    if (data && data.data) {
      Object.assign(configForm, data.data)
      initialConfig.value = JSON.parse(JSON.stringify(data.data))
    }
  } catch (error) {
    showToast('Error al cargar la configuración de Telegram.', 'error')
  } finally {
    loadingConfig.value = false
  }
}

const saveConfig = async () => {
  savingConfig.value = true
  try {
    const { data } = await axios.put('/api/telegram/config', configForm)
    initialConfig.value = JSON.parse(JSON.stringify(configForm))
    showToast(data.message || 'Configuración guardada exitosamente.', 'success')
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Error al persistir la configuración.'
    showToast(errorMsg, 'error')
  } finally {
    savingConfig.value = false
  }
}

const registerWebhook = async () => {
  if (isDirty.value) {
    const confirm = await Swal.fire({
      title: 'Cambios no guardados',
      text: 'Debes guardar la configuración antes de registrar el Webhook. ¿Deseas guardarla ahora?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar y registrar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E20074',
    })

    if (!confirm.isConfirmed) return
    await saveConfig()
  }

  registeringWebhook.value = true
  try {
    const { data } = await axios.post('/api/telegram/webhook/register')
    showToast(data.message || 'Webhook registrado con éxito en Telegram.', 'success')
    await checkWebhookStatus()
  } catch (error) {
    const msg = error.response?.data?.error || error.response?.data?.message || 'Error al registrar Webhook en Telegram.'
    showToast(msg, 'error')
  } finally {
    registeringWebhook.value = false
  }
}

const checkWebhookStatus = async (silent = false) => {
  checkingStatus.value = true
  try {
    const { data } = await axios.get('/api/telegram/webhook/status')
    if (data.status === 'success' && data.info) {
      webhookInfo.value = data.info
      if (!silent) {
        showToast('Diagnóstico de Webhook actualizado.', 'info')
      }
    } else {
      webhookInfo.value = null
      if (!silent) {
        showToast(data.message || 'El webhook no se encuentra activo.', 'warning')
      }
    }
  } catch (error) {
    webhookInfo.value = null
    if (!silent) {
      showToast('Fallo de conexión al consultar el estado en Telegram.', 'error')
    }
  } finally {
    checkingStatus.value = false
  }
}

onMounted(() => {
  fetchConfig()
  checkWebhookStatus(true)
})
</script>

<template>
  <div>
    <!-- Encabezado Principal del Módulo -->
    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="48">
            <VIcon icon="tabler-brand-telegram" size="28" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h5 font-weight-bold">
          Configuración Central de Telegram
        </VCardTitle>
        <VCardSubtitle class="text-body-2">
          Gestión de credenciales de BotFather, sincronización de Webhook y enrutamiento multicanal del ERP.
        </VCardSubtitle>

        <template #append>
          <div class="d-flex align-center gap-2">
            <VChip
              :color="isWebhookConfigured ? 'success' : 'warning'"
              variant="tonal"
              size="small"
              class="font-weight-medium"
            >
              <VIcon
                start
                size="14"
                :icon="isWebhookConfigured ? 'tabler-circle-check' : 'tabler-alert-circle'"
              />
              {{ isWebhookConfigured ? 'Webhook Activo' : 'Webhook Inactivo' }}
            </VChip>
          </div>
        </template>
      </VCardItem>

      <VTabs v-model="activeTab" class="px-4">
        <VTab value="credentials">
          <VIcon start icon="tabler-key" size="18" />
          Credenciales & Diagnóstico
        </VTab>
        <VTab value="channels">
          <VIcon start icon="tabler-topology-ring-3" size="18" />
          Canales de Notificación
        </VTab>
      </VTabs>
    </VCard>

    <!-- Contenido de Pestañas -->
    <VWindow v-model="activeTab">
      <!-- TAB 1: CREDENCIALES Y DIAGNÓSTICO -->
      <VWindowItem value="credentials">
        <VRow>
          <!-- Formulario de Configuración Principal -->
          <VCol cols="12" lg="7">
            <VCard>
              <VCardItem>
                <VCardTitle class="text-h6 font-weight-bold">
                  Parámetros de Autenticación
                </VCardTitle>
                <VCardSubtitle class="text-body-2">
                  Configuración del token del bot y credenciales de enrutamiento base.
                </VCardSubtitle>
              </VCardItem>

              <VDivider />

              <VCardText class="pt-6">
                <VProgressLinear
                  v-if="loadingConfig"
                  indeterminate
                  color="primary"
                  class="mb-4"
                />

                <VAlert
                  v-if="isDirty"
                  type="warning"
                  variant="tonal"
                  density="comfortable"
                  class="mb-6"
                  icon="tabler-alert-triangle"
                >
                  Tienes cambios sin guardar en la configuración. Guarda los cambios antes de ejecutar acciones en Telegram.
                </VAlert>

                <VForm @submit.prevent="saveConfig">
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="configForm.bot_token"
                        :type="showToken ? 'text' : 'password'"
                        label="Bot Token de Telegram *"
                        placeholder="123456789:ABCdefGhIJKlmNoPQRstuVWXyz"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        prepend-inner-icon="tabler-key"
                        :append-inner-icon="showToken ? 'tabler-eye-off' : 'tabler-eye'"
                        hint="Token emitido por @BotFather al crear el bot"
                        persistent-hint
                        @click:append-inner="showToken = !showToken"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VTextField
                        v-model="configForm.webhook_url"
                        label="URL Pública del Webhook *"
                        placeholder="https://tudominio.com/api/public/telegram/webhook"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        prepend-inner-icon="tabler-link"
                        hint="Requiere protocolo HTTPS con certificado SSL válido"
                        persistent-hint
                      />
                    </VCol>

                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="configForm.chat_id"
                        label="Chat ID Principal (Fallback)"
                        placeholder="-100123456789"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        prepend-inner-icon="tabler-message"
                        hint="Canal por defecto para alertas sin módulo asignado"
                        persistent-hint
                      />
                    </VCol>

                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="configForm.admin_chat_id"
                        label="Admin Chat ID (Superusuario)"
                        placeholder="987654321"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        prepend-inner-icon="tabler-shield-check"
                        hint="Identificador numérico con privilegios de ejecución"
                        persistent-hint
                      />
                    </VCol>

                    <VCol cols="12" class="d-flex flex-wrap align-center gap-3 pt-4">
                      <VBtn
                        type="submit"
                        color="primary"
                        prepend-icon="tabler-device-floppy"
                        :loading="savingConfig"
                        :disabled="loadingConfig"
                      >
                        Guardar Parámetros
                      </VBtn>

                      <VBtn
                        color="success"
                        variant="tonal"
                        prepend-icon="tabler-webhook"
                        :loading="registeringWebhook"
                        :disabled="loadingConfig || !configForm.bot_token"
                        @click="registerWebhook"
                      >
                        Sincronizar Webhook
                      </VBtn>

                      <VBtn
                        color="info"
                        variant="outlined"
                        prepend-icon="tabler-refresh"
                        :loading="checkingStatus"
                        @click="checkWebhookStatus"
                      >
                        Verificar Conexión
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Diagnóstico de Webhook en Producción -->
          <VCol cols="12" lg="5">
            <VCard class="h-100">
              <VCardItem>
                <template #prepend>
                  <VAvatar
                    :color="isWebhookConfigured ? 'success' : 'warning'"
                    variant="tonal"
                    rounded
                    size="40"
                  >
                    <VIcon
                      :icon="isWebhookConfigured ? 'tabler-server-2' : 'tabler-server-off'"
                      size="22"
                    />
                  </VAvatar>
                </template>
                <VCardTitle class="text-h6 font-weight-bold">
                  Diagnóstico en Tiempo Real
                </VCardTitle>
                <VCardSubtitle class="text-body-2">
                  Metadatos retornados directamente por la API de Telegram.
                </VCardSubtitle>
              </VCardItem>

              <VDivider />

              <VCardText class="pt-4">
                <div v-if="webhookInfo" class="d-flex flex-column gap-4">
                  <div>
                    <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                      URL Registrada en Telegram
                    </span>
                    <div class="text-body-2 font-weight-medium text-primary text-break mt-1">
                      {{ webhookInfo.url || 'No registrada' }}
                    </div>
                  </div>

                  <VRow dense>
                    <VCol cols="6">
                      <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                        Mensajes Pendientes
                      </span>
                      <div class="mt-1">
                        <VChip
                          size="small"
                          :color="webhookInfo.pending_update_count > 0 ? 'warning' : 'success'"
                          variant="tonal"
                        >
                          {{ webhookInfo.pending_update_count || 0 }} pendientes
                        </VChip>
                      </div>
                    </VCol>

                    <VCol cols="6">
                      <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                        Certificado Personalizado
                      </span>
                      <div class="text-body-2 font-weight-medium mt-1">
                        {{ webhookInfo.has_custom_certificate ? 'Habilitado' : 'No requerido' }}
                      </div>
                    </VCol>
                  </VRow>

                  <div v-if="webhookInfo.last_error_message">
                    <VAlert
                      type="error"
                      variant="tonal"
                      density="comfortable"
                      class="mt-2"
                      title="Último error reportado:"
                    >
                      <div class="text-body-2">{{ webhookInfo.last_error_message }}</div>
                      <div
                        v-if="webhookInfo.last_error_date"
                        class="text-caption mt-1 font-weight-medium"
                      >
                        Hora del error: {{ new Date(webhookInfo.last_error_date * 1000).toLocaleString() }}
                      </div>
                    </VAlert>
                  </div>
                </div>

                <div v-else class="text-center py-8 text-medium-emphasis">
                  <VIcon icon="tabler-network-off" size="48" class="mb-2 opacity-50" />
                  <p class="text-body-2 mb-0">
                    No se ha obtenido información de diagnóstico. Pulsa "Verificar Conexión" para consultar el servidor.
                  </p>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- TAB 2: GESTIÓN DE CANALES -->
      <VWindowItem value="channels">
        <TelegramChannelsManager />
      </VWindowItem>
    </VWindow>

    <!-- Notificaciones Toast Rápidas -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
      location="top right"
      variant="flat"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
