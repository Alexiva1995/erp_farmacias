<script setup>
import { ref } from 'vue'
import { usePharmacySettingsForm } from '@/composables/usePharmacySettingsForm'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

const { can } = useAbility()
const logoInputRef = ref(null)
const faviconInputRef = ref(null)
const signatureInputRef = ref(null)

const isDraggingLogo = ref(false)
const isDraggingFavicon = ref(false)
const isDraggingSignature = ref(false)

const {
  form,
  logoPreview,
  faviconPreview,
  signatureStampPreview,
  isLoading,
  isPageLoading,
  isDirty,
  handleLogoSelect,
  handleFaviconSelect,
  handleSignatureStampSelect,
  removeLogo,
  removeFavicon,
  removeSignatureStamp,
  resetForm,
  saveSettings,
} = usePharmacySettingsForm()

const logoHasError = ref(false)
const signatureHasError = ref(false)

const onLogoChange = (e) => {
  const file = e.target.files?.[0]
  if (file) {
    logoHasError.value = false
    handleLogoSelect(file)
  }
}

const onFaviconChange = (e) => {
  const file = e.target.files?.[0]
  if (file) handleFaviconSelect(file)
}

const onSignatureChange = (e) => {
  const file = e.target.files?.[0]
  if (file) {
    signatureHasError.value = false
    handleSignatureStampSelect(file)
  }
}

const onDropLogo = (e) => {
  isDraggingLogo.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) {
    logoHasError.value = false
    handleLogoSelect(file)
  }
}

const onDropFavicon = (e) => {
  isDraggingFavicon.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) handleFaviconSelect(file)
}

const onDropSignature = (e) => {
  isDraggingSignature.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) {
    signatureHasError.value = false
    handleSignatureStampSelect(file)
  }
}

const testingFactory = ref(false)
const factoryTestResult = ref(null)

const testFactoryConnection = async () => {
  testingFactory.value = true
  factoryTestResult.value = null
  try {
    const response = await axios.post('/fiscal/factory/test-connection', {
      ip: form.factory_printer_ip,
      port: form.factory_printer_port ? Number(form.factory_printer_port) : 8090,
    })
    const resData = response.data?.data || response.data
    factoryTestResult.value = resData
    if (resData.success && resData.printer_present) {
      toast.success(resData.message || 'Impresora The Factory HKA conectada correctamente')
    } else if (resData.connected) {
      toast.warning(resData.message || 'Conectado al listener TCP, pero la impresora no responde')
    } else {
      toast.error(resData.message || 'Error al conectar con la impresora Factory')
    }
  } catch (err) {
    console.error('Error al probar conexión Factory:', err)
    factoryTestResult.value = {
      success: false,
      connected: false,
      message: err.response?.data?.message || err.message || 'Error de comunicación',
    }
    toast.error('Error de comunicación con el servicio de The Factory HKA')
  } finally {
    testingFactory.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <!-- Skeleton Loader -->
      <VCard
        v-if="isPageLoading"
        variant="outlined"
        class="pa-6 rounded-lg bg-surface"
      >
        <VSkeletonLoader type="heading" class="w-25 mb-2" />
        <VSkeletonLoader type="subtitle" class="w-50 mb-6" />
        <VRow>
          <VCol cols="12" md="7">
            <VSkeletonLoader type="paragraph, actions" />
          </VCol>
          <VCol cols="12" md="5">
            <VSkeletonLoader type="image" />
          </VCol>
        </VRow>
      </VCard>

      <!-- Formulario Principal -->
      <VCard
        v-else
        variant="flat"
        class="rounded-lg border shadow-sm"
      >
        <VCardItem class="px-6 pt-6 pb-3">
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
                class="rounded-lg"
              >
                <VIcon icon="tabler-building-store" size="28" />
              </VAvatar>
              <div>
                <VCardTitle class="text-h5 font-weight-bold">
                  Datos de la Farmacia e Identidad Corporativa
                </VCardTitle>
                <VCardSubtitle class="text-caption text-medium-emphasis mt-1">
                  Razón social, RIF, serial de máquina fiscal, moneda principal, logotipos y membretes oficiales.
                </VCardSubtitle>
              </div>
            </div>

            <VChip
              v-if="isDirty"
              color="warning"
              variant="tonal"
              size="small"
              prepend-icon="tabler-alert-circle"
            >
              Cambios pendientes por guardar
            </VChip>
          </div>
        </VCardItem>

        <VDivider />

        <VCardText class="px-6 py-5">
          <VForm @submit.prevent="saveSettings">
            <VRow>
              <!-- Columna Izquierda: Parámetros Institucionales y Medios -->
              <VCol cols="12" md="7">
                <!-- Tarjeta 1: Información Fiscal y Legal -->
                <VCard variant="outlined" class="pa-5 rounded-lg mb-5 border">
                  <div class="text-subtitle-1 font-weight-bold mb-4 text-primary d-flex align-center gap-2">
                    <VIcon icon="tabler-certificate" size="20" />
                    Identificación Fiscal y Operativa
                  </div>

                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="form.app_name"
                        label="Nombre Comercial / Razón Social"
                        placeholder="Ej: FARMACIA PRINCIPAL 2026, C.A."
                        prepend-inner-icon="tabler-building"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[v => !!v?.trim() || 'El nombre institucional es obligatorio']"
                        clearable
                      />
                    </VCol>

                    <VCol cols="12" sm="6">
                      <VTextField
                        v-model="form.app_rif"
                        label="RIF / Identificación Tributaria"
                        placeholder="Ej: J-12345678-9"
                        prepend-inner-icon="tabler-receipt-tax"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        clearable
                      />
                    </VCol>

                    <VCol cols="12" sm="6">
                      <VTextField
                        v-model="form.fiscal_printer_serial"
                        label="Serial Máquina Fiscal"
                        placeholder="Ej: EOM0000310"
                        prepend-inner-icon="tabler-printer"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        clearable
                      />
                    </VCol>

                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="form.fiscal_machine_type"
                        :items="[
                          { title: 'Protocolo PNP (Impresora Fiscal Estándar)', value: 'pnp' },
                          { title: 'Factory (The Factory HKA - TCP / Directo)', value: 'factory' },
                          { title: 'Bixolon / HKA Fiscal', value: 'bixolon' },
                          { title: 'Hasar Fiscal', value: 'hasar' },
                          { title: 'Custom / Genérica', value: 'custom' },
                        ]"
                        label="Controlador / Máquina Fiscal"
                        prepend-inner-icon="tabler-cpu"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                      />
                    </VCol>

                    <!-- Campos específicos para The Factory HKA -->
                    <VCol
                      v-if="form.fiscal_machine_type === 'factory'"
                      cols="12"
                    >
                      <VCard
                        variant="tonal"
                        color="primary"
                        class="pa-4 rounded-lg border"
                      >
                        <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                          <div class="d-flex align-center gap-2 font-weight-bold text-subtitle-2">
                            <VIcon icon="tabler-network" size="20" />
                            Parámetros de Conexión The Factory HKA (TCP Listener)
                          </div>
                          <VBtn
                            size="small"
                            color="primary"
                            variant="elevated"
                            prepend-icon="tabler-plug-connected"
                            :loading="testingFactory"
                            :disabled="testingFactory"
                            @click="testFactoryConnection"
                          >
                            Probar Conexión Factory
                          </VBtn>
                        </div>

                        <VRow>
                          <VCol cols="12" sm="6">
                            <VTextField
                              v-model="form.factory_printer_ip"
                              label="IP del TCP Listener"
                              placeholder="Ej: 127.0.0.1"
                              prepend-inner-icon="tabler-server"
                              variant="outlined"
                              density="comfortable"
                              hide-details="auto"
                            />
                          </VCol>

                          <VCol cols="12" sm="6">
                            <VTextField
                              v-model="form.factory_printer_port"
                              label="Puerto TCP Listener"
                              placeholder="Ej: 8090"
                              type="number"
                              prepend-inner-icon="tabler-hash"
                              variant="outlined"
                              density="comfortable"
                              hide-details="auto"
                            />
                          </VCol>
                        </VRow>

                        <!-- Resultado del Test de Conexión -->
                        <VAlert
                          v-if="factoryTestResult"
                          class="mt-3"
                          :type="factoryTestResult.success && factoryTestResult.printer_present ? 'success' : (factoryTestResult.connected ? 'warning' : 'error')"
                          variant="tonal"
                          density="compact"
                          closable
                          @click:close="factoryTestResult = null"
                        >
                          <div class="text-body-2 font-weight-medium">
                            {{ factoryTestResult.message }}
                          </div>
                          <div
                            v-if="factoryTestResult.connected"
                            class="text-caption mt-1"
                          >
                            Endpoint: {{ factoryTestResult.ip }}:{{ factoryTestResult.port }} |
                            Estado: {{ factoryTestResult.status_code || 'N/A' }} |
                            Error: {{ factoryTestResult.error_code || '0' }}
                          </div>
                        </VAlert>
                      </VCard>
                    </VCol>

                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="form.default_currency"
                        :items="['COP', 'USD', 'BS']"
                        label="Moneda Base del Sistema"
                        prepend-inner-icon="tabler-currency-dollar"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VTextarea
                        v-model="form.address"
                        label="Dirección Fiscal y Operativa"
                        placeholder="Ej: Av. Principal, Edificio Central, Nivel PB, Local 1."
                        prepend-inner-icon="tabler-map-pin"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        clearable
                      />
                    </VCol>
                  </VRow>
                </VCard>

                <!-- Tarjeta 2: Logotipo Oficial -->
                <VCard
                  variant="outlined"
                  class="pa-5 rounded-lg mb-5 border transition-all upload-dropzone"
                  :class="{ 'dropzone-active': isDraggingLogo }"
                  @dragover.prevent="isDraggingLogo = true"
                  @dragleave.prevent="isDraggingLogo = false"
                  @drop.prevent="onDropLogo"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <div class="text-subtitle-1 font-weight-bold text-primary d-flex align-center gap-2">
                      <VIcon icon="tabler-photo" size="20" />
                      Logotipo Oficial (Membretes y Reportes PDF)
                    </div>
                    <VChip
                      v-if="logoPreview"
                      color="success"
                      size="x-small"
                      variant="tonal"
                      prepend-icon="tabler-check"
                    >
                      Cargado
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    PNG transparente, JPG, WEBP o SVG. Tamaño máx: 5MB.
                  </div>

                  <input
                    ref="logoInputRef"
                    type="file"
                    accept="image/png, image/jpeg, image/webp, image/svg+xml, .png, .jpg, .jpeg, .webp, .svg"
                    class="d-none"
                    @change="onLogoChange"
                  >

                  <div
                    class="dropzone-box pa-4 rounded-lg text-center cursor-pointer mb-3"
                    :class="{ 'dropzone-box-active': isDraggingLogo }"
                    @click="logoInputRef?.click()"
                  >
                    <VIcon icon="tabler-cloud-upload" size="32" class="text-primary mb-1" />
                    <div class="text-body-2 font-weight-medium">
                      {{ isDraggingLogo ? 'Suelta el logotipo aquí...' : 'Arrastra y suelta el logotipo aquí o haz clic para examinar' }}
                    </div>
                    <div class="text-caption text-disabled mt-1">Soporta PNG, JPG, WEBP, SVG (Máx 5MB)</div>
                  </div>

                  <div class="d-flex align-center gap-3 flex-wrap">
                    <VBtn
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-upload"
                      @click="logoInputRef?.click()"
                    >
                      {{ logoPreview ? 'Reemplazar Logotipo' : 'Cargar Logotipo' }}
                    </VBtn>

                    <VBtn
                      v-if="logoPreview"
                      variant="outlined"
                      color="error"
                      prepend-icon="tabler-trash"
                      @click="removeLogo"
                    >
                      Quitar Logotipo
                    </VBtn>
                  </div>
                </VCard>

                <!-- Tarjeta 3: Favicon e Identidad de Pestaña -->
                <VCard
                  variant="outlined"
                  class="pa-5 rounded-lg mb-5 border transition-all upload-dropzone"
                  :class="{ 'dropzone-active': isDraggingFavicon }"
                  @dragover.prevent="isDraggingFavicon = true"
                  @dragleave.prevent="isDraggingFavicon = false"
                  @drop.prevent="onDropFavicon"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <div class="text-subtitle-1 font-weight-bold text-primary d-flex align-center gap-2">
                      <VIcon icon="tabler-app-window" size="20" />
                      Favicon Institucional (Pestaña del Navegador)
                    </div>
                    <VChip
                      v-if="faviconPreview"
                      color="success"
                      size="x-small"
                      variant="tonal"
                      prepend-icon="tabler-check"
                    >
                      Cargado
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    Icono o vector SVG que se muestra en la pestaña del navegador (ICO, PNG, SVG).
                  </div>

                  <input
                    ref="faviconInputRef"
                    type="file"
                    accept="image/png, image/x-icon, image/vnd.microsoft.icon, image/svg+xml, .ico, .svg, .png"
                    class="d-none"
                    @change="onFaviconChange"
                  >

                  <div
                    class="dropzone-box pa-4 rounded-lg text-center cursor-pointer mb-3"
                    :class="{ 'dropzone-box-active': isDraggingFavicon }"
                    @click="faviconInputRef?.click()"
                  >
                    <VIcon icon="tabler-cloud-upload" size="32" class="text-primary mb-1" />
                    <div class="text-body-2 font-weight-medium">
                      {{ isDraggingFavicon ? 'Suelta el favicon aquí...' : 'Arrastra y suelta el favicon aquí o haz clic para examinar' }}
                    </div>
                    <div class="text-caption text-disabled mt-1">Soporta ICO, PNG, SVG (Máx 2MB)</div>
                  </div>

                  <div class="d-flex align-center gap-3 flex-wrap">
                    <VBtn
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-upload"
                      @click="faviconInputRef?.click()"
                    >
                      {{ faviconPreview ? 'Reemplazar Favicon' : 'Cargar Favicon' }}
                    </VBtn>

                    <VBtn
                      v-if="faviconPreview"
                      variant="outlined"
                      color="error"
                      prepend-icon="tabler-trash"
                      @click="removeFavicon"
                    >
                      Quitar Favicon
                    </VBtn>
                  </div>
                </VCard>

                <!-- Tarjeta 4: Firma y Sello Digital -->
                <VCard
                  variant="outlined"
                  class="pa-5 rounded-lg border transition-all upload-dropzone"
                  :class="{ 'dropzone-active': isDraggingSignature }"
                  @dragover.prevent="isDraggingSignature = true"
                  @dragleave.prevent="isDraggingSignature = false"
                  @drop.prevent="onDropSignature"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <div class="text-subtitle-1 font-weight-bold text-primary d-flex align-center gap-2">
                      <VIcon icon="tabler-signature" size="20" />
                      Firma y Sello Húmedo Digital
                    </div>
                    <VChip
                      v-if="signatureStampPreview"
                      color="success"
                      size="x-small"
                      variant="tonal"
                      prepend-icon="tabler-check"
                    >
                      Cargado
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    Imagen PNG o WEBP con transparencia que se estampará en comprobantes de retención, nóminas y constancias oficiales.
                  </div>

                  <input
                    ref="signatureInputRef"
                    type="file"
                    accept="image/png, image/webp, .png, .webp"
                    class="d-none"
                    @change="onSignatureChange"
                  >

                  <div
                    class="dropzone-box pa-4 rounded-lg text-center cursor-pointer mb-3"
                    :class="{ 'dropzone-box-active': isDraggingSignature }"
                    @click="signatureInputRef?.click()"
                  >
                    <VIcon icon="tabler-cloud-upload" size="32" class="text-primary mb-1" />
                    <div class="text-body-2 font-weight-medium">
                      {{ isDraggingSignature ? 'Suelta la firma y sello aquí...' : 'Arrastra y suelta el archivo aquí o haz clic para examinar' }}
                    </div>
                    <div class="text-caption text-disabled mt-1">Soporta PNG, WEBP transparente (Máx 5MB)</div>
                  </div>

                  <div class="d-flex align-center gap-3 flex-wrap">
                    <VBtn
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-upload"
                      @click="signatureInputRef?.click()"
                    >
                      {{ signatureStampPreview ? 'Reemplazar Firma y Sello' : 'Cargar Firma y Sello' }}
                    </VBtn>

                    <VBtn
                      v-if="signatureStampPreview"
                      variant="outlined"
                      color="error"
                      prepend-icon="tabler-trash"
                      @click="removeSignatureStamp"
                    >
                      Quitar Firma y Sello
                    </VBtn>
                  </div>
                </VCard>
              </VCol>

              <!-- Columna Derecha: Previsualización en Vivo de PDF y Firma -->
              <VCol cols="12" md="5">
                <VCard
                  variant="outlined"
                  class="pa-5 rounded-lg border h-100 d-flex flex-column bg-surface"
                >
                  <div class="text-subtitle-1 font-weight-bold mb-1 text-secondary d-flex align-center gap-2">
                    <VIcon icon="tabler-file-type-pdf" size="20" />
                    Previsualización de Documento PDF
                  </div>
                  <div class="text-caption text-medium-emphasis mb-4">
                    Muestra en tiempo real cómo se verán los encabezados y sellos oficiales.
                  </div>

                  <div class="pdf-mock-sheet pa-4 rounded border flex-grow-1 d-flex flex-column justify-space-between shadow-xs">
                    <div>
                      <!-- Cabecera de Documento -->
                      <div class="d-flex align-start justify-space-between pb-3 border-b-sheet">
                        <div class="pdf-mock-logo-container d-flex align-center justify-center">
                          <img
                            v-if="logoPreview && !logoHasError"
                            :src="logoPreview"
                            alt="Logo"
                            class="pdf-mock-img"
                            @error="logoHasError = true"
                          >
                          <div
                            v-else
                            class="text-center pa-2 text-medium-emphasis border-dashed-mock rounded w-100"
                          >
                            <VIcon icon="tabler-photo-off" size="20" />
                            <div class="text-caption font-italic">Sin logotipo</div>
                          </div>
                        </div>

                        <div class="text-right pl-3 pdf-mock-header-text">
                          <div class="text-caption font-weight-bold text-uppercase text-high-emphasis">
                            {{ form.app_name || 'NOMBRE DE LA FARMACIA' }}
                          </div>
                          <div
                            v-if="form.app_rif"
                            class="text-caption text-medium-emphasis font-weight-medium"
                          >
                            RIF: {{ form.app_rif }}
                          </div>
                          <div
                            v-if="form.fiscal_printer_serial"
                            class="text-caption text-medium-emphasis"
                          >
                            Máquina Fiscal: {{ form.fiscal_printer_serial }} ({{ (form.fiscal_machine_type || 'pnp').toUpperCase() }})
                          </div>
                          <div
                            v-if="form.address"
                            class="text-caption text-disabled text-truncate-2"
                          >
                            {{ form.address }}
                          </div>
                        </div>
                      </div>

                      <!-- Título de Documento Simulado -->
                      <div class="text-center my-3 py-1 bg-grey-100 rounded">
                        <span class="text-overline text-primary font-weight-bold">
                          COMPROBANTE / REPORTE OFICIAL
                        </span>
                      </div>

                      <!-- Líneas de Contenido Simulado -->
                      <div class="opacity-30 mt-2">
                        <div class="mock-line w-100 mb-2" />
                        <div class="mock-line w-75 mb-2" />
                        <div class="mock-line w-50" />
                      </div>
                    </div>

                    <!-- Pie con Sello y Firma Digital -->
                    <div class="pt-3 border-t-sheet d-flex align-center justify-space-between">
                      <div class="text-caption text-disabled">
                        Moneda Base: {{ form.default_currency }}
                      </div>
                      <div class="text-center">
                        <img
                          v-if="signatureStampPreview && !signatureHasError"
                          :src="signatureStampPreview"
                          alt="Firma y Sello"
                          style="max-height: 48px; max-width: 100px; object-fit: contain;"
                          @error="signatureHasError = true"
                        >
                        <div v-else class="text-caption text-medium-emphasis font-italic">
                          [Sin firma y sello]
                        </div>
                        <div class="text-caption font-weight-medium">Firma Autorizada</div>
                      </div>
                    </div>
                  </div>
                </VCard>
              </VCol>

              <!-- Barra de Acciones Inferior -->
              <VCol cols="12" class="mt-4">
                <VRow>
                  <VCol
                    v-if="isDirty"
                    cols="12"
                    sm="6"
                  >
                    <VBtn
                      block
                      size="large"
                      variant="outlined"
                      color="secondary"
                      prepend-icon="tabler-rotate-clockwise"
                      :disabled="isLoading"
                      @click="resetForm"
                    >
                      Descartar Cambios
                    </VBtn>
                  </VCol>

                  <VCol
                    v-if="can('manage', 'all') || can('manage', 'GeneralSetting') || can('manage', 'admin')"
                    cols="12"
                    :sm="isDirty ? 6 : 12"
                  >
                    <VBtn
                      block
                      size="large"
                      type="submit"
                      color="primary"
                      prepend-icon="tabler-device-floppy"
                      :loading="isLoading"
                      :disabled="isLoading || !isDirty"
                    >
                      Guardar Configuración Institucional
                    </VBtn>
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.upload-dropzone {
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.dropzone-box {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.dropzone-box:hover {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.dropzone-box-active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.dropzone-active {
  border-color: rgb(var(--v-theme-primary)) !important;
}

.pdf-mock-sheet {
  min-height: 340px;
  background-color: #ffffff;
  color: #1e293b;
}

.pdf-mock-logo-container {
  width: 120px;
  min-height: 50px;
}

.pdf-mock-img {
  max-width: 120px;
  max-height: 60px;
  object-fit: contain;
}

.border-dashed-mock {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
}

.border-b-sheet {
  border-bottom: 1px solid #e2e8f0;
}

.border-t-sheet {
  border-top: 1px solid #e2e8f0;
}

.pdf-mock-header-text {
  max-width: 65%;
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.mock-line {
  height: 6px;
  background-color: #cbd5e1;
  border-radius: 3px;
}
</style>