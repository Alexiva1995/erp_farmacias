<script setup>
import { ref } from 'vue'
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
      a2PreviewData.value = task.preview_data
      a2SuppliersEdit.value = JSON.parse(JSON.stringify(task.preview_data.suppliers || []))
      a2PreviewModal.value = true
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
            a2PreviewData.value = updatedTask.preview_data
            a2SuppliersEdit.value = JSON.parse(JSON.stringify(updatedTask.preview_data.suppliers || []))
            a2PreviewModal.value = true
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
      suppliers: a2SuppliersEdit.value,
    })
    const stats = res.data?.stats || {}
    a2PreviewModal.value = false
    await Swal.fire({
      title: '¡Importación desde a2 Exitosa!',
      icon: 'success',
      html: `
        <div style="text-align:left; font-size:0.95rem; line-height:1.6;">
          <p class="mb-1 text-success"><strong>Proveedores Creados:</strong> ${stats.suppliers_created ?? 0}</p>
          <p class="mb-1 text-primary"><strong>Proveedores Vinculados Existentes:</strong> ${stats.suppliers_linked ?? 0}</p>
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
                Los datos se leen directamente desde las tablas `.dat` de a2 a través de su servicio local en Python. No se duplican proveedores ya registrados ni productos maestros.
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
                    1. Proveedores y Compras (2026)
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae proveedores de `Sproveedor.dat` y compras de `SOperacionInv.dat` del año 2026 sin duplicar droguerías del Master.
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

            <!-- Tarjeta 3: Directorio de Clientes -->
            <VCol cols="12" md="6" class="mt-4">
              <VCard variant="outlined" class="h-100 d-flex flex-column">
                <VCardItem>
                  <VCardTitle class="d-flex align-center gap-2 text-subtitle-1">
                    <VIcon icon="tabler-users" color="primary" />
                    3. Clientes (Directorio y Cédulas)
                  </VCardTitle>
                  <VCardSubtitle class="text-caption">
                    Extrae clientes desde `Sclientes.dat` cruzando por cédula/RIF para no duplicar existentes.
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
      max-width="1200px"
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
            Verifique la homologación de proveedores y productos contra el Catálogo Maestro y Tenant antes de guardar.
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-2">
          <!-- Resumen Métricas -->
          <VRow class="mb-4">
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Proveedores en a2</div>
                <div class="text-h6 font-weight-bold text-primary">{{ a2PreviewData?.summary?.total_suppliers ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Vinculados a Existentes (Sin Duplicar)</div>
                <div class="text-h6 font-weight-bold text-success">{{ a2PreviewData?.summary?.suppliers_matched ?? 0 }}</div>
              </div>
            </VCol>
            <VCol cols="12" sm="4">
              <div class="pa-3 bg-surface rounded text-center border">
                <div class="text-caption text-medium-emphasis">Operaciones Año 2026</div>
                <div class="text-h6 font-weight-bold text-info">{{ a2PreviewData?.summary?.total_operations_year ?? 0 }}</div>
              </div>
            </VCol>
          </VRow>

          <!-- Tabla de Proveedores a2 -->
          <VCard variant="outlined" class="mb-4">
            <VCardItem class="pb-2">
              <VCardTitle class="text-subtitle-2 font-weight-bold">
                Mapeo de Proveedores a2 ➔ ERP
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-caption">
              <thead>
                <tr>
                  <th>RIF a2</th>
                  <th>Nombre a2</th>
                  <th>Teléfono</th>
                  <th>Estado en ERP</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sup, idx) in a2SuppliersEdit" :key="idx">
                  <td>{{ sup.a2_rif || 'N/A' }}</td>
                  <td class="font-weight-medium">{{ sup.a2_name }}</td>
                  <td>{{ sup.a2_phone || '-' }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="sup.is_matched ? 'success' : 'primary'"
                      variant="tonal"
                    >
                      {{ sup.match_reason }}
                    </VChip>
                  </td>
                  <td>
                    <span v-if="sup.is_matched" class="text-success font-weight-bold">
                      ✓ Vinculado a: {{ sup.matched_supplier_name }}
                    </span>
                    <span v-else class="text-primary font-weight-bold">
                      + Se registrará nuevo
                    </span>
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
