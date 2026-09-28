<script setup>
import { onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import { useCrmSettings } from '@/composables/configuration/useCrmSettings'
import CrmModuleCard from '@/components/configuration/CrmModuleCard.vue'

const ability = useAbility()

const {
  enabledCrmViews,
  availableCrmViews,
  isLoading,
  isSaving,
  hasError,
  errorMessage,
  isDirty,
  totalCount,
  activeCount,
  activePercentage,
  allEnabled,
  noneEnabled,
  fetchSettings,
  toggleCrmView,
  setAllViews,
  resetSettings,
  saveSettings,
} = useCrmSettings()

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div v-if="ability.can('manage', 'admin') || ability.can('manage', 'all')">
    <VCard class="mb-6 rounded-lg elevation-1 position-relative">
      <VProgressLinear
        v-if="isSaving"
        indeterminate
        color="primary"
        height="4"
        class="position-absolute top-0 left-0 right-0 z-index-2"
      />

      <!-- Cabecera Principal -->
      <VCardItem class="pb-4 pt-6">
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4">
          <div>
            <VCardTitle class="text-h5 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-address-book" color="primary" size="28" />
              Configuración de Vistas del CRM
            </VCardTitle>
            <VCardSubtitle class="text-body-2 text-medium-emphasis mt-1">
              Control de visibilidad y acceso a los módulos de fidelización, clientes y convenios en la navegación general.
            </VCardSubtitle>
          </div>

          <!-- Métricas y Acciones Rápidas -->
          <div v-if="!isLoading && !hasError" class="d-flex align-center gap-2 flex-wrap">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-medium">
              {{ activeCount }} / {{ totalCount }} Módulos Activos ({{ activePercentage }}%)
            </VChip>
            <VBtn
              size="small"
              variant="outlined"
              color="primary"
              density="comfortable"
              :disabled="allEnabled || isSaving"
              @click="setAllViews(true)"
            >
              Activar Todos
            </VBtn>
            <VBtn
              size="small"
              variant="outlined"
              color="error"
              density="comfortable"
              :disabled="noneEnabled || isSaving"
              @click="setAllViews(false)"
            >
              Desactivar Todos
            </VBtn>
          </div>
        </div>
      </VCardItem>

      <VDivider />

      <!-- Estado de Carga / Skeleton -->
      <VCardText v-if="isLoading" class="py-8">
        <VRow>
          <VCol v-for="n in 5" :key="n" cols="12" sm="6" md="4">
            <VSkeletonLoader type="article, actions" class="border rounded-lg" height="140" />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Estado de Error de Carga -->
      <VCardText v-else-if="hasError" class="py-12 text-center">
        <VIcon icon="tabler-alert-circle" color="error" size="56" class="mb-3" />
        <h3 class="text-h6 font-weight-bold text-error mb-1">
          No se pudo sincronizar la configuración del CRM
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-6">
          {{ errorMessage }}
        </p>
        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-reload"
          density="comfortable"
          @click="fetchSettings"
        >
          Reintentar Carga
        </VBtn>
      </VCardText>

      <!-- Rejilla de Módulos CRM -->
      <VCardText v-else class="py-6">
        <VRow>
          <VCol
            v-for="view in availableCrmViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
          >
            <CrmModuleCard
              :view="view"
              :is-active="enabledCrmViews.includes(view.key)"
              :is-saving="isSaving"
              @toggle="toggleCrmView"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider v-if="!isLoading && !hasError" />

      <!-- Barra de Acciones y Persistencia Explícita -->
      <VCardActions v-if="!isLoading && !hasError" class="pa-4 bg-surface">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="isDirty ? 'tabler-alert-circle' : 'tabler-check'"
            :color="isDirty ? 'warning' : 'success'"
            size="20"
          />
          <span class="text-caption text-medium-emphasis">
            {{ isDirty ? 'Hay cambios sin guardar' : 'Configuración de vistas sincronizada' }}
          </span>
        </div>

        <VSpacer />

        <VBtn
          variant="outlined"
          color="secondary"
          density="comfortable"
          :disabled="!isDirty || isSaving"
          @click="resetSettings"
        >
          Descartar
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          density="comfortable"
          prepend-icon="tabler-device-floppy"
          :loading="isSaving"
          :disabled="!isDirty || isSaving"
          @click="saveSettings"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </div>

  <!-- Vista sin Permisos -->
  <VCard v-else class="text-center pa-12">
    <VIcon icon="tabler-lock" color="error" size="64" class="mb-4" />
    <h2 class="text-h5 font-weight-bold mb-2">Acceso Denegado</h2>
    <p class="text-body-2 text-medium-emphasis">
      No posee los privilegios requeridos para administrar las vistas y módulos del CRM.
    </p>
  </VCard>
</template>
