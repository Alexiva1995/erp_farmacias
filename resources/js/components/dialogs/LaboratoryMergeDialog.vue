<script setup>
import axios from '@/plugins/axios'
import { computed, ref, watch } from 'vue'
import { toast } from 'vue3-toastify'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  laboratories: {
    type: Array,
    default: () => [],
  },
  initialLaboratory: {
    type: Object,
    default: null,
  },
  isRestaurant: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'merged'])

const targetLaboratoryId = ref(null)
const sourceLaboratoryIds = ref([])
const isSubmitting = ref(false)
const confirmUnderstand = ref(false)

const entityLabel = computed(() => (props.isRestaurant ? 'Marca' : 'Laboratorio'))
const entityLabelPlural = computed(() => (props.isRestaurant ? 'Marcas' : 'Laboratorios'))

watch(
  () => props.isDialogVisible,
  val => {
    if (val) {
      targetLaboratoryId.value = props.initialLaboratory?.id || null
      sourceLaboratoryIds.value = []
      confirmUnderstand.value = false
    }
  },
)

// Si el usuario selecciona como principal uno que estaba en la lista de duplicados, quitarlo de duplicados
watch(targetLaboratoryId, newTargetId => {
  if (newTargetId && sourceLaboratoryIds.value.includes(newTargetId)) {
    sourceLaboratoryIds.value = sourceLaboratoryIds.value.filter(id => id !== newTargetId)
  }
})

const formattedLaboratories = computed(() => {
  return props.laboratories.map(lab => ({
    ...lab,
    displayTitle: `#${lab.id} - ${lab.name}`,
  }))
})

const availableSourceLaboratories = computed(() => {
  return formattedLaboratories.value.filter(l => l.id !== targetLaboratoryId.value)
})

const targetLaboratory = computed(() => {
  return props.laboratories.find(l => l.id === targetLaboratoryId.value) || null
})

const selectedSourceLaboratories = computed(() => {
  return props.laboratories.filter(l => sourceLaboratoryIds.value.includes(l.id))
})

const totalSourceProducts = computed(() => {
  return selectedSourceLaboratories.value.reduce((acc, lab) => acc + Number(lab.products_count || 0), 0)
})

const canSubmit = computed(() => {
  return (
    Boolean(targetLaboratoryId.value) &&
    sourceLaboratoryIds.value.length > 0 &&
    !sourceLaboratoryIds.value.includes(targetLaboratoryId.value) &&
    confirmUnderstand.value &&
    !isSubmitting.value
  )
})

const closeDialog = () => {
  if (isSubmitting.value) return
  emit('update:isDialogVisible', false)
}

const useInitialAsSourceInstead = () => {
  if (!props.initialLaboratory?.id) return
  const initId = props.initialLaboratory.id
  if (!sourceLaboratoryIds.value.includes(initId)) {
    sourceLaboratoryIds.value = [...sourceLaboratoryIds.value, initId]
  }
  if (targetLaboratoryId.value === initId) {
    targetLaboratoryId.value = null
  }
}

const handleMerge = async () => {
  if (!canSubmit.value) return

  isSubmitting.value = true
  try {
    const response = await axios.post('/inventory/laboratories-manage/merge', {
      target_laboratory_id: targetLaboratoryId.value,
      source_laboratory_ids: sourceLaboratoryIds.value,
    })

    toast.success(response.data?.message || `${entityLabelPlural.value} fusionados exitosamente en todas las bases.`)
    emit('merged', response.data?.data)
    emit('update:isDialogVisible', false)
  } catch (error) {
    console.error('Error al fusionar laboratorios:', error)
    const msg =
      error.response?.data?.message ||
      error.response?.data?.errors?.source_laboratory_ids?.[0] ||
      'Ocurrió un error al fusionar los laboratorios.'
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="720"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-var-theme-background">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="38">
            <VIcon icon="tabler-arrows-join-2" size="22" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">
              Fusionar {{ entityLabelPlural }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Unifica {{ entityLabelPlural.toLowerCase() }} duplicados en el Catálogo Maestro, la Principal y todos los Tenants
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :disabled="isSubmitting"
          @click="closeDialog"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <VAlert
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-5 text-body-2"
          icon="tabler-alert-triangle"
        >
          Todos los <strong>productos</strong>, <strong>vínculos de proveedores</strong> y <strong>asignaciones de comisiones</strong> de los {{ entityLabelPlural.toLowerCase() }} absorbidos pasarán al {{ entityLabel.toLowerCase() }} principal en <strong>tovaerp_master, la base central y todos los tenants</strong>, y los duplicados serán eliminados definitivamente.
        </VAlert>

        <VRow>
          <!-- Laboratorio Principal (el que se conserva) -->
          <VCol cols="12">
            <div class="d-flex align-center justify-space-between mb-1">
              <div class="text-subtitle-2 font-weight-bold text-success d-flex align-center gap-1">
                <VIcon icon="tabler-shield-check" size="18" />
                1. {{ entityLabel }} Principal (SE CONSERVA)
              </div>
              <VBtn
                v-if="props.initialLaboratory && targetLaboratoryId === props.initialLaboratory.id"
                variant="text"
                size="x-small"
                color="warning"
                prepend-icon="tabler-switch-vertical"
                @click="useInitialAsSourceInstead"
              >
                Pasar #{{ props.initialLaboratory.id }} a duplicado
              </VBtn>
            </div>

            <VAutocomplete
              v-model="targetLaboratoryId"
              :items="formattedLaboratories"
              item-title="displayTitle"
              item-value="id"
              :placeholder="`Buscar ${entityLabel.toLowerCase()} que quedará activo...`"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details="auto"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem v-bind="itemProps">
                  <template #Subtitle>
                    <span class="text-xs text-medium-emphasis">
                      Grupo: {{ item.raw.group?.name || 'Sin grupo' }}
                    </span>
                  </template>
                  <template #append>
                    <VChip
                      size="x-small"
                      :color="Number(item.raw.products_count || 0) > 0 ? 'primary' : 'secondary'"
                      variant="tonal"
                      label
                      class="font-weight-bold"
                    >
                      {{ item.raw.products_count || 0 }} prod.
                    </VChip>
                  </template>
                </VListItem>
              </template>
            </VAutocomplete>

            <VCard
              v-if="targetLaboratory"
              variant="tonal"
              color="success"
              class="mt-2 pa-3 rounded-lg"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="font-weight-black text-body-2 text-uppercase">
                    #{{ targetLaboratory.id }} - {{ targetLaboratory.name }}
                  </div>
                  <div class="text-caption">
                    Grupo: {{ targetLaboratory.group?.name || 'Sin grupo' }} | Productos actuales: {{ targetLaboratory.products_count || 0 }}
                  </div>
                </div>
                <VChip size="x-small" color="success" variant="flat">
                  Quedará único
                </VChip>
              </div>
            </VCard>
          </VCol>

          <!-- Flecha visual -->
          <VCol cols="12" class="d-flex justify-center py-1">
            <VAvatar color="primary" variant="tonal" size="34">
              <VIcon icon="tabler-arrow-up" size="20" />
            </VAvatar>
          </VCol>

          <!-- Laboratorios Duplicados (los que se absorben y eliminan) -->
          <VCol cols="12">
            <div class="text-subtitle-2 font-weight-bold mb-1 text-error d-flex align-center gap-1">
              <VIcon icon="tabler-trash-x" size="18" />
              2. {{ entityLabelPlural }} Duplicados (SE ABSORBEN Y ELIMINAN)
            </div>
            <VAutocomplete
              v-model="sourceLaboratoryIds"
              :items="availableSourceLaboratories"
              item-title="displayTitle"
              item-value="id"
              :placeholder="`Selecciona uno o varios ${entityLabelPlural.toLowerCase()} duplicados...`"
              variant="outlined"
              density="comfortable"
              multiple
              chips
              closable-chips
              clearable
              hide-details="auto"
              :disabled="!targetLaboratoryId"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem v-bind="itemProps">
                  <template #Subtitle>
                    <span class="text-xs text-medium-emphasis">
                      Grupo: {{ item.raw.group?.name || 'Sin grupo' }}
                    </span>
                  </template>
                  <template #append>
                    <VChip
                      size="x-small"
                      :color="Number(item.raw.products_count || 0) > 0 ? 'warning' : 'secondary'"
                      variant="tonal"
                      label
                      class="font-weight-bold"
                    >
                      {{ item.raw.products_count || 0 }} prod.
                    </VChip>
                  </template>
                </VListItem>
              </template>
            </VAutocomplete>

            <VCard
              v-if="selectedSourceLaboratories.length > 0 && targetLaboratory"
              variant="tonal"
              color="error"
              class="mt-2 pa-3 rounded-lg"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                <div>
                  <div class="font-weight-bold text-body-2">
                    Se absorberán {{ selectedSourceLaboratories.length }} {{ selectedSourceLaboratories.length === 1 ? entityLabel.toLowerCase() : entityLabelPlural.toLowerCase() }}
                    ({{ totalSourceProducts }} producto(s) asociados en esta vista)
                  </div>
                  <div class="text-caption">
                    Pasarán al ID <strong>#{{ targetLaboratory.id }} ({{ targetLaboratory.name }})</strong> en todas las bases de datos.
                  </div>
                </div>
                <VChip size="x-small" color="error" variant="flat">
                  Se eliminarán {{ selectedSourceLaboratories.length }}
                </VChip>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Confirmación explícita -->
        <div
          v-if="targetLaboratory && selectedSourceLaboratories.length > 0"
          class="mt-4 pa-3 rounded-lg border bg-var-theme-background"
        >
          <VCheckbox
            v-model="confirmUnderstand"
            color="primary"
            hide-details
            density="compact"
          >
            <template #label>
              <span class="text-body-2">
                Confirmo fusionar
                <strong>{{ selectedSourceLaboratories.map(l => `#${l.id} ${l.name}`).join(', ') }}</strong>
                dentro de
                <strong>#{{ targetLaboratory.id }} {{ targetLaboratory.name }}</strong>
                en el Master, la base principal y todos los tenants.
              </span>
            </template>
          </VCheckbox>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-arrows-join-2"
          :loading="isSubmitting"
          :disabled="!canSubmit"
          @click="handleMerge"
        >
          Fusionar {{ entityLabelPlural }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
