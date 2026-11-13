<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { useAbility } from '@casl/vue'

definePage({
  meta: {
    action: 'read',
    subject: 'configuration',
  },
})

const { can } = useAbility()
const canEdit = computed(() => can('update', 'configuration') || can('manage', 'all'))

const brandingStore = useBrandingStore()
const isLoading = ref(true)
const isSaving = ref(false)
const updatingStationId = ref(null)
const hasError = ref(false)
const errorMessage = ref('')

const kdsMultiStation = ref(true)
const useCourses = ref(true)
const kdsCourses = ref([
  { number: 1, label: '1° Tiempo (Entrada)' },
  { number: 2, label: '2° Tiempo (Fuerte)' },
  { number: 3, label: '3° Tiempo (Postre)' },
])
const stations = ref([])

const defaultCourses = () => [
  { number: 1, label: '1° Tiempo (Entrada)' },
  { number: 2, label: '2° Tiempo (Fuerte)' },
  { number: 3, label: '3° Tiempo (Postre)' },
]

const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const [settingsRes, stationsRes] = await Promise.all([
      axios.get('/general-settings', {
        params: { only: 'kds_multi_station,kds_courses' },
      }),
      axios.get('/kitchen-stations'),
    ])

    const settings = settingsRes.data.data
    if (settings) {
      kdsMultiStation.value = settings.kds_multi_station ?? true
      if (Array.isArray(settings.kds_courses)) {
        useCourses.value = settings.kds_courses.length > 0
        kdsCourses.value = settings.kds_courses.length > 0 ? settings.kds_courses : defaultCourses()
      } else {
        useCourses.value = true
        kdsCourses.value = defaultCourses()
      }
    }

    stations.value = stationsRes.data?.data || []
  } catch (error) {
    console.error('Error cargando configuración de restaurante:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo cargar la configuración de restaurante.'
    toast.error('Error al cargar la configuración')
  } finally {
    isLoading.value = false
  }
}

const saveSettings = async () => {
  if (isLoading.value || !canEdit.value) return
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      kds_multi_station: kdsMultiStation.value,
      kds_courses: useCourses.value ? kdsCourses.value : [],
    })
    await brandingStore.fetchSettings(true)
    toast.success('Configuración de restaurante guardada')
  } catch (error) {
    console.error('Error al guardar configuración de restaurante:', error)
    toast.error('Error al actualizar la configuración')
  } finally {
    isSaving.value = false
  }
}

const toggleStationActive = async (station) => {
  if (!canEdit.value || updatingStationId.value === station.id) return
  updatingStationId.value = station.id
  const nextState = !station.is_active
  try {
    await axios.put(`/kitchen-stations/${station.id}`, {
      name: station.name,
      code: station.code,
      color: station.color,
      icon: station.icon,
      sort_order: station.sort_order ?? 0,
      is_active: nextState,
    })
    station.is_active = nextState
    toast.success(`Estación ${station.name} ${nextState ? 'habilitada' : 'deshabilitada'}`)
  } catch (error) {
    console.error('Error al actualizar estación:', error)
    toast.error('No se pudo actualizar la estación')
  } finally {
    updatingStationId.value = null
  }
}

const addCourse = () => {
  const nextNum = kdsCourses.value.length > 0
    ? Math.max(...kdsCourses.value.map(c => Number(c.number) || 0)) + 1
    : 1
  kdsCourses.value.push({ number: nextNum, label: `${nextNum}° Tiempo` })
}

const removeCourse = (idx) => {
  if (kdsCourses.value.length <= 1) return
  kdsCourses.value.splice(idx, 1)
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <div v-if="isLoading" class="py-12">
      <VSkeletonLoader type="card, list-item-two-line" class="rounded-lg border" />
    </div>

    <VAlert
      v-else-if="hasError"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard v-else variant="flat" class="border rounded-lg">
      <VCardItem>
        <template #prepend>
          <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
            <VIcon icon="tabler-tools-kitchen-2" size="22" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          Configuración de Restaurante y Cocina (KDS)
        </VCardTitle>
        <VCardSubtitle>
          Habilita estaciones de cocina, define si usas tiempos de servicio y guarda tus cambios.
        </VCardSubtitle>
        <template #append>
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-device-floppy"
            :loading="isSaving"
            :disabled="!canEdit || isSaving"
            @click="saveSettings"
          >
            Guardar Configuración
          </VBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-6">
        <VRow>
          <!-- Estaciones de Cocina -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="pa-4 rounded-lg h-100">
              <div class="d-flex align-center justify-space-between gap-3 mb-3">
                <div>
                  <div class="text-subtitle-1 font-weight-bold mb-1">Modo de Estaciones KDS</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ kdsMultiStation
                      ? 'Múltiples estaciones: el KDS permite filtrar por las estaciones habilitadas.'
                      : 'Cocina única: todas las comandas se muestran en una sola pantalla general.' }}
                  </div>
                </div>
                <VSwitch
                  v-model="kdsMultiStation"
                  color="primary"
                  density="comfortable"
                  hide-details
                  :disabled="isSaving || !canEdit"
                />
              </div>

              <template v-if="kdsMultiStation">
                <VDivider class="my-3" />
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-subtitle-2 font-weight-bold">Estaciones Precargadas</span>
                  <VBtn
                    size="x-small"
                    variant="text"
                    color="primary"
                    to="/restaurant/stations"
                    prepend-icon="tabler-external-link"
                  >
                    Gestionar
                  </VBtn>
                </div>

                <div v-if="stations.length > 0" class="d-flex flex-column gap-2">
                  <div
                    v-for="st in stations"
                    :key="st.id"
                    class="d-flex align-center justify-space-between pa-2 rounded border"
                  >
                    <div class="d-flex align-center gap-2">
                      <VAvatar :color="st.color || 'secondary'" variant="tonal" size="30">
                        <VIcon :icon="st.icon || 'tabler-flame'" size="16" />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-bold">{{ st.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ st.code }}</div>
                      </div>
                    </div>
                    <VSwitch
                      :model-value="Boolean(st.is_active)"
                      color="success"
                      density="compact"
                      hide-details
                      :loading="updatingStationId === st.id"
                      :disabled="!canEdit || updatingStationId === st.id"
                      @update:model-value="toggleStationActive(st)"
                    />
                  </div>
                </div>
                <VAlert v-else density="compact" variant="tonal" color="info" class="text-caption">
                  No hay estaciones registradas aún.
                </VAlert>
              </template>
            </VCard>
          </VCol>

          <!-- Tiempos / Cursos de Servicio -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="pa-4 rounded-lg h-100">
              <div class="d-flex align-center justify-space-between gap-3 mb-3">
                <div>
                  <div class="text-subtitle-1 font-weight-bold mb-1">Tiempos / Cursos de Servicio</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ useCourses
                      ? 'Agrupa los platos en cocina por tiempos (Entrada, Fuerte, Postre).'
                      : 'Modo general: los platos se muestran directo sin separar por tiempos.' }}
                  </div>
                </div>
                <VSwitch
                  v-model="useCourses"
                  color="primary"
                  density="comfortable"
                  hide-details
                  :disabled="isSaving || !canEdit"
                />
              </div>

              <template v-if="useCourses">
                <VDivider class="my-3" />
                <div class="d-flex align-center justify-space-between mb-3">
                  <span class="text-subtitle-2 font-weight-bold">Nombres de Tiempos</span>
                  <VBtn
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-plus"
                    :disabled="isSaving || !canEdit"
                    @click="addCourse"
                  >
                    Agregar Tiempo
                  </VBtn>
                </div>

                <div class="d-flex flex-column gap-2">
                  <div
                    v-for="(course, idx) in kdsCourses"
                    :key="idx"
                    class="d-flex align-center gap-2"
                  >
                    <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                      #{{ course.number }}
                    </VChip>
                    <VTextField
                      v-model="course.label"
                      density="compact"
                      variant="outlined"
                      hide-details
                      placeholder="Ej: 1° Tiempo (Entrada)"
                      :disabled="isSaving || !canEdit"
                    />
                    <VBtn
                      v-if="kdsCourses.length > 1"
                      icon="tabler-trash"
                      size="small"
                      color="error"
                      variant="text"
                      :disabled="isSaving || !canEdit"
                      @click="removeCourse(idx)"
                    />
                  </div>
                </div>
              </template>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
