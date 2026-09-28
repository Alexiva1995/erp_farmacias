<script setup>
import { onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import { useMenuSettings } from '@/composables/configuration/useMenuSettings'

import MenuCategorySelector from '@/components/configuration/MenuCategorySelector.vue'
import MenuCustomLinkBuilder from '@/components/configuration/MenuCustomLinkBuilder.vue'
import MenuItemList from '@/components/configuration/MenuItemList.vue'

const { can } = useAbility()
const {
  isLoading,
  isCategoriesLoading,
  isInitialLoading,
  isDirty,
  categories,
  menuItems,
  initMenu,
  addCategory,
  addCustomLink,
  moveUp,
  moveDown,
  makeChild,
  extractChild,
  removeItem,
  removeChild,
  saveMenu
} = useMenuSettings()

onMounted(async () => {
  await initMenu()
})
</script>

<template>
  <VRow v-if="can('manage', 'GeneralSetting') || can('manage', 'admin') || can('manage', 'all')">
    <VCol cols="12">
      <!-- Encabezado de Sección -->
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis mb-1">
            Menú del E-commerce
          </h1>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Administra los enlaces y categorías visibles en la barra de navegación superior de la tienda.
          </p>
        </div>

        <VChip
          v-if="isDirty"
          color="warning"
          variant="tonal"
          size="small"
          prepend-icon="tabler-alert-circle"
          class="font-weight-medium align-self-start align-self-sm-center"
        >
          Cambios sin guardar
        </VChip>
      </div>

      <VRow>
        <!-- Panel Lateral Izquierdo: Orígenes de Enlaces -->
        <VCol cols="12" md="4" class="d-flex flex-column ga-6">
          <MenuCategorySelector
            :categories="categories"
            :loading="isCategoriesLoading || isInitialLoading"
            :disabled="isLoading"
            @add-category="addCategory"
          />

          <MenuCustomLinkBuilder
            :disabled="isLoading || isInitialLoading"
            @add-custom-link="addCustomLink"
          />
        </VCol>

        <!-- Panel Central/Derecho: Estructura del Menú -->
        <VCol cols="12" md="8">
          <MenuItemList
            :menu-items="menuItems"
            :loading="isLoading"
            :disabled="isInitialLoading"
            :is-dirty="isDirty"
            @move-up="moveUp"
            @move-down="moveDown"
            @make-child="makeChild"
            @extract-child="extractChild"
            @remove-item="removeItem"
            @remove-child="removeChild"
            @save="saveMenu"
          />
        </VCol>
      </VRow>
    </VCol>
  </VRow>

  <!-- Estado sin permisos -->
  <VRow v-else>
    <VCol cols="12">
      <VCard variant="outlined" class="pa-8 text-center bg-surface">
        <VIcon icon="tabler-lock" size="48" color="error" class="mb-4" />
        <h2 class="text-h6 font-weight-bold mb-2">Acceso Restringido</h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          No tienes permisos suficientes para configurar la estructura de navegación del e-commerce.
        </p>
      </VCard>
    </VCol>
  </VRow>
</template>
