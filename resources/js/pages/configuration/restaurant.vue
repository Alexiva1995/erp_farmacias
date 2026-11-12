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
const hasError = ref(false)
const errorMessage = ref('')

const kdsMultiStation = ref(true)
const kdsCourses = ref([
  { number: 1, label: '1° Tiempo (Entrada)' },
  { number: 2, label: '2° Tiempo (Fuerte)' },
  { number: 3, label: '3° Tiempo (Postre)' },
])

const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'kds_multi_station,kds_courses' },
    })
    const settings = response.data.data
    if (settings) {
      kdsMultiStation.value = settings.kds_multi_station ?? true
      kdsCourses.value = Array.isArray(settings.kds_courses) && settings.kds_courses.length > 0
        ? settings.kds_courses
        : [
            { number: 1, label: '1° Tiempo (Entrada)' },
            { number: 2, label: '2° Tiempo (Fuerte)' },
            { number: 3, label: '3° Tiempo (Postre)' },
          ]
    }
  } catch (error) {
    console.error('Error cargando configuración de restaurante:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo cargar la configuración de restaurante.'
    toast.error('Error al cargar la configuración')
  } finally {
    isLoading.value = false
  }
}

let saveDebounceTimer = null
const updateSettings = () => {
  if (isLoading.value || !canEdit.value) return
  isSaving.value = true
  if (saveDebounceTimer) clearTimeout(saveDebounceTimer)

  saveDebounceTimer = setTimeout(async () => {
    try {
      await axios.post('/general-settings', {
        kds_multi_station: kdsMultiStation.value,
        kds_courses: kdsCourses.value,
      })
      await brandingStore.fetchSettings(true)
      toast.success('Configuración de restaurante actualizada')
    } catch (error) {
      console.error('Error al guardar configuración de restaurante:', error)
      toast.error('Error al actualizar la configuración')
    } finally {
      isSaving.value = false
    }
  }, 350)
}

const addCourse = () => {
  const nextNum = kdsCourses.value.length > 0
    ? Math.max(...kdsCourses.value.map(c => Number(c.number) || 0)) + 1
    : 1
  kdsCourses.value.push({ number: nextNum, label: `${nextNum}° Tiempo` })
  updateSettings()
}

const removeCourse = (idx) => {
  if (kdsCourses.value.length <= 1) return
  kdsCourses.value.splice(idx, 1)
  updateSettings()
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
          Define el modo de estaciones de cocina y personaliza los tiempos de servicio de tus comandas.
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-6">
        <VRow>
          <VCol cols="12" md="5">
            <VCard variant="outlined" class="pa-4 rounded-lg h-100">
              <div class="d-flex align-center justify-space-between gap-3">
                <div>
                  <div class="text-subtitle-1 font-weight-bold mb-1">Modo de Estaciones KDS</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ kdsMultiStation
                      ? 'Múltiples estaciones: el KDS permite filtrar por Parrilla, Frío, Barra, etc.'
                      : 'Cocina única: todas las comandas se muestran en una sola pantalla sin filtros de estación.' }}
                  </div>
                </div>
                <VSwitch
                  v-model="kdsMultiStation"
                  color="primary"
                  density="comfortable"
                  hide-details
                  :disabled="isSaving || !canEdit"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCard>
          </VCol>

          <VCol cols="12" md="7">
            <VCard variant="outlined" class="pa-4 rounded-lg h-100">
              <div class="d-flex align-center justify-space-between mb-3">
                <div>
                  <div class="text-subtitle-1 font-weight-bold">Tiempos / Cursos de Servicio</div>
                  <div class="text-caption text-medium-emphasis">
                    Personaliza los nombres de los tiempos (Entrada, Fuerte, Postre, etc.) que verá el chef en el KDS.
                  </div>
                </div>
                <VBtn
                  size="small"
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
                    @update:model-value="updateSettings"
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
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
