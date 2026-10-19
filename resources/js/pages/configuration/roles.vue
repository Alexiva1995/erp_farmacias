<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAbility } from '@casl/vue'
import { useAuthStore } from '@/stores/auth'
import { useRolePermissions } from '@/composables/configuration/useRolePermissions'
import { toast, confirmDialog } from '@/plugins/sweetalert'

const ability = useAbility()
const authStore = useAuthStore()

const {
  roles,
  isLoading,
  isSaving,
  errorMessage,
  menuTree,
  allAvailableKeys,
  fetchRoles,
  createRole,
  updateRole,
  saveRoleMenuPermissions,
  deleteRole,
} = useRolePermissions()

// Estado para diálogo de crear/editar rol
const isRoleDialogVisible = ref(false)
const isEditingRole = ref(false)
const roleForm = ref({
  id: null,
  name: '',
})
const roleFormErrors = ref({})

// Estado para diálogo de permisos de menú
const isPermissionsDialogVisible = ref(false)
const selectedRole = ref(null)
const selectedPermissions = ref([])
const searchQuery = ref('')
const expandedPanels = ref([])

// Cargar roles al montar
onMounted(async () => {
  await fetchRoles()
})

// Filtrado de árbol de menú por buscador
const filteredMenuTree = computed(() => {
  if (!searchQuery.value.trim()) {
    return menuTree.value
  }

  const query = searchQuery.value.toLowerCase().trim()

  function filterNodes(nodes) {
    return nodes
      .map(node => {
        const titleMatches = (node.title || '').toLowerCase().includes(query)
        const keyMatches = (node.key || '').toLowerCase().includes(query)
        let matchingChildren = []

        if (node.children?.length) {
          matchingChildren = filterNodes(node.children)
        }

        if (titleMatches || keyMatches || matchingChildren.length > 0) {
          return {
            ...node,
            children: matchingChildren.length > 0 ? matchingChildren : node.children,
          }
        }

        return null
      })
      .filter(Boolean)
  }

  return filterNodes(menuTree.value)
})

// Cantidad de permisos activos seleccionados
const selectedCount = computed(() => selectedPermissions.value.length)
const totalAvailableCount = computed(() => allAvailableKeys.value.length)

// Determinar si el rol seleccionado es Administrador
const isSelectedRoleAdmin = computed(() => {
  if (!selectedRole.value) return false
  const name = (selectedRole.value.name || '').toLowerCase()
  return selectedRole.value.id === 1 || name.includes('admin')
})

// Abrir modal de creación de rol
function openCreateRoleDialog() {
  isEditingRole.value = false
  roleForm.value = { id: null, name: '' }
  roleFormErrors.value = {}
  isRoleDialogVisible.value = true
}

// Abrir modal de edición de nombre de rol
function openEditRoleDialog(role) {
  isEditingRole.value = true
  roleForm.value = { id: role.id, name: role.name }
  roleFormErrors.value = {}
  isRoleDialogVisible.value = true
}

// Guardar rol (creación o edición)
async function handleSaveRole() {
  if (!roleForm.value.name.trim()) {
    roleFormErrors.value = { name: 'El nombre del rol es obligatorio.' }
    return
  }

  roleFormErrors.value = {}
  let result

  if (isEditingRole.value) {
    result = await updateRole(roleForm.value.id, { name: roleForm.value.name.trim() })
  } else {
    result = await createRole({ name: roleForm.value.name.trim() })
  }

  if (result.success) {
    toast.success(isEditingRole.value ? 'Rol actualizado exitosamente' : 'Rol creado exitosamente')
    isRoleDialogVisible.value = false
  } else {
    toast.error(result.message)
  }
}

// Abrir modal para configurar permisos del menú
function openPermissionsDialog(role) {
  selectedRole.value = role

  // Si menu_permissions es array o string JSON, cargarlo; si es null, todos por defecto
  let initialPermissions = []
  if (Array.isArray(role.menu_permissions)) {
    initialPermissions = [...role.menu_permissions]
  } else if (typeof role.menu_permissions === 'string') {
    try {
      const parsed = JSON.parse(role.menu_permissions)
      initialPermissions = Array.isArray(parsed) ? parsed : [...allAvailableKeys.value]
    } catch (e) {
      initialPermissions = [...allAvailableKeys.value]
    }
  } else {
    initialPermissions = [...allAvailableKeys.value]
  }
  selectedPermissions.value = initialPermissions

  // Desplegar todos los paneles al abrir
  expandedPanels.value = menuTree.value.map((_, index) => index)
  isPermissionsDialogVisible.value = true
}

// Set reactivo para búsquedas O(1) en la UI de permisos
const selectedPermissionsSet = computed(() => new Set(selectedPermissions.value))

// Verificar si una clave está seleccionada
function isPermissionSelected(key) {
  return selectedPermissionsSet.value.has(key)
}

// Conmutar un permiso individual
function togglePermission(key) {
  const index = selectedPermissions.value.indexOf(key)
  if (index >= 0) {
    selectedPermissions.value.splice(index, 1)
  } else {
    selectedPermissions.value.push(key)
  }
}

// Conmutar un módulo completo con todos sus submódulos
function toggleModule(moduleNode) {
  const allKeys = moduleNode.allDescendantKeys || [moduleNode.key]
  const set = selectedPermissionsSet.value
  const allSelected = allKeys.every(k => set.has(k))

  if (allSelected) {
    // Desmarcar todos los descendientes
    const toRemoveSet = new Set(allKeys)
    selectedPermissions.value = selectedPermissions.value.filter(k => !toRemoveSet.has(k))
  } else {
    // Marcar todos los descendientes
    const union = new Set([...selectedPermissions.value, ...allKeys])
    selectedPermissions.value = Array.from(union)
  }
}

// Verificar estado de selección de un módulo principal con complejidad O(K)
function getModuleCheckState(moduleNode) {
  const allKeys = moduleNode.allDescendantKeys || [moduleNode.key]
  const set = selectedPermissionsSet.value
  let count = 0

  for (let i = 0; i < allKeys.length; i++) {
    if (set.has(allKeys[i])) {
      count++
    }
  }

  if (count === 0) return 'none'
  if (count === allKeys.length) return 'all'
  return 'some'
}

// Seleccionar todos los elementos del menú
function selectAllPermissions() {
  selectedPermissions.value = [...allAvailableKeys.value]
}

// Desmarcar todos los elementos del menú
function deselectAllPermissions() {
  selectedPermissions.value = []
}

// Guardar los permisos del menú configurados
async function handleSaveMenuPermissions() {
  if (!selectedRole.value) return

  // Asegurar que si es Admin, siempre conserve la clave de configuración de roles
  let permissionsToSave = [...selectedPermissions.value]
  if (isSelectedRoleAdmin.value) {
    const essentialKeys = ['configuration-roles', 'module::configuración']
    essentialKeys.forEach(k => {
      if (!permissionsToSave.includes(k)) {
        permissionsToSave.push(k)
      }
    })
  }

  const result = await saveRoleMenuPermissions(selectedRole.value.id, permissionsToSave)

  if (result.success) {
    toast.success('Permisos de menú guardados exitosamente')
    
    // Si el rol modificado es el del usuario actual, sincronizar en tiempo real
    if (authStore.user?.role_id === selectedRole.value.id) {
      if (!authStore.user.role) {
        authStore.user.role = {}
      }
      authStore.user.role.menu_permissions = permissionsToSave
    }

    isPermissionsDialogVisible.value = false
  } else {
    toast.error(result.message)
  }
}

// Confirmar y eliminar un rol
async function handleDeleteRole(role) {
  if (role.users_count > 0) {
    toast.warning(`No es posible eliminar el rol "${role.name}" porque tiene ${role.users_count} usuario(s) asignado(s).`)
    return
  }

  const confirmed = await confirmDialog({
    title: `¿Eliminar el rol "${role.name}"?`,
    text: 'Esta acción no se puede deshacer.',
    confirmButtonText: 'Sí, eliminar',
    confirmButtonColor: '#ea5455',
  })

  if (!confirmed) return

  const result = await deleteRole(role.id)
  if (result.success) {
    toast.success('Rol eliminado correctamente')
  } else {
    toast.error(result.message)
  }
}
</script>

<template>
  <div v-if="ability.can('manage', 'admin') || ability.can('manage', 'all')">
    <!-- Encabezado Principal -->
    <VCard class="mb-6 rounded-lg elevation-1">
      <VCardItem class="pb-4 pt-6">
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center ga-4">
          <div>
            <VCardTitle class="text-h5 font-weight-bold d-flex align-center ga-2">
              <VIcon icon="tabler-shield-check" color="primary" size="28" />
              Gestión de Roles y Permisos de Menú
            </VCardTitle>
            <VCardSubtitle class="text-body-2 text-medium-emphasis mt-1">
              Administra los roles del sistema y configura con precisión qué módulos y pantallas puede visualizar cada rol en la barra lateral.
            </VCardSubtitle>
          </div>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreateRoleDialog"
          >
            Nuevo Rol
          </VBtn>
        </div>
      </VCardItem>
    </VCard>

    <!-- Error Global -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      closable
      class="mb-6"
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Tabla de Roles -->
    <VCard class="rounded-lg elevation-1">
      <!-- Indicador de carga -->
      <VProgressLinear
        v-if="isLoading"
        indeterminate
        color="primary"
      />

      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th class="text-left font-weight-bold">ID</th>
            <th class="text-left font-weight-bold">NOMBRE DEL ROL</th>
            <th class="text-center font-weight-bold">USUARIOS ASIGNADOS</th>
            <th class="text-center font-weight-bold">VISIBILIDAD DE MENÚ</th>
            <th class="text-end font-weight-bold pe-6">ACCIONES</th>
          </tr>
        </thead>

        <tbody>
          <!-- Estado Vacío -->
          <tr v-if="!isLoading && roles.length === 0">
            <td colspan="5" class="text-center py-8">
              <div class="d-flex flex-column align-center justify-center ga-2">
                <VIcon icon="tabler-shield-off" size="48" color="medium-emphasis" />
                <span class="text-h6 text-medium-emphasis">No hay roles registrados</span>
                <span class="text-body-2 text-medium-emphasis">Crea un nuevo rol para comenzar a definir permisos.</span>
                <VBtn
                  color="primary"
                  variant="outlined"
                  size="small"
                  class="mt-2"
                  @click="openCreateRoleDialog"
                >
                  Crear Primer Rol
                </VBtn>
              </div>
            </td>
          </tr>

          <!-- Listado de Roles -->
          <tr
            v-for="role in roles"
            :key="role.id"
            class="table-row-hover"
          >
            <td class="text-medium-emphasis font-weight-medium">
              #{{ role.id }}
            </td>

            <td>
              <div class="d-flex align-center ga-2">
                <span class="font-weight-bold text-high-emphasis">{{ role.name }}</span>
                <VChip
                  v-if="role.id === 1 || role.name.toLowerCase().includes('admin')"
                  color="primary"
                  size="x-small"
                  variant="tonal"
                >
                  Sistema
                </VChip>
              </div>
            </td>

            <td class="text-center">
              <VChip
                :color="role.users_count > 0 ? 'info' : 'default'"
                size="small"
                variant="tonal"
                prepend-icon="tabler-users"
              >
                {{ role.users_count }} {{ role.users_count === 1 ? 'usuario' : 'usuarios' }}
              </VChip>
            </td>

            <td class="text-center">
              <VChip
                v-if="!role.menu_permissions"
                color="success"
                size="small"
                variant="tonal"
                prepend-icon="tabler-world"
              >
                Acceso Total
              </VChip>
              <VChip
                v-else
                color="warning"
                size="small"
                variant="tonal"
                prepend-icon="tabler-filter"
              >
                Personalizado ({{ role.menu_permissions.length }} ítems)
              </VChip>
            </td>

            <td class="text-end pe-4">
              <div class="d-inline-flex align-center ga-1">
                <!-- Botón Configurar Menú -->
                <VTooltip text="Configurar visibilidad del menú" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon
                      variant="text"
                      color="primary"
                      size="small"
                      @click="openPermissionsDialog(role)"
                    >
                      <VIcon icon="tabler-layout-sidebar" size="20" />
                    </VBtn>
                  </template>
                </VTooltip>

                <!-- Botón Editar Nombre -->
                <VTooltip text="Editar nombre del rol" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon
                      variant="text"
                      color="secondary"
                      size="small"
                      @click="openEditRoleDialog(role)"
                    >
                      <VIcon icon="tabler-edit" size="20" />
                    </VBtn>
                  </template>
                </VTooltip>

                <!-- Botón Eliminar -->
                <VTooltip
                  :text="role.users_count > 0 ? 'No se puede eliminar: tiene usuarios asociados' : 'Eliminar rol'"
                  location="top"
                >
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon
                      variant="text"
                      color="error"
                      size="small"
                      :disabled="role.users_count > 0 || isSaving"
                      @click="handleDeleteRole(role)"
                    >
                      <VIcon icon="tabler-trash" size="20" />
                    </VBtn>
                  </template>
                </VTooltip>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Diálogo Crear / Editar Rol -->
    <VDialog
      v-model="isRoleDialogVisible"
      max-width="500"
      persistent
    >
      <VCard class="rounded-lg">
        <VCardTitle class="pt-6 px-6 pb-2 d-flex justify-space-between align-center">
          <span class="text-h6 font-weight-bold">
            {{ isEditingRole ? 'Editar Rol' : 'Nuevo Rol' }}
          </span>
          <VBtn
            icon
            variant="text"
            density="compact"
            @click="isRoleDialogVisible = false"
          >
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </VCardTitle>

        <VCardText class="px-6 py-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Ingresa el nombre descriptivo para el rol de usuario.
          </p>

          <VTextField
            v-model="roleForm.name"
            label="Nombre del Rol *"
            placeholder="Ej: Farmacéutico, Cajero, Auditor..."
            autofocus
            :error-messages="roleFormErrors.name"
            @keyup.enter="handleSaveRole"
          />
        </VCardText>

        <VCardActions class="px-6 pb-6 pt-2 justify-end ga-2">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="isSaving"
            @click="isRoleDialogVisible = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSaving"
            :disabled="isSaving"
            @click="handleSaveRole"
          >
            {{ isEditingRole ? 'Guardar Cambios' : 'Crear Rol' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de Configuración de Visibilidad de Menú -->
    <VDialog
      v-model="isPermissionsDialogVisible"
      max-width="900"
      persistent
      scrollable
    >
      <VCard class="rounded-lg">
        <!-- Encabezado del Diálogo -->
        <VCardTitle class="pt-6 px-6 pb-2 d-flex justify-space-between align-center">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-layout-sidebar" color="primary" size="24" />
            <div>
              <span class="text-h6 font-weight-bold">
                Visibilidad de Menú: {{ selectedRole?.name }}
              </span>
              <div class="text-caption text-medium-emphasis">
                Configura los módulos y enlaces de la barra de navegación visibles para este rol.
              </div>
            </div>
          </div>

          <VBtn
            icon
            variant="text"
            density="compact"
            @click="isPermissionsDialogVisible = false"
          >
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="px-6 py-4" style="max-height: 70vh;">
          <!-- Advertencia especial si es Rol Administrador -->
          <VAlert
            v-if="isSelectedRoleAdmin"
            type="warning"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="mb-4"
          >
            <div class="text-body-2 font-weight-medium">
              Atención: Estás editando los permisos del rol <strong>Administrador</strong>.
            </div>
            <div class="text-caption">
              Los módulos que desmarques dejarán de ser visibles incluso para administradores. Por seguridad, la sección de <em>Configuración de Roles</em> siempre se mantendrá accesible.
            </div>
          </VAlert>

          <!-- Barra de Herramientas y Filtro -->
          <VRow class="mb-4 align-center">
            <VCol cols="12" sm="6">
              <VTextField
                v-model="searchQuery"
                density="compact"
                placeholder="Buscar módulo o ruta..."
                prepend-inner-icon="tabler-search"
                hide-details
                clearable
              />
            </VCol>

            <VCol cols="12" sm="6" class="d-flex justify-sm-end align-center ga-2 flex-wrap">
              <VChip
                color="primary"
                size="small"
                variant="tonal"
                class="font-weight-medium"
              >
                {{ selectedCount }} / {{ totalAvailableCount }} ítems visibles
              </VChip>

              <VBtn
                size="small"
                variant="outlined"
                color="primary"
                @click="selectAllPermissions"
              >
                Marcar Todos
              </VBtn>

              <VBtn
                size="small"
                variant="outlined"
                color="error"
                @click="deselectAllPermissions"
              >
                Desmarcar Todos
              </VBtn>
            </VCol>
          </VRow>

          <!-- Acordeón de Módulos del Sistema -->
          <VExpansionPanels
            v-model="expandedPanels"
            multiple
            variant="accordion"
          >
            <VExpansionPanel
              v-for="(moduleNode, index) in filteredMenuTree"
              :key="moduleNode.key"
              :value="index"
              class="border rounded mb-2"
            >
              <!-- Cabecera del Módulo -->
              <VExpansionPanelTitle class="py-2 px-4">
                <div class="d-flex align-center justify-space-between w-100 pe-2">
                  <div class="d-flex align-center ga-3" @click.stop>
                    <!-- Checkbox Maestro del Módulo -->
                    <VCheckboxBtn
                      :model-value="getModuleCheckState(moduleNode) === 'all'"
                      :indeterminate="getModuleCheckState(moduleNode) === 'some'"
                      color="primary"
                      density="compact"
                      @click.stop="toggleModule(moduleNode)"
                    />

                    <span class="font-weight-bold text-high-emphasis">
                      {{ moduleNode.title }}
                    </span>

                    <VChip
                      v-if="moduleNode.children?.length"
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                    >
                      {{ moduleNode.children.length }} sub-enlaces
                    </VChip>
                  </div>
                </div>
              </VExpansionPanelTitle>

              <!-- Submódulos / Pantallas -->
              <VExpansionPanelText class="pt-2 pb-4">
                <div v-if="moduleNode.children?.length" class="d-flex flex-column ga-2 ps-6">
                  <template v-for="child in moduleNode.children" :key="child.key">
                    <!-- Si el elemento tiene sus propios sub-hijos (p. ej. Optimización, Devoluciones) -->
                    <div v-if="child.children?.length" class="border rounded p-2 mb-2 bg-var-theme-background">
                      <div class="d-flex align-center justify-space-between py-1 px-2 rounded">
                        <div class="d-flex align-center ga-2">
                          <VCheckboxBtn
                            :model-value="getModuleCheckState(child) === 'all'"
                            :indeterminate="getModuleCheckState(child) === 'some'"
                            color="primary"
                            density="compact"
                            @click.stop="toggleModule(child)"
                          />
                          <span class="text-body-2 font-weight-bold text-high-emphasis">
                            {{ child.title }}
                          </span>
                        </div>
                        <span class="text-caption text-medium-emphasis">
                          {{ child.key }}
                        </span>
                      </div>
                      <!-- Hijos de segundo nivel -->
                      <div class="d-flex flex-column ga-1 ps-6 mt-1">
                        <div
                          v-for="subChild in child.children"
                          :key="subChild.key"
                          class="d-flex align-center justify-space-between py-1 px-3 rounded hover-bg"
                        >
                          <div class="d-flex align-center ga-2">
                            <VCheckboxBtn
                              :model-value="isPermissionSelected(subChild.key)"
                              color="primary"
                              density="compact"
                              @update:model-value="togglePermission(subChild.key)"
                            />
                            <span class="text-body-2 font-weight-medium">
                              {{ subChild.title }}
                            </span>
                          </div>
                          <span class="text-caption text-medium-emphasis">
                            {{ subChild.to || subChild.key }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <!-- Enlace directo regular -->
                    <div
                      v-else
                      class="d-flex align-center justify-space-between py-1 px-3 rounded hover-bg"
                    >
                      <div class="d-flex align-center ga-2">
                        <VCheckboxBtn
                          :model-value="isPermissionSelected(child.key)"
                          color="primary"
                          density="compact"
                          @update:model-value="togglePermission(child.key)"
                        />
                        <span class="text-body-2 font-weight-medium">
                          {{ child.title }}
                        </span>
                      </div>

                      <span class="text-caption text-medium-emphasis">
                        {{ child.to || child.key }}
                      </span>
                    </div>
                  </template>
                </div>

                <div v-else class="text-caption text-medium-emphasis ps-6 py-1">
                  Este módulo es un enlace directo sin submódulos.
                </div>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>

        <VDivider />

        <!-- Acciones del Diálogo -->
        <VCardActions class="px-6 py-4 justify-end ga-2">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="isSaving"
            @click="isPermissionsDialogVisible = false"
          >
            Cancelar
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="isSaving"
            :disabled="isSaving"
            @click="handleSaveMenuPermissions"
          >
            Guardar Permisos de Menú
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>

  <!-- Estado sin permisos de acceso -->
  <VCard v-else class="text-center pa-8 rounded-lg">
    <VIcon icon="tabler-lock" size="48" color="error" class="mb-4" />
    <h2 class="text-h6 font-weight-bold mb-2">Acceso Restringido</h2>
    <p class="text-body-2 text-medium-emphasis">
      No dispones de los permisos necesarios para gestionar los roles y menús del sistema.
    </p>
  </VCard>
</template>

<style scoped>
.table-row-hover:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.hover-bg:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
