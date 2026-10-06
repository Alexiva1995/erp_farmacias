<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'

const ability = useAbility()

// Validación de permisos CASL
const canEdit = computed(() => {
  return (
    ability.can('manage', 'admin') ||
    ability.can('manage', 'all') ||
    ability.can('edit', 'Configuration')
  )
})

// Estados de carga y feedback
const isLoading = ref(true)
const isSaving = ref(false)
const isTesting = ref(false)
const isSyncingNow = ref(false)
const showPassword = ref(false)
const showAdvanced = ref(false)

// Estado del formulario reactivo
const form = reactive({
  gmail_sync_email: '',
  gmail_sync_password: '',
  has_password: false,
  gmail_sync_host: 'imap.gmail.com',
  gmail_sync_port: 993,
  gmail_sync_folder: 'INBOX',
  gmail_sync_enabled: true,
  gmail_sync_last_tested_at: null,
  gmail_sync_last_status: null,
})

// Resultado del último test de conexión
const testResult = ref(null)

// Cargar la configuración actual desde el backend
const fetchConfig = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/configuration/email-sync')
    const data = response.data.data || response.data

    form.gmail_sync_email = data.gmail_sync_email || ''
    form.has_password = Boolean(data.has_password)
    form.gmail_sync_host = data.gmail_sync_host || 'imap.gmail.com'
    form.gmail_sync_port = Number(data.gmail_sync_port) || 993
    form.gmail_sync_folder = data.gmail_sync_folder || 'INBOX'
    form.gmail_sync_enabled = Boolean(data.gmail_sync_enabled)
    form.gmail_sync_last_tested_at = data.gmail_sync_last_tested_at || null
    form.gmail_sync_last_status = data.gmail_sync_last_status || null
  } catch (error) {
    toast('No se pudo cargar la configuración de sincronización de Gmail', 'error')
  } finally {
    isLoading.value = false
  }
}

// Probar conexión IMAP en vivo
const testConnection = async () => {
  if (!form.gmail_sync_email) {
    toast('Por favor ingrese el correo de Gmail antes de probar', 'warning')
    return
  }

  if (!form.gmail_sync_password && !form.has_password) {
    toast('Debe ingresar la contraseña de aplicación de 16 caracteres para probar la conexión', 'warning')
    return
  }

  isTesting.value = true
  testResult.value = null

  try {
    const payload = {
      gmail_sync_email: form.gmail_sync_email,
      gmail_sync_password: form.gmail_sync_password || undefined,
      gmail_sync_host: form.gmail_sync_host,
      gmail_sync_port: form.gmail_sync_port,
      gmail_sync_folder: form.gmail_sync_folder,
    }

    const response = await axios.post('/configuration/email-sync/test', payload)
    
    testResult.value = {
      success: true,
      message: response.data.message || 'Conexión IMAP establecida con éxito.',
    }
    
    form.gmail_sync_last_status = 'success'
    form.gmail_sync_last_tested_at = new Date().toISOString()
    
    Swal.fire({
      title: '¡Conexión Exitosa!',
      text: response.data.message || 'Se logró autenticar con Gmail IMAP correctamente.',
      icon: 'success',
      confirmButtonText: 'Entendido',
      customClass: {
        confirmButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-flat bg-primary text-white px-6',
      },
      buttonsStyling: false,
    })
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al conectar con Gmail IMAP. Verifique las credenciales.'
    testResult.value = {
      success: false,
      message: msg,
    }
    form.gmail_sync_last_status = 'error'

    Swal.fire({
      title: 'Fallo de Conexión',
      text: msg,
      icon: 'error',
      confirmButtonText: 'Revisar datos',
      customClass: {
        confirmButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-flat bg-error text-white px-6',
      },
      buttonsStyling: false,
    })
  } finally {
    isTesting.value = false
  }
}

// Guardar la configuración
const saveConfig = async () => {
  if (!form.gmail_sync_email) {
    toast('El correo electrónico de Gmail es obligatorio', 'warning')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      gmail_sync_email: form.gmail_sync_email,
      gmail_sync_password: form.gmail_sync_password || undefined,
      gmail_sync_host: form.gmail_sync_host,
      gmail_sync_port: form.gmail_sync_port,
      gmail_sync_folder: form.gmail_sync_folder,
      gmail_sync_enabled: form.gmail_sync_enabled,
    }

    const response = await axios.post('/configuration/email-sync', payload)
    const data = response.data.data || response.data

    if (form.gmail_sync_password) {
      form.has_password = true
      form.gmail_sync_password = ''
    }

    toast(response.data.message || 'Configuración de sincronización guardada correctamente', 'success')
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al guardar la configuración de sincronización'
    toast(msg, 'error')
  } finally {
    isSaving.value = false
  }
}

// Ejecutar sincronización inmediata de catálogos
const runSyncNow = async () => {
  const result = await Swal.fire({
    title: '¿Sincronizar Catálogos Ahora?',
    text: 'Se conectará a Gmail para buscar los archivos Excel y listas de precios más recientes de las droguerías configuradas.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, Sincronizar',
    cancelButtonText: 'Cancelar',
    customClass: {
      confirmButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-flat bg-primary text-white mr-3 px-6',
      cancelButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-outlined text-secondary px-6',
    },
    buttonsStyling: false,
  })

  if (!result.isConfirmed) return

  isSyncingNow.value = true
  try {
    const response = await axios.post('/configuration/email-sync/run')
    const summary = response.data.data || {}
    const count = summary.processed?.length || 0

    Swal.fire({
      title: '¡Sincronización Finalizada!',
      text: count > 0 
        ? `Se procesaron exitosamente catálogos de ${count} proveedor(es).`
        : 'Sincronización completada. No se encontraron nuevos correos pendientes.',
      icon: 'success',
      confirmButtonText: 'Aceptar',
      customClass: {
        confirmButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-flat bg-primary text-white px-6',
      },
      buttonsStyling: false,
    })
  } catch (error) {
    const msg = error.response?.data?.message || 'Ocurrió un error al procesar la sincronización por correo.'
    Swal.fire({
      title: 'Error de Sincronización',
      text: msg,
      icon: 'error',
      confirmButtonText: 'Entendido',
      customClass: {
        confirmButton: 'v-btn v-btn--density-default v-btn--size-default v-btn--variant-flat bg-error text-white px-6',
      },
      buttonsStyling: false,
    })
  } finally {
    isSyncingNow.value = false
  }
}

onMounted(() => {
  fetchConfig()
})
</script>

<template>
  <div v-if="ability.can('manage', 'admin') || ability.can('manage', 'all')">
    <VCard class="mb-6 rounded-lg elevation-1 position-relative">
      <VProgressLinear
        v-if="isSaving || isTesting || isSyncingNow"
        indeterminate
        color="primary"
        height="4"
        class="position-absolute top-0 left-0 right-0 z-index-2"
      />

      <!-- Cabecera Principal -->
      <VCardItem class="pb-4 pt-6">
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="error" variant="tonal" rounded size="48">
              <VIcon icon="tabler-brand-gmail" size="28" />
            </VAvatar>
            <div>
              <VCardTitle class="text-h5 font-weight-bold">
                Sincronizaciones (Gmail IMAP)
              </VCardTitle>
              <VCardSubtitle class="text-body-2 text-medium-emphasis mt-1">
                Configuración del buzón de correo para la extracción y actualización automática de catálogos y listas de precios de droguerías.
              </VCardSubtitle>
            </div>
          </div>

          <!-- Estado y Acciones Rápidas -->
          <div v-if="!isLoading" class="d-flex align-center gap-2 flex-wrap">
            <VChip
              v-if="form.gmail_sync_last_status === 'success'"
              color="success"
              variant="tonal"
              size="small"
              class="font-weight-medium"
            >
              <VIcon icon="tabler-check" size="16" class="mr-1" />
              Conexión Verificada
            </VChip>
            <VChip
              v-else-if="form.gmail_sync_last_status === 'error'"
              color="error"
              variant="tonal"
              size="small"
              class="font-weight-medium"
            >
              <VIcon icon="tabler-alert-circle" size="16" class="mr-1" />
              Fallo en Verificación
            </VChip>
            <VChip
              v-else
              color="warning"
              variant="tonal"
              size="small"
              class="font-weight-medium"
            >
              <VIcon icon="tabler-clock" size="16" class="mr-1" />
              Sin Probar
            </VChip>
          </div>
        </div>
      </VCardItem>

      <VDivider />

      <!-- Tarjeta Instructiva de Google App Password -->
      <VCardText class="py-4 bg-var-theme-background">
        <div class="d-flex flex-column flex-md-row align-start align-md-center justify-space-between gap-3">
          <div class="d-flex align-start gap-3">
            <VIcon icon="tabler-info-circle" color="info" size="24" class="mt-1" />
            <div>
              <span class="text-subtitle-2 font-weight-bold d-block text-high-emphasis">
                ¿Cómo obtener la Contraseña de Aplicación de Gmail?
              </span>
              <span class="text-caption text-medium-emphasis">
                Google requiere una <strong>Contraseña de Aplicación de 16 caracteres</strong> (no tu clave personal de Gmail).
                Para generarla: 
                1. Activa la verificación en dos pasos en tu cuenta de Google.
                2. Entra en <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer" class="text-primary font-weight-medium">myaccount.google.com/apppasswords</a>.
                3. Escribe un nombre descriptivo (ej: <em>ERP Farmacia</em>) y haz clic en <strong>Crear</strong>.
                4. Copia la clave de 16 letras y pégala a continuación.
              </span>
            </div>
          </div>
          <VBtn
            href="https://myaccount.google.com/apppasswords"
            target="_blank"
            rel="noopener noreferrer"
            variant="tonal"
            color="info"
            size="small"
            density="comfortable"
            prepend-icon="tabler-external-link"
            class="text-none flex-shrink-0"
          >
            Abrir Google App Passwords
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Estado de Carga -->
      <VCardText v-if="isLoading" class="py-8">
        <VRow>
          <VCol cols="12" md="6">
            <VSkeletonLoader type="text, text, text" class="border rounded-lg" />
          </VCol>
          <VCol cols="12" md="6">
            <VSkeletonLoader type="text, text, text" class="border rounded-lg" />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Formulario de Configuración -->
      <VCardText v-else class="py-6">
        <VRow>
          <!-- Switch de Activación General -->
          <VCol cols="12">
            <div class="d-flex align-center justify-space-between p-3 border rounded-lg bg-surface">
              <div>
                <span class="text-subtitle-1 font-weight-bold d-block">
                  Habilitar Sincronización Automática por Correo
                </span>
                <span class="text-caption text-medium-emphasis">
                  Permite que el sistema lea periódicamente los correos entrantes de proveedores para actualizar listas de precios y stock.
                </span>
              </div>
              <VSwitch
                v-model="form.gmail_sync_enabled"
                color="primary"
                hide-details
                inset
                :disabled="!canEdit || isSaving"
              />
            </div>
          </VCol>

          <!-- Input Correo Electrónico -->
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.gmail_sync_email"
              label="Correo Electrónico de Gmail"
              placeholder="ejemplo@farmacia.com"
              prepend-inner-icon="tabler-mail"
              type="email"
              variant="outlined"
              density="comfortable"
              :disabled="!canEdit || isSaving"
              hint="Buzón de Gmail donde recibes las listas de precios de las droguerías"
              persistent-hint
            />
          </VCol>

          <!-- Input Contraseña de Aplicación -->
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.gmail_sync_password"
              :label="form.has_password ? 'Contraseña de Aplicación (Guardada - reescribir para cambiar)' : 'Contraseña de Aplicación de 16 caracteres'"
              placeholder="xxxx xxxx xxxx xxxx"
              prepend-inner-icon="tabler-key"
              :type="showPassword ? 'text' : 'password'"
              :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
              variant="outlined"
              density="comfortable"
              :disabled="!canEdit || isSaving"
              hint="Clave de 16 caracteres generada en Google App Passwords"
              persistent-hint
              @click:append-inner="showPassword = !showPassword"
            />
          </VCol>

          <!-- Toggle de Opciones Avanzadas (Host / Puerto / Carpeta) -->
          <VCol cols="12">
            <VBtn
              variant="text"
              color="secondary"
              density="compact"
              size="small"
              :prepend-icon="showAdvanced ? 'tabler-chevron-up' : 'tabler-chevron-down'"
              @click="showAdvanced = !showAdvanced"
            >
              {{ showAdvanced ? 'Ocultar Opciones de Conexión Avanzadas' : 'Ver Opciones de Conexión Avanzadas (IMAP/Puerto/Carpeta)' }}
            </VBtn>
          </VCol>

          <template v-if="showAdvanced">
            <VCol cols="12" md="4">
              <VTextField
                v-model="form.gmail_sync_host"
                label="Servidor IMAP"
                placeholder="imap.gmail.com"
                prepend-inner-icon="tabler-server"
                variant="outlined"
                density="comfortable"
                :disabled="!canEdit || isSaving"
                hint="Por defecto imap.gmail.com"
                persistent-hint
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.gmail_sync_port"
                label="Puerto IMAP SSL"
                placeholder="993"
                prepend-inner-icon="tabler-hash"
                type="number"
                variant="outlined"
                density="comfortable"
                :disabled="!canEdit || isSaving"
                hint="Puerto seguro SSL (normalmente 993)"
                persistent-hint
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                v-model="form.gmail_sync_folder"
                label="Carpeta a Leer"
                placeholder="INBOX"
                prepend-inner-icon="tabler-folder"
                variant="outlined"
                density="comfortable"
                :disabled="!canEdit || isSaving"
                hint="Carpeta raíz a consultar (por defecto INBOX)"
                persistent-hint
              />
            </VCol>
          </template>

          <!-- Banner de Resultado de Prueba -->
          <VCol v-if="testResult" cols="12">
            <VAlert
              :type="testResult.success ? 'success' : 'error'"
              variant="tonal"
              closable
              class="mb-0"
              @click:close="testResult = null"
            >
              <div class="d-flex align-center gap-2">
                <span class="font-weight-medium">{{ testResult.message }}</span>
              </div>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider v-if="!isLoading" />

      <!-- Barra de Acciones -->
      <VCardActions v-if="!isLoading" class="pa-4 bg-surface d-flex flex-wrap gap-3">
        <!-- Botón Probar Conexión -->
        <VBtn
          variant="tonal"
          color="info"
          density="comfortable"
          prepend-icon="tabler-plug-connected"
          :loading="isTesting"
          :disabled="isSaving || isSyncingNow"
          @click="testConnection"
        >
          Probar Conexión IMAP
        </VBtn>

        <!-- Botón Sincronizar Ahora -->
        <VBtn
          variant="outlined"
          color="success"
          density="comfortable"
          prepend-icon="tabler-refresh"
          :loading="isSyncingNow"
          :disabled="isSaving || isTesting || (!form.has_password && !form.gmail_sync_password)"
          @click="runSyncNow"
        >
          Sincronizar Catálogos Ahora
        </VBtn>

        <VSpacer />

        <!-- Botón Guardar Cambios -->
        <VBtn
          color="primary"
          variant="flat"
          density="comfortable"
          prepend-icon="tabler-device-floppy"
          :loading="isSaving"
          :disabled="!canEdit || isTesting || isSyncingNow"
          @click="saveConfig"
        >
          Guardar Configuración
        </VBtn>
      </VCardActions>
    </VCard>
  </div>

  <!-- Vista sin Permisos -->
  <VCard v-else class="text-center pa-12">
    <VIcon icon="tabler-lock" color="error" size="64" class="mb-4" />
    <h2 class="text-h5 font-weight-bold mb-2">Acceso Denegado</h2>
    <p class="text-body-2 text-medium-emphasis">
      No posee los privilegios requeridos para administrar las sincronizaciones del sistema.
    </p>
  </VCard>
</template>
