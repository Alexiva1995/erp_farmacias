<script setup>
import { ref, onMounted } from 'vue'
import TelegramChannelsManager from './components/TelegramChannelsManager.vue'

const config = ref({
  bot_token: '',
  chat_id: '',
  admin_chat_id: '',
  webhook_url: '',
  is_active: true,
})

const loadingConfig = ref(false)
const savingConfig = ref(false)
const registeringWebhook = ref(false)
const checkingStatus = ref(false)
const webhookInfo = ref(null)

const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const fetchConfig = async () => {
  loadingConfig.value = true
  try {
    const response = await fetch('/api/telegram/config', {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })
    if (response.ok) {
      const res = await response.json()
      if (res.data) {
        config.value = { ...res.data }
      }
    } else {
      showToast('Error al cargar la configuración de Telegram', 'error')
    }
  } catch (error) {
    showToast('Error de conexión al cargar la configuración', 'error')
  } finally {
    loadingConfig.value = false
  }
}

const saveConfig = async () => {
  savingConfig.value = true
  try {
    const response = await fetch('/api/telegram/config', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
      body: JSON.stringify(config.value),
    })

    if (response.ok) {
      showToast('Configuración guardada correctamente', 'success')
      fetchConfig()
    } else {
      showToast('Error al guardar la configuración', 'error')
    }
  } catch (error) {
    showToast('Error de comunicación con el servidor', 'error')
  } finally {
    savingConfig.value = false
  }
}

const registerWebhook = async () => {
  registeringWebhook.value = true
  try {
    const response = await fetch('/api/telegram/webhook/register', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })

    const res = await response.json()
    if (response.ok) {
      showToast(res.message || 'Webhook registrado con éxito en Telegram', 'success')
      checkWebhookStatus()
    } else {
      showToast(res.error || res.message || 'Error al registrar Webhook', 'error')
    }
  } catch (error) {
    showToast('Excepción al registrar Webhook en Telegram', 'error')
  } finally {
    registeringWebhook.value = false
  }
}

const checkWebhookStatus = async () => {
  checkingStatus.value = true
  try {
    const response = await fetch('/api/telegram/webhook/status', {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })
    const res = await response.json()
    if (response.ok && res.info) {
      webhookInfo.value = res.info
      showToast('Estado del Webhook actualizado', 'info')
    } else {
      webhookInfo.value = null
      showToast(res.message || 'Error consultando estado del Webhook', 'warning')
    }
  } catch (error) {
    showToast('Error al conectar con Telegram API', 'error')
  } finally {
    checkingStatus.value = false
  }
}

const showToast = (text, color = 'success') => {
  snackbar.value = {
    show: true,
    text,
    color,
  }
}

onMounted(() => {
  fetchConfig()
  checkWebhookStatus()
})
</script>

<template>
  <div>
    <VRow>
      <!-- Credenciales Globales -->
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardItem>
            <template #prepend>
              <VAvatar color="primary" variant="tonal" rounded size="42">
                <VIcon icon="tabler-brand-telegram" size="24" />
              </VAvatar>
            </template>
            <VCardTitle class="text-h5 font-weight-bold">
              Configuración General & Webhook de Telegram
            </VCardTitle>
            <VCardSubtitle>
              Configura el Token global de tu Bot y registra la URL para recibir eventos y webhooks interactivos.
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

            <VForm @submit.prevent="saveConfig">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="config.bot_token"
                    label="Bot Token de Telegram"
                    placeholder="ej: 123456789:ABCdefGhIJKlmNoPQRstuVWXyz"
                    prepend-inner-icon="tabler-key"
                    hint="Obtenido a través de @BotFather en Telegram"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="config.webhook_url"
                    label="URL del Webhook de Telegram"
                    placeholder="https://tu-dominio.com/api/public/telegram/webhook"
                    prepend-inner-icon="tabler-link"
                    hint="Debe ser una URL HTTPS accesible públicamente"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="config.chat_id"
                    label="Chat ID Principal (Fallback)"
                    placeholder="ej: -100123456789"
                    prepend-inner-icon="tabler-message"
                    hint="Canal por defecto en caso de no especificar canal por módulo"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="config.admin_chat_id"
                    label="Admin Chat ID (Administrador)"
                    placeholder="ej: 987654321"
                    prepend-inner-icon="tabler-user-check"
                    hint="ID del administrador autorizado para comandos interactivos"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" class="d-flex align-center gap-4 mt-2">
                  <VBtn
                    type="submit"
                    color="primary"
                    prepend-icon="tabler-device-floppy"
                    :loading="savingConfig"
                  >
                    Guardar Credenciales
                  </VBtn>

                  <VBtn
                    color="success"
                    prepend-icon="tabler-webhook"
                    :loading="registeringWebhook"
                    @click="registerWebhook"
                  >
                    Registrar Webhook en Telegram
                  </VBtn>

                  <VBtn
                    color="info"
                    variant="outlined"
                    prepend-icon="tabler-activity"
                    :loading="checkingStatus"
                    @click="checkWebhookStatus"
                  >
                    Verificar Estado
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Administrador de Múltiples Canales con Nombres -->
      <VCol cols="12">
        <TelegramChannelsManager />
      </VCol>

      <!-- Card de Estado del Webhook -->
      <VCol cols="12">
        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                :color="webhookInfo && webhookInfo.url ? 'success' : 'warning'"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  :icon="webhookInfo && webhookInfo.url ? 'tabler-circle-check' : 'tabler-alert-triangle'"
                  size="24"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6 font-weight-bold">
              Estado del Webhook en Producción
            </VCardTitle>
            <VCardSubtitle>
              Información de diagnóstico retornada por la API de Telegram.
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <div v-if="webhookInfo">
              <VRow class="mb-2">
                <VCol cols="12" md="4">
                  <div class="text-caption text-uppercase font-weight-bold text-muted">URL Configurada</div>
                  <div class="font-weight-medium text-body-1 text-primary text-break">
                    {{ webhookInfo.url || 'No registrada' }}
                  </div>
                </VCol>

                <VCol cols="12" md="4">
                  <div class="text-caption text-uppercase font-weight-bold text-muted">Mensajes Pendientes</div>
                  <div class="font-weight-medium text-body-1">
                    <VChip size="small" :color="webhookInfo.pending_update_count > 0 ? 'warning' : 'success'">
                      {{ webhookInfo.pending_update_count || 0 }} pendientes
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="12" md="4">
                  <div class="text-caption text-uppercase font-weight-bold text-muted">Certificado SSL Personalizado</div>
                  <div class="font-weight-medium text-body-1">
                    {{ webhookInfo.has_custom_certificate ? 'Sí' : 'No' }}
                  </div>
                </VCol>

                <VCol cols="12" v-if="webhookInfo.last_error_message">
                  <VAlert
                    type="error"
                    variant="tonal"
                    class="mt-2"
                    title="Último error reportado por Telegram:"
                  >
                    {{ webhookInfo.last_error_message }}
                    <div class="text-caption mt-1" v-if="webhookInfo.last_error_date">
                      Fecha: {{ new Date(webhookInfo.last_error_date * 1000).toLocaleString() }}
                    </div>
                  </VAlert>
                </VCol>
              </VRow>
            </div>
            <div v-else class="text-muted text-center py-4">
              Presiona "Verificar Estado" para consultar la conexión en vivo con Telegram.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
      location="top right"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
