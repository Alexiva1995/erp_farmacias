<script setup>
import { ref } from 'vue'
import axios from 'axios'
import InventoryProductsPage from '@/pages/inventory/products/index.vue'

const syncing = ref(false)
const homologating = ref(false)
const refreshKey = ref(0)

const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const homologationDialog = ref({
  show: false,
  success: true,
  message: '',
  sources: [],
  masterTotals: {},
  tenants: [],
  log: '',
})

const syncFarmatodoImages = async () => {
  if (syncing.value) return
  syncing.value = true

  try {
    const { data } = await axios.post('/api/master-catalog/sync-farmatodo-images')
    const stats = data?.data || {}
    snackbar.value = {
      show: true,
      color: stats.aborted ? 'warning' : 'success',
      text: `${data?.message || 'Completado'} — Procesados: ${stats.processed ?? 0}, Actualizados: ${stats.updated ?? 0}, No encontrados: ${stats.not_found ?? 0}`,
    }
  } catch (error) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: error?.response?.data?.message || 'Error al sincronizar imágenes.',
    }
  } finally {
    syncing.value = false
  }
}

const consolidateAndHomologate = async () => {
  if (homologating.value) return
  homologating.value = true

  try {
    const { data } = await axios.post('/api/master-catalog/consolidate-homologate')
    const report = data?.data?.report || {}

    homologationDialog.value = {
      show: true,
      success: Boolean(data?.success),
      message: data?.message || 'Consolidación y homologación completada.',
      sources: Array.isArray(report.sources) ? report.sources : [],
      masterTotals: report.master_totals || {},
      tenants: Array.isArray(report.tenants) ? report.tenants : [],
      log: data?.data?.log || '',
    }
    refreshKey.value += 1
  } catch (error) {
    homologationDialog.value = {
      show: true,
      success: false,
      message: error?.response?.data?.message || 'Error al ejecutar la consolidación y homologación global.',
      sources: [],
      masterTotals: {},
      tenants: [],
      log: error?.response?.data?.data?.log || '',
    }
  } finally {
    homologating.value = false
  }
}
</script>

<template>
  <div>
    <v-alert
      type="info"
      variant="tonal"
      color="primary"
      density="compact"
      class="mb-4"
      border="start"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Inventario</strong>
          <span class="text-body-2 ms-2">
            Los productos creados o modificados aquí actualizan el diccionario oficial de todas las farmacias.
          </span>
        </div>
        <div class="d-flex align-center gap-2 flex-wrap">
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            prepend-icon="tabler-refresh"
            :loading="homologating"
            :disabled="homologating || syncing"
            @click="consolidateAndHomologate"
          >
            Actualizar y Homologar Tenants
          </v-btn>
          <v-btn
            color="primary"
            variant="outlined"
            size="small"
            prepend-icon="tabler-photo-down"
            :loading="syncing"
            :disabled="syncing || homologating"
            @click="syncFarmatodoImages"
          >
            Sincronizar Imágenes (Lote 20)
          </v-btn>
          <v-chip
            color="primary"
            size="small"
            variant="flat"
          >
            MASTER
          </v-chip>
        </div>
      </div>
    </v-alert>

    <InventoryProductsPage :key="refreshKey" :is-master="true" />

    <!-- Modal de resultado de Consolidación y Homologación Global -->
    <v-dialog v-model="homologationDialog.show" max-width="820" scrollable>
      <v-card rounded="lg">
        <v-card-title class="d-flex align-center justify-space-between pa-4 border-b">
          <div class="d-flex align-center ga-2">
            <v-icon
              :icon="homologationDialog.success ? 'tabler-circle-check' : 'tabler-alert-triangle'"
              :color="homologationDialog.success ? 'success' : 'error'"
            />
            <span class="text-subtitle-1 font-weight-bold">
              Resultado de Actualización y Homologación Master
            </span>
          </div>
          <v-btn icon="tabler-x" variant="text" size="small" @click="homologationDialog.show = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <v-alert
            :type="homologationDialog.success ? 'success' : 'error'"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ homologationDialog.message }}
          </v-alert>

          <!-- Totales consolidados en tovaerp_master -->
          <div v-if="Object.keys(homologationDialog.masterTotals).length > 0" class="mb-4">
            <div class="text-xs font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Totales Consolidados en tovaerp_master
            </div>
            <div class="d-flex flex-wrap ga-2">
              <v-chip
                v-for="(total, tableName) in homologationDialog.masterTotals"
                :key="tableName"
                color="primary"
                variant="tonal"
                size="small"
                class="font-weight-bold"
              >
                {{ tableName }}: {{ total }}
              </v-chip>
            </div>
          </div>

          <!-- Fuentes consolidadas -->
          <div v-if="homologationDialog.sources.length > 0" class="mb-4">
            <div class="text-xs font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Catálogos Extraídos hacia el Master
            </div>
            <v-table density="compact" class="border rounded">
              <thead>
                <tr>
                  <th>Fuente</th>
                  <th class="text-end">Productos Nuevos</th>
                  <th class="text-end">Enriquecidos (Foto / Datos)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(src, idx) in homologationDialog.sources" :key="idx">
                  <td class="font-weight-medium">
                    {{ src.name }}
                    <span v-if="src.error" class="text-error text-xs ms-2">({{ src.error }})</span>
                  </td>
                  <td class="text-end">{{ src.inserted_products ?? 0 }}</td>
                  <td class="text-end">{{ src.enriched_products ?? 0 }}</td>
                </tr>
              </tbody>
            </v-table>
          </div>

          <!-- Detalle por tenant / central -->
          <div v-if="homologationDialog.tenants.length > 0" class="mb-4">
            <div class="text-xs font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Homologación de IDs y Trazabilidad por Base de Datos
            </div>
            <v-table density="compact" class="border rounded">
              <thead>
                <tr>
                  <th>Base / Tenant</th>
                  <th>Estado</th>
                  <th class="text-end">IDs Homologados</th>
                  <th class="text-end">Reubicados</th>
                  <th class="text-end">Duplicados Fusionados</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(t, idx) in homologationDialog.tenants" :key="idx">
                  <td class="font-weight-medium">{{ t.name }}</td>
                  <td>
                    <v-chip
                      size="x-small"
                      :color="t.status === 'ok' ? 'success' : 'error'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ t.status === 'ok' ? 'OK' : 'Error' }}
                    </v-chip>
                    <span v-if="t.error" class="text-error text-xs ms-1">{{ t.error }}</span>
                  </td>
                  <td class="text-end">{{ t.remapped ?? 0 }}</td>
                  <td class="text-end">{{ t.displaced ?? 0 }}</td>
                  <td class="text-end">{{ t.merged_duplicates ?? 0 }}</td>
                </tr>
              </tbody>
            </v-table>
          </div>

          <!-- Log técnico colapsable -->
          <v-expansion-panels v-if="homologationDialog.log" variant="accordion">
            <v-expansion-panel title="Ver log técnico completo">
              <v-expansion-panel-text>
                <pre class="text-xs overflow-x-auto pa-2 bg-grey-lighten-4 rounded">{{ homologationDialog.log }}</pre>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>

        <v-card-actions class="pa-4 border-t justify-end">
          <v-btn color="primary" variant="flat" @click="homologationDialog.show = false">
            Cerrar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="5000"
      location="bottom end"
    >
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>
