<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'

const ability = useAbility()

// Permisos CASL
const canEdit = computed(() => {
  return (
    ability.can('manage', 'admin') ||
    ability.can('manage', 'all') ||
    ability.can('edit', 'Configuration')
  )
})

// Pestaña activa
const activeTab = ref('templates')

// Estados de carga
const isLoading = ref(true)
const isSaving = ref(false)
const isTesting = ref(false)
const showPassword = ref(false)

// Configuración SMTP
const smtpForm = reactive({
  mail_mailer: 'smtp',
  mail_host: '',
  mail_port: 587,
  mail_username: '',
  mail_password: '',
  has_password: false,
  mail_encryption: 'tls',
  mail_from_address: '',
  mail_from_name: '',
})

// Plantillas de E-commerce
const activeTemplateKey = ref('order_created')

const templates = reactive({
  order_created: {
    title: 'Pedido Recibido',
    description: 'Enviado al cliente cuando realiza un pedido en la tienda online',
    subject: '',
    body: '',
    enabled: true,
  },
  order_approved: {
    title: 'Pedido Confirmado',
    description: 'Enviado cuando el pago es validado o el pedido pasa a confirmado',
    subject: '',
    body: '',
    enabled: true,
  },
  order_shipped: {
    title: 'Pedido Enviado',
    description: 'Enviado cuando el pedido sale a despacho o entrega',
    subject: '',
    body: '',
    enabled: true,
  },
})

// Diálogo de prueba
const testDialog = ref(false)
const testEmail = ref('')

// Diálogo de vista previa
const previewDialog = ref(false)

// Shortcodes disponibles
const shortcodes = [
  { code: '{cliente_nombre}', desc: 'Nombre del cliente' },
  { code: '{cliente_email}', desc: 'Correo electrónico' },
  { code: '{cliente_telefono}', desc: 'Teléfono de contacto' },
  { code: '{cliente_direccion}', desc: 'Dirección de entrega' },
  { code: '{pedido_id}', desc: 'Código del pedido (ej. #1042)' },
  { code: '{pedido_fecha}', desc: 'Fecha y hora del pedido' },
  { code: '{pedido_total}', desc: 'Total a pagar con moneda' },
  { code: '{metodo_pago}', desc: 'Método de pago seleccionado' },
  { code: '{metodo_envio}', desc: 'Método de envío seleccionado' },
  { code: '{tienda_nombre}', desc: 'Nombre de la farmacia / tienda' },
  { code: '{tabla_productos}', desc: 'Tabla con fotos, tonos, precios y subtotales' },
]

// Plantilla actual seleccionada
const currentTemplate = computed(() => templates[activeTemplateKey.value] || templates.order_created)

// Inserción de shortcode en textarea
const insertShortcode = (code) => {
  if (!currentTemplate.value) return
  currentTemplate.value.body = (currentTemplate.value.body || '') + ' ' + code + ' '
  toast(`Shortcode ${code} insertado`, 'info')
}

// Cargar configuración desde el backend
const fetchConfig = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/configuration/emails')
    const data = response.data?.data || response.data || {}

    // Asignar SMTP
    smtpForm.mail_mailer = data.mail_mailer || 'smtp'
    smtpForm.mail_host = data.mail_host || ''
    smtpForm.mail_port = data.mail_port || 587
    smtpForm.mail_username = data.mail_username || ''
    smtpForm.mail_password = ''
    smtpForm.has_password = Boolean(data.has_password)
    smtpForm.mail_encryption = data.mail_encryption || 'tls'
    smtpForm.mail_from_address = data.mail_from_address || ''
    smtpForm.mail_from_name = data.mail_from_name || ''

    // Asignar plantillas
    if (data.ecommerce_email_templates) {
      ['order_created', 'order_approved', 'order_shipped'].forEach(key => {
        if (data.ecommerce_email_templates[key]) {
          templates[key].subject = data.ecommerce_email_templates[key].subject || ''
          templates[key].body = data.ecommerce_email_templates[key].body || ''
          templates[key].enabled = data.ecommerce_email_templates[key].enabled !== false
        }
      })
    }
  } catch (error) {
    console.error('Error al cargar configuración de correos:', error)
    toast('No se pudo cargar la configuración de correos', 'error')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración completa
const saveConfig = async () => {
  if (!canEdit.value) return

  isSaving.value = true
  try {
    const payload = {
      mail_mailer: smtpForm.mail_mailer,
      mail_host: smtpForm.mail_host,
      mail_port: Number(smtpForm.mail_port),
      mail_username: smtpForm.mail_username,
      mail_encryption: smtpForm.mail_encryption,
      mail_from_address: smtpForm.mail_from_address,
      mail_from_name: smtpForm.mail_from_name,
      ecommerce_email_templates: {
        order_created: {
          subject: templates.order_created.subject,
          body: templates.order_created.body,
          enabled: Boolean(templates.order_created.enabled),
        },
        order_approved: {
          subject: templates.order_approved.subject,
          body: templates.order_approved.body,
          enabled: Boolean(templates.order_approved.enabled),
        },
        order_shipped: {
          subject: templates.order_shipped.subject,
          body: templates.order_shipped.body,
          enabled: Boolean(templates.order_shipped.enabled),
        },
      },
    }

    if (smtpForm.mail_password) {
      payload.mail_password = smtpForm.mail_password
    }

    const res = await axios.post('/configuration/emails', payload)
    toast(res.data?.message || 'Configuración de correos guardada exitosamente', 'success')
    smtpForm.password = ''
    fetchConfig()
  } catch (error) {
    console.error('Error al guardar configuración:', error)
    const msg = error.response?.data?.message || 'Ocurrió un error al guardar'
    toast(msg, 'error')
  } finally {
    isSaving.value = false
  }
}

// Probar conexión SMTP
const testConnection = async () => {
  if (!testEmail.value) {
    toast('Por favor ingresa un correo para la prueba', 'warning')
    return
  }

  isTesting.value = true
  try {
    const payload = {
      mail_host: smtpForm.mail_host,
      mail_port: Number(smtpForm.mail_port),
      mail_username: smtpForm.mail_username,
      mail_password: smtpForm.mail_password,
      mail_encryption: smtpForm.mail_encryption,
      mail_from_address: smtpForm.mail_from_address,
      mail_from_name: smtpForm.mail_from_name,
      test_email: testEmail.value,
    }

    const res = await axios.post('/configuration/emails/test', payload)
    Swal.fire({
      icon: 'success',
      title: '¡Prueba Exitosa!',
      text: res.data?.message || 'El correo de prueba fue enviado correctamente.',
      confirmButtonText: 'Aceptar',
    })
    testDialog.value = false
  } catch (error) {
    const msg = error.response?.data?.message || 'No se pudo enviar el correo de prueba. Revisa credenciales y puertos.'
    Swal.fire({
      icon: 'error',
      title: 'Error de Envío',
      text: msg,
      confirmButtonText: 'Cerrar',
    })
  } finally {
    isTesting.value = false
  }
}

// Vista previa renderizada
const previewRenderedBody = computed(() => {
  let text = currentTemplate.value.body || ''
  const sampleTable = `
    <table style="width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 13px; margin: 15px 0;">
      <thead>
        <tr style="background: #f1f5f9; text-align: left;">
          <th style="padding: 8px; border: 1px solid #cbd5e1;">Producto</th>
          <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: center;">Cant.</th>
          <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: right;">Precio</th>
          <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: right;">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="padding: 8px; border: 1px solid #e2e8f0;">
            <strong>Labial Mate Larga Duración</strong><br>
            <span style="font-size: 11px; color: #64748b;">Tono / Color: Rojo Pasión (#E50914)</span>
          </td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">2</td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">$ 12.50</td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">$ 25.00</td>
        </tr>
        <tr>
          <td style="padding: 8px; border: 1px solid #e2e8f0;">
            <strong>Vitamina C Sérum Facial</strong>
          </td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">1</td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">$ 18.00</td>
          <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">$ 18.00</td>
        </tr>
      </tbody>
      <tfoot>
        <tr style="background: #f8fafc; font-weight: bold;">
          <td colspan="3" style="padding: 8px; border: 1px solid #cbd5e1; text-align: right;">Total:</td>
          <td style="padding: 8px; border: 1px solid #cbd5e1; text-align: right; color: #0f172a;">$ 43.00</td>
        </tr>
      </tfoot>
    </table>
  `

  text = text.replace(/{cliente_nombre}/g, 'María Pérez')
             .replace(/{cliente_email}/g, 'maria.perez@example.com')
             .replace(/{cliente_telefono}/g, '+58 412-1234567')
             .replace(/{cliente_direccion}/g, 'Av. Principal, Edif. Los Rosales, Apto 4B, Caracas')
             .replace(/{pedido_id}/g, '#1048')
             .replace(/{pedido_fecha}/g, new Date().toLocaleString())
             .replace(/{pedido_total}/g, '$ 43.00')
             .replace(/{metodo_pago}/g, 'Pago Móvil / Transferencia')
             .replace(/{metodo_envio}/g, 'Envío a Domicilio (MRW)')
             .replace(/{tienda_nombre}/g, 'Farmacia & E-commerce Central')
             .replace(/{tabla_productos}/g, sampleTable)

  return text.replace(/\n/g, '<br>')
})

onMounted(() => {
  fetchConfig()
})
</script>

<template>
  <VContainer fluid class="py-4">
    <!-- Encabezado Principal -->
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="8">
        <h2 class="text-h4 font-weight-bold mb-1">
          Configuración de Correos y Plantillas
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Gestiona las credenciales de envío SMTP y personaliza las notificaciones automáticas por correo electrónico para pedidos e-commerce.
        </p>
      </VCol>
      <VCol cols="12" md="4" class="text-md-end">
        <VBtn
          color="secondary"
          variant="outlined"
          class="mr-2"
          prepend-icon="tabler-mail-forward"
          @click="testDialog = true"
        >
          Probar Envío
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="isSaving"
          :disabled="!canEdit || isLoading"
          @click="saveConfig"
        >
          Guardar Cambios
        </VBtn>
      </VCol>
    </VRow>

    <!-- Estado de Carga Inicial -->
    <VSkeletonLoader
      v-if="isLoading"
      type="card, article"
      class="mb-4"
    />

    <template v-else>
      <!-- Pestañas de Navegación -->
      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mb-6"
        color="primary"
      >
        <VTab value="templates">
          <VIcon start icon="tabler-template" />
          Plantillas de Pedidos
        </VTab>
        <VTab value="smtp">
          <VIcon start icon="tabler-server-cog" />
          Servidor Saliente (SMTP)
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- TAB 1: PLANTILLAS DE PEDIDOS -->
        <VWindowItem value="templates">
          <VRow>
            <!-- Selector lateral de plantillas -->
            <VCol cols="12" md="3">
              <VCard variant="outlined" class="mb-4">
                <VCardItem class="pb-2">
                  <VCardTitle class="text-subtitle-1 font-weight-bold">
                    Eventos de Pedido
                  </VCardTitle>
                </VCardItem>
                <VDivider />
                <VList density="comfortable" nav class="py-2">
                  <VListItem
                    :active="activeTemplateKey === 'order_created'"
                    color="primary"
                    prepend-icon="tabler-shopping-cart-plus"
                    @click="activeTemplateKey = 'order_created'"
                  >
                    <VListItemTitle class="font-weight-medium">Pedido Creado</VListItemTitle>
                    <VListItemSubtitle>Al registrar la orden</VListItemSubtitle>
                    <template #append>
                      <VChip
                        size="x-small"
                        :color="templates.order_created.enabled ? 'success' : 'secondary'"
                        variant="tonal"
                      >
                        {{ templates.order_created.enabled ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </template>
                  </VListItem>

                  <VListItem
                    :active="activeTemplateKey === 'order_approved'"
                    color="primary"
                    prepend-icon="tabler-circle-check"
                    @click="activeTemplateKey = 'order_approved'"
                  >
                    <VListItemTitle class="font-weight-medium">Pedido Confirmado</VListItemTitle>
                    <VListItemSubtitle>Al validar el pago</VListItemSubtitle>
                    <template #append>
                      <VChip
                        size="x-small"
                        :color="templates.order_approved.enabled ? 'success' : 'secondary'"
                        variant="tonal"
                      >
                        {{ templates.order_approved.enabled ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </template>
                  </VListItem>

                  <VListItem
                    :active="activeTemplateKey === 'order_shipped'"
                    color="primary"
                    prepend-icon="tabler-truck-delivery"
                    @click="activeTemplateKey = 'order_shipped'"
                  >
                    <VListItemTitle class="font-weight-medium">Pedido Enviado</VListItemTitle>
                    <VListItemSubtitle>Al iniciar despacho</VListItemSubtitle>
                    <template #append>
                      <VChip
                        size="x-small"
                        :color="templates.order_shipped.enabled ? 'success' : 'secondary'"
                        variant="tonal"
                      >
                        {{ templates.order_shipped.enabled ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
              </VCard>

              <!-- Ayuda de Shortcodes -->
              <VCard variant="tonal" color="primary" class="pa-2">
                <VCardText class="pa-3">
                  <div class="d-flex align-center mb-2">
                    <VIcon icon="tabler-variable" class="mr-2" />
                    <span class="font-weight-bold">Shortcodes</span>
                  </div>
                  <p class="text-caption mb-3">
                    Haz clic en cualquier etiqueta para insertarla directamente en el cuerpo del mensaje:
                  </p>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="sc in shortcodes"
                      :key="sc.code"
                      size="small"
                      variant="outlined"
                      class="cursor-pointer mb-1"
                      @click="insertShortcode(sc.code)"
                    >
                      {{ sc.code }}
                      <VTooltip activator="parent" location="top">
                        {{ sc.desc }}
                      </VTooltip>
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Editor de Plantilla -->
            <VCol cols="12" md="9">
              <VCard variant="outlined">
                <VCardItem class="pb-3">
                  <div class="d-flex justify-space-between align-center flex-wrap gap-2">
                    <div>
                      <VCardTitle class="text-h6 font-weight-bold">
                        {{ currentTemplate.title }}
                      </VCardTitle>
                      <VCardSubtitle>
                        {{ currentTemplate.description }}
                      </VCardSubtitle>
                    </div>
                    <div class="d-flex align-center gap-3">
                      <VSwitch
                        v-model="currentTemplate.enabled"
                        color="success"
                        hide-details
                        label="Habilitar envío automático"
                        density="compact"
                      />
                      <VBtn
                        color="info"
                        variant="tonal"
                        size="small"
                        prepend-icon="tabler-eye"
                        @click="previewDialog = true"
                      >
                        Vista Previa
                      </VBtn>
                    </div>
                  </div>
                </VCardItem>
                <VDivider />

                <VCardText class="pt-4">
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="currentTemplate.subject"
                        label="Asunto del Correo"
                        placeholder="Ej. Tu pedido {pedido_id} ha sido recibido con éxito"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="tabler-mail"
                        hint="Puedes incluir shortcodes en el asunto como {pedido_id} o {tienda_nombre}"
                        persistent-hint
                      />
                    </VCol>

                    <VCol cols="12">
                      <div class="d-flex justify-space-between align-center mb-1">
                        <label class="text-caption font-weight-bold text-medium-emphasis">
                          Cuerpo del Correo (Formato HTML / Texto enriquecido)
                        </label>
                        <span class="text-caption text-medium-emphasis">
                          Soporta etiquetas HTML (&lt;p&gt;, &lt;strong&gt;, etc.)
                        </span>
                      </div>
                      <VTextarea
                        v-model="currentTemplate.body"
                        rows="14"
                        variant="outlined"
                        density="comfortable"
                        placeholder="Escribe el contenido del correo..."
                        persistent-hint
                        hint="Usa {tabla_productos} en la línea donde quieras presentar la lista con fotos, tonos y totales."
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 2: SERVIDOR SMTP -->
        <VWindowItem value="smtp">
          <VCard variant="outlined">
            <VCardItem class="pb-3">
              <VCardTitle class="text-h6 font-weight-bold">
                Parámetros de Conexión SMTP
              </VCardTitle>
              <VCardSubtitle>
                Configura el servidor saliente que emitirá las notificaciones de los clientes.
              </VCardSubtitle>
            </VCardItem>
            <VDivider />

            <VCardText class="pt-4">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="smtpForm.mail_host"
                    label="Servidor SMTP (Host)"
                    placeholder="smtp.gmail.com o mail.mitienda.com"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-server"
                  />
                </VCol>

                <VCol cols="12" md="3">
                  <VTextField
                    v-model.number="smtpForm.mail_port"
                    label="Puerto SMTP"
                    type="number"
                    placeholder="587 o 465"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-hash"
                  />
                </VCol>

                <VCol cols="12" md="3">
                  <VSelect
                    v-model="smtpForm.mail_encryption"
                    label="Cifrado / Protocolo"
                    :items="[
                      { title: 'TLS (Recomendado - 587)', value: 'tls' },
                      { title: 'SSL (Recomendado - 465)', value: 'ssl' },
                      { title: 'STARTTLS', value: 'starttls' },
                      { title: 'Ninguno (No recomendado)', value: 'null' },
                    ]"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="smtpForm.mail_username"
                    label="Usuario / Correo Electrónico SMTP"
                    placeholder="notificaciones@mitienda.com"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-user"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="smtpForm.mail_password"
                    :type="showPassword ? 'text' : 'password'"
                    label="Contraseña SMTP / Contraseña de Aplicación"
                    :placeholder="smtpForm.has_password ? '•••••••••••••••• (dejar en blanco para no modificar)' : 'Introduce la contraseña'"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-lock"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="smtpForm.mail_from_name"
                    label="Nombre del Remitente"
                    placeholder="Ej. Farmacia Central - E-commerce"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-building-store"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="smtpForm.mail_from_address"
                    label="Dirección de Correo del Remitente"
                    placeholder="pedidos@mitienda.com"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-at"
                  />
                </VCol>
              </VRow>

              <VAlert
                type="info"
                variant="tonal"
                class="mt-4"
                density="comfortable"
              >
                <div class="text-caption">
                  <strong>Nota sobre proveedores como Gmail o Outlook:</strong> Si utilizas Gmail, debes generar y utilizar una <em>Contraseña de Aplicación</em> (App Password) con la autenticación en dos pasos activada en tu cuenta de Google.
                </div>
              </VAlert>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </template>

    <!-- Modal de Prueba de Envío -->
    <VDialog v-model="testDialog" max-width="500">
      <VCard>
        <VCardTitle class="d-flex align-center pt-4 px-4 font-weight-bold">
          <VIcon icon="tabler-mail-forward" class="mr-2 text-primary" />
          Probar Servidor de Correo
        </VCardTitle>
        <VDivider />
        <VCardText class="pt-4">
          <p class="text-body-2 text-medium-emphasis mb-3">
            Ingresa un correo electrónico de destino para comprobar que la conexión SMTP y el despacho funcionan correctamente.
          </p>
          <VTextField
            v-model="testEmail"
            label="Correo de Destino"
            placeholder="tucorreo@gmail.com"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="tabler-mail"
            :disabled="isTesting"
          />
        </VCardText>
        <VCardActions class="pb-4 px-4 justify-end">
          <VBtn
            variant="text"
            color="secondary"
            :disabled="isTesting"
            @click="testDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isTesting"
            prepend-icon="tabler-send"
            @click="testConnection"
          >
            Enviar Prueba
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Vista Previa -->
    <VDialog v-model="previewDialog" max-width="700">
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center pt-4 px-4 font-weight-bold">
          <span>Vista Previa: {{ currentTemplate.title }}</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="previewDialog = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <div class="mb-3 pa-2 rounded bg-grey-lighten-4 border">
            <span class="text-caption font-weight-bold">Asunto simulado: </span>
            <span class="text-caption text-high-emphasis">
              {{ currentTemplate.subject.replace('{pedido_id}', '#1048').replace('{tienda_nombre}', 'Farmacia Central') }}
            </span>
          </div>

          <div
            class="pa-4 border rounded bg-white"
            style="min-height: 250px; color: #1e293b; line-height: 1.6;"
            v-html="previewRenderedBody"
          />
        </VCardText>
        <VCardActions class="pb-4 px-4 justify-end">
          <VBtn variant="tonal" color="primary" @click="previewDialog = false">
            Cerrar Vista Previa
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.gap-1 {
  gap: 4px;
}
.gap-2 {
  gap: 8px;
}
.gap-3 {
  gap: 12px;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
