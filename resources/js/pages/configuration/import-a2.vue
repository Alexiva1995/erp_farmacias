<script setup>
import { ref, computed } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useAbility } from '@casl/vue'

const ability = useAbility()

// --- ESTADOS Y CONTROL DE INTEGRACIÓN a2 SOFTWAY (DBISAM 2026) ---
const a2Loading = ref(false)
const a2PreviewModal = ref(false)
const a2Importing = ref(false)
const a2PreviewData = ref(null)
const a2SelectedSection = ref('suppliers_payables')
const a2YearFilter = ref(2026)
const a2SuppliersEdit = ref([])
const a2ClientsEdit = ref([])
const existingSuppliersList = ref([])

// Opciones dinámicas para el VSelect de proveedores
const supplierSelectOptions = computed(() => {
  const options = [
    { title: '➕ Registrar como NUEVO proveedor', value: null },
  ]
  existingSuppliersList.value.forEach(s => {
    options.push({
      title: `${s.name} ${s.rif ? '(' + s.rif + ')' : ''}`,
      value: s.id,
    })
  })
  return options
})

// Manejo de cambio en la vinculación manual de proveedores
const onSupplierLinkChange = (sup, selectedId) => {
  if (selectedId) {
    const found = existingSuppliersList.value.find(s => s.id === selectedId)
    sup.selected_supplier_id = selectedId
    sup.is_matched = true
    sup.matched_supplier_name = found ? found.name : 'Vinculado'
    sup.match_reason = 'Vinculado manualmente'
  } else {
    sup.selected_supplier_id = null
    sup.is_matched = false
    sup.matched_supplier_name = null
    sup.match_reason = 'Nuevo Proveedor a registrar'
  }

  // Recalcular métricas de resumen
  if (a2PreviewData.value?.summary) {
    const matched = a2SuppliersEdit.value.filter(s => s.is_matched).length
    a2PreviewData.value.summary.suppliers_matched = matched
    a2PreviewData.value.summary.suppliers_new = a2SuppliersEdit.value.length - matched
  }
}

const a2ProductsEdit = ref([])

const populatePreview = (data) => {
  a2PreviewData.value = data
  a2SuppliersEdit.value = JSON.parse(JSON.stringify(data.suppliers || []))
  a2ClientsEdit.value = JSON.parse(JSON.stringify(data.clients || []))
  a2ProductsEdit.value = JSON.parse(JSON.stringify(data.products_sample || data.products || []))
  existingSuppliersList.value = data.existing_suppliers || []
  a2PreviewModal.value = true
}

const requestA2Preview = async (section) => {
  a2SelectedSection.value = section
  a2Loading.value = true
  try {
    const res = await axios.post('/configuration/data-import/a2/request-preview', {
      section,
      year: a2YearFilter.value,
    })
    const task = res.data?.task
    if (task?.preview_data) {
      populatePreview(task.preview_data)
      toast.success('Datos de a2 previsualizados exitosamente.')
      a2Loading.value = false
    } else if (task?.id) {
      toast.info('Solicitud enviada al puente a2. Esperando extracción...')
      // Polling activo en frontend esperando la respuesta del puente Python
      let attempts = 0
      const checkInterval = setInterval(async () => {
        attempts++
        try {
          const checkRes = await axios.get(`/configuration/data-import/a2/task/${task.id}`)
          const updatedTask = checkRes.data?.task
          if (updatedTask?.status === 'completed' && updatedTask?.preview_data) {
            clearInterval(checkInterval)
            populatePreview(updatedTask.preview_data)
            toast.success('Extracción completada por el puente a2.')
            a2Loading.value = false
          } else if (updatedTask?.status === 'failed') {
            clearInterval(checkInterval)
            toast.error(updatedTask?.error_message || 'El puente a2 reportó un error en la extracción.')
            a2Loading.value = false
          }
        } catch (e) {
          // continuar intentando
        }
        if (attempts >= 40) {
          clearInterval(checkInterval)
          toast.warning('El puente a2 tardó en responder. Verifique la consola de Python.')
          a2Loading.value = false
        }
      }, 1500)
    } else {
      a2Loading.value = false
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al conectar con a2.')
    a2Loading.value = false
  }
}

const confirmAndExecuteA2Import = async () => {
  a2Importing.value = true
  try {
    const res = await axios.post('/configuration/data-import/a2/execute-import', {
      task_id: a2PreviewData.value?.task_id || null,
      section: a2SelectedSection.value,
      suppliers: a2SuppliersEdit.value,
      clients: a2ClientsEdit.value,
      products: a2ProductsEdit.value,
    })
    const stats = res.data?.stats || {}
    a2PreviewModal.value = false
    await Swal.fire({
      title: '¡Importación desde a2 Exitosa!',
      icon: 'success',
      html: `
        <div style="text-align:left; font-size:0.95rem; line-height:1.6;">
          <template v-if="a2SelectedSection === 'products_lots'">
            <p class="mb-1 text-success"><strong>Productos Creados:</strong> ${stats.products_created ?? 0}</p>
            <p class="mb-1 text-primary"><strong>Productos Actualizados:</strong> ${stats.products_updated ?? 0}</p>
            <p class="mb-1 text-info"><strong>Homologados con Master:</strong> ${stats.products_matched_master ?? 0}</p>
          </template>
          <template v-else-if="a2SelectedSection === 'clients'">
            <p class="mb-1 text-success"><strong>Clientes Creados:</strong> ${stats.clients_created ?? 0}</p>
            <p class="mb-1 text-primary"><strong>Clientes Vinculados:</strong> ${stats.clients_linked ?? 0}</p>
            <p class="mb-1 text-success"><strong>Cuentas por Cobrar (CXC):</strong> ${stats.credits_created ?? 0}</p>
          </template>
          <template v-else>
            <p class="mb-1 text-success"><strong>Proveedores Creados:</strong> ${stats.suppliers_created ?? 0}</p>
            <p class="mb-1 text-primary"><strong>Proveedores Vinculados Existentes:</strong> ${stats.suppliers_linked ?? 0}</p>
            <p class="mb-1 text-success"><strong>Facturas CXP Importadas:</strong> ${stats.invoices_created ?? 0}</p>
            <p class="mb-1 text-medium-emphasis"><strong>Facturas Omitidas (Duplicadas):</strong> ${stats.invoices_skipped ?? 0}</p>
          </template>
        </div>
      `,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#E20074',
    })
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al guardar los datos de a2.')
  } finally {
    a2Importing.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-h5">
            <VIcon
              icon="tabler-database-import"
              color="primary"
            />
            Centro de Importación y Sincronización a2 Softway
          </VCardTitle>
          <VCardSubtitle class="text-body-2">
            Extracción y homologación directa desde la base de datos de a2 (DBISAM). Operaciones filtradas exclusivamente para el año 2026, evitando duplicados contra el Catálogo Maestro y Proveedores existentes.
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-0">
          <VAlert
            type="info"
            variant="tonal"
            density="comfortable"
            class="mb-6"
          >
            <div class="d-flex flex-column gap-1">
              <span class="font-weight-bold">Conexión con el Puente Autónomo de a2:</span>
              <span class="text-caption">
                Los datos se leen directamente desde las tablas `.dat` de a2 a través de su servicio local en Python. No se duplican proveedores ya registrados ni facturas existentes.
              </span>
            </div>
          </VAlert>

          <VRow class="mb-4">
            <!-- Tarjeta 1: Proveedores y CXP 2026 -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 d-flex flex-column">
                <VCardItem>
                  <VCardTitle class="d-flex align-center gap-2 text-subtitle-1">
                    <VIcon icon="tabler-building-bank" color="primary" />
                    1. Proveedores y Cuentas por Pagar (2026)
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae proveedores de `Sproveedor.dat` y facturas con saldo pendiente de `Scuentasxpagar.dat` sin duplicar.
                  </VCardSubtitle>
                </VCardItem>
                <VSpacer />
                <VCardText class="pt-0">
                  <VBtn
                    block
                    color="primary"
                    prepend-icon="tabler-eye"
                    :loading="a2Loading && a2SelectedSection === 'suppliers_payables'"
                    :disabled="a2Loading"
                    @click="requestA2Preview('suppliers_payables')"
                  >
                    Consultar y Previsualizar
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Tarjeta 2: Catálogo de Productos e Inventario -->
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="h-100 d-flex flex-column">
                <VCardItem>
                  <VCardTitle class="d-flex align-center gap-2 text-subtitle-1">
                    <VIcon icon="tabler-packages" color="primary" />
                    2. Catálogo de Productos e Inventario
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae productos desde `Sinventario.dat` y los homologa contra el Catálogo Maestro del ERP.
                  </VCardSubtitle>
                </VCardItem>
                <VSpacer />
                <VCardText class="pt-0">
                  <VBtn
                    block
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-eye"
                    :loading="a2Loading && a2SelectedSection === 'products_lots'"
                    :disabled="a2Loading"
                    @click="requestA2Preview('products_lots')"
                  >
                    Consultar y Previsualizar
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Tarjeta 3: Clientes y Cuentas por Cobrar -->
            <VCol cols="12" md="6" class="mt-4">
              <VCard variant="outlined" class="h-100 d-flex flex-column">
                <VCardItem>
                  <VCardTitle class="d-flex align-center gap-2 text-subtitle-1">
                    <VIcon icon="tabler-users" color="primary" />
                    3. Clientes y Cuentas por Cobrar (2026)
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae clientes desde `Sclientes.dat` y cuentas por cobrar pendientes desde `Scuentasxcobrar.dat`.
                  </VCardSubtitle>
                </VCardItem>
                <VSpacer />
                <VCardText class="pt-0">
                  <VBtn
                    block
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-eye"
                    :loading="a2Loading && a2SelectedSection === 'clients'"
                    :disabled="a2Loading"
                    @click="requestA2Preview('clients')"
                  >
                    Consultar y Previsualizar
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Tarjeta 4: Ventas y Transacciones 2026 -->
            <VCol cols="12" md="6" class="mt-4">
              <VCard variant="outlined" class="h-100 d-flex flex-column">
                <VCardItem>
                  <VCardTitle class="d-flex align-center gap-2 text-subtitle-1">
                    <VIcon icon="tabler-shopping-cart" color="primary" />
                    4. Ventas y Transacciones (2026)
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae ventas facturadas en a2 desde `01/01/2026` para estadísticas, rotación y cierres.
                  </VCardSubtitle>
                </VCardItem>
                <VSpacer />
                <VCardText class="pt-0">
                  <VBtn
                    block
                    color="primary"
                    prepend-icon="tabler-eye"
                    :loading="a2Loading && a2SelectedSection === 'sales'"
                    :disabled="a2Loading"
                    @click="requestA2Preview('sales')"
                  >
                    Consultar y Previsualizar
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- =================================================================== -->
    <!-- MODAL DE PREVISUALIZACIÓN Y HOMOLOGACIÓN a2 SOFTWAY -->
    <!-- =================================================================== -->
    <VDialog
      v-model="a2PreviewModal"
      max-width="1280px"
      persistent
    >
      <VCard class="pa-2">
        <VCardItem class="pb-2">
          <VCardTitle class="d-flex align-center justify-space-between text-h6">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-database-import" color="primary" />
              Previsualización y Adaptación a2 (Año {{ a2PreviewData?.summary?.year ?? 2026 }})
            </div>
            <VBtn
              icon="tabler-x"
              variant="text"
              density="compact"
              @click="a2PreviewModal = false"
            />
          </VCardTitle>
          <VCardSubtitle class="text-caption">
            Verifique la homologación de proveedores, facturas CXP y cuentas por cobrar antes de guardar.
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-2">
          <!-- Resumen Métricas Proveedores y CXP -->
          <VRow class="mb-4" v-if="a2SelectedSection === 'suppliers_payables'">
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Proveedores en a2</div>
                <div class="text-h6 font-weight-bold text-primary">{{ a2PreviewData?.summary?.total_suppliers ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Vinculados Existentes</div>
                <div class="text-h6 font-weight-bold text-success">{{ a2PreviewData?.summary?.suppliers_matched ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Facturas CXP Pendientes</div>
                <div class="text-h6 font-weight-bold text-info">{{ a2PreviewData?.summary?.total_payables ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Monto Total CXP (USD)</div>
                <div class="text-h6 font-weight-bold text-warning">${{ Number(a2PreviewData?.summary?.total_payables_usd ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</div>
              </div>
            </VCol>
          </VRow>

          <!-- Resumen Métricas Clientes y CXC -->
          <VRow class="mb-4" v-if="a2SelectedSection === 'clients'">
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Clientes en a2</div>
                <div class="text-h6 font-weight-bold text-primary">{{ a2PreviewData?.summary?.total_clients ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Clientes Coincidentes</div>
                <div class="text-h6 font-weight-bold text-success">{{ a2PreviewData?.summary?.clients_matched ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Cuentas por Cobrar</div>
                <div class="text-h6 font-weight-bold text-info">{{ a2PreviewData?.summary?.total_receivables ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="3">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Saldo por Cobrar (VES)</div>
                <div class="text-h6 font-weight-bold text-warning">{{ Number(a2PreviewData?.summary?.total_receivables_ves ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} Bs</div>
              </div>
            </VCol>
          </VRow>

          <!-- Tabla de Proveedores y Facturas CXP -->
          <VCard variant="outlined" class="mb-4" v-if="a2SelectedSection === 'suppliers_payables'">
            <VCardItem class="pb-2">
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                Mapeo de Proveedores y Cuentas por Pagar (CXP)
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-caption">
              <thead>
                <tr>
                  <th style="width: 120px;">RIF a2</th>
                  <th style="width: 230px;">Nombre a2</th>
                  <th style="width: 130px;">Facturas CXP</th>
                  <th style="width: 150px;">Saldo Pendiente</th>
                  <th style="width: 150px;">Coincidencia</th>
                  <th style="min-width: 280px;">Destino en ERP</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sup, idx) in a2SuppliersEdit" :key="idx">
                  <td>{{ sup.a2_rif || 'N/A' }}</td>
                  <td class="font-weight-medium">{{ sup.a2_name }}</td>
                  <td>
                    <VChip size="x-small" color="info" variant="tonal">
                      {{ (sup.invoices || []).length }} facturas
                    </VChip>
                  </td>
                  <td class="font-weight-bold text-warning">
                    ${{ Number(sup.total_pending_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                  </td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="sup.is_matched ? 'success' : 'primary'"
                      variant="tonal"
                    >
                      {{ sup.match_reason }}
                    </VChip>
                  </td>
                  <td class="py-2">
                    <VSelect
                      v-model="sup.selected_supplier_id"
                      :items="supplierSelectOptions"
                      item-title="title"
                      item-value="value"
                      density="compact"
                      variant="outlined"
                      hide-details
                      class="text-caption"
                      @update:model-value="(val) => onSupplierLinkChange(sup, val)"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>

          <!-- Resumen Métricas Productos e Inventario -->
          <VRow class="mb-4" v-if="a2SelectedSection === 'products_lots'">
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Productos en a2</div>
                <div class="text-h6 font-weight-bold text-primary">{{ a2PreviewData?.summary?.total_products ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Homologados con Catálogo Maestro</div>
                <div class="text-h6 font-weight-bold text-success">{{ a2PreviewData?.summary?.products_matched_master ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Nuevos por Registrar</div>
                <div class="text-h6 font-weight-bold text-warning">
                  {{ (a2PreviewData?.summary?.total_products ?? 0) - (a2PreviewData?.summary?.products_matched_master ?? 0) }}
                </div>
              </div>
            </VCol>
          </VRow>

          <!-- Tabla de Productos a2 -->
          <VCard variant="outlined" class="mb-4" v-if="a2SelectedSection === 'products_lots'">
            <VCardItem class="pb-2">
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                Muestra de Productos extraídos de Sinventario.dat
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-caption">
              <thead>
                <tr>
                  <th style="width: 130px;">Código</th>
                  <th style="min-width: 280px;">Nombre del Producto</th>
                  <th style="width: 180px;">Categoría</th>
                  <th style="width: 150px;">Laboratorio Master</th>
                  <th style="width: 160px;">Estado de Coincidencia</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(p, idx) in a2ProductsEdit" :key="idx">
                  <td class="font-weight-medium">{{ p.code }}</td>
                  <td>{{ p.name }}</td>
                  <td>{{ p.category || '-' }}</td>
                  <td>{{ p.laboratory || '-' }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="p.master_matched ? 'success' : 'primary'"
                      variant="tonal"
                    >
                      {{ p.master_matched ? 'Match Catálogo Maestro' : 'Producto Nuevo' }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>

          <!-- Tabla de Clientes y Cuentas por Cobrar (CXC) -->
          <VCard variant="outlined" class="mb-4" v-if="a2SelectedSection === 'clients'">
            <VCardItem class="pb-2">
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                Directorio de Clientes y Cuentas por Cobrar (CXC)
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-caption">
              <thead>
                <tr>
                  <th style="width: 120px;">Código/Cédula</th>
                  <th style="width: 250px;">Nombre Cliente</th>
                  <th style="width: 120px;">Teléfono</th>
                  <th style="width: 120px;">Documentos CXC</th>
                  <th style="width: 150px;">Saldo Pendiente</th>
                  <th style="width: 150px;">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(cli, idx) in a2ClientsEdit" :key="idx">
                  <td>{{ cli.identification || cli.code }}</td>
                  <td class="font-weight-medium">{{ cli.name }}</td>
                  <td>{{ cli.phone || '-' }}</td>
                  <td>
                    <VChip size="x-small" color="info" variant="tonal">
                      {{ (cli.receivables || []).length }} docs
                    </VChip>
                  </td>
                  <td class="font-weight-bold text-warning">
                    {{ Number(cli.total_pending_amount || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} Bs
                  </td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="cli.is_matched ? 'success' : 'primary'"
                      variant="tonal"
                    >
                      {{ cli.is_matched ? 'Existente en ERP' : 'Nuevo Cliente' }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCardText>

        <VCardActions class="pt-2">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="a2Importing"
            @click="a2PreviewModal = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            :loading="a2Importing"
            :disabled="a2Importing"
            @click="confirmAndExecuteA2Import"
          >
            Confirmar e Importar Datos Adaptados
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>
