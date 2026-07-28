<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const isLoading = ref(true)
const isSaving = ref(false)

const enableProductTypes  = ref(true)
const enabledProductTypes = ref([])
const enableFavorites     = ref(true)
const enableVariations    = ref(true)
const enableMerge         = ref(false)
const enableGroups        = ref(true)
const enableExpirations   = ref(true)
const enableBrandGroups   = ref(false)
const enableDonations     = ref(true)
const enableLocations     = ref(true)
const enableOptimization  = ref(true)
const enableDishes        = ref(true)
const traceabilityMode    = ref('units')
const productFormFields   = ref([])

const productTypeOptions = [
  { label: "Redundantes",       value: "redundantes" },
  { label: "Origen Colombiano", value: "col"         },
  { label: "Con IVA (G)",       value: "iva"         },
  { label: "Exento",            value: "exento"      },
  { label: "Novaventa",         value: "novaventa"   },
  { label: "Eliminados",        value: "eliminados"  },
  { label: "PVP",               value: "pvp"         },
  { label: "Ingredientes",      value: "ingredients" },
  { label: "Mixto",             value: "mixed"       },
]

const formFieldsOptions = [
  { label: "Código de Barras",           value: "barcode"            },
  { label: "Nombre del Producto",        value: "name"               },
  { label: "Descripción",                value: "description"        },
  { label: "Principio Activo",           value: "active_ingredient"  },
  { label: "Laboratorio / Marca",        value: "laboratory_id"      },
  { label: "Origen",                     value: "origin_id"          },
  { label: "Categoría",                  value: "category_id"        },
  { label: "Costo Unitario",             value: "unit_cost"          },
  { label: "Precio de Venta",            value: "sale_price"         },
  { label: "Inventario Inicial",         value: "stock"              },
  { label: "Impuesto IVA",               value: "iva"                },
  { label: "Origen Colombiano (COL)",    value: "is_colombian_origin"},
  { label: "Novaventa Flag",             value: "is_novaventa"       },
  { label: "Control Psicotrópico",       value: "psychotropic"       },
  { label: "No PVP (Sin precio venta)",  value: "no_pvp"             },
  { label: "Presentación",               value: "presentation"       },
  { label: "Unidad de Medida",           value: "unit_of_measure"    },
  { label: "Imagen / Foto",              value: "photo_url"          },
]

const toggleTraceabilityMode = (val) => {
  traceabilityMode.value = val ? 'consumption' : 'units'
  
  if (val) {
    if (!productFormFields.value.includes('presentation')) {
      productFormFields.value.push('presentation')
    }
    if (!productFormFields.value.includes('unit_of_measure')) {
      productFormFields.value.push('unit_of_measure')
    }
  }
  updateSettings()
}

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    enableProductTypes.value  = settings.enable_product_types  ?? true
    enabledProductTypes.value = settings.enabled_product_types ?? productTypeOptions.map(o => o.value)
    enableFavorites.value     = settings.enable_favorites      ?? true
    enableVariations.value    = settings.enable_variations     ?? true
    enableMerge.value         = settings.enable_merge          ?? false
    enableGroups.value        = settings.enable_groups         ?? true
    enableExpirations.value   = settings.enable_expirations    ?? true
    enableBrandGroups.value   = settings.enable_brand_groups   ?? false
    enableDonations.value     = settings.enable_donations      ?? true
    enableLocations.value     = settings.enable_locations      ?? true
    enableOptimization.value  = settings.enable_optimization   ?? true
    enableDishes.value        = settings.enable_dishes         ?? true
    traceabilityMode.value    = settings.traceability_mode     ?? 'units'
    productFormFields.value   = settings.product_form_fields   ?? formFieldsOptions.map(o => o.value)
  } catch (error) {
    console.error("Error cargando configuración de productos:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

let saveDebounceTimer = null
const updateSettings = () => {
  isSaving.value = true
  if (saveDebounceTimer) clearTimeout(saveDebounceTimer)

  saveDebounceTimer = setTimeout(async () => {
    try {
      await axios.post('/general-settings', {
        enable_product_types:  enableProductTypes.value,
        enabled_product_types: enabledProductTypes.value,
        enable_favorites:      enableFavorites.value,
        enable_variations:     enableVariations.value,
        enable_merge:          enableMerge.value,
        enable_groups:         enableGroups.value,
        enable_expirations:    enableExpirations.value,
        enable_brand_groups:   enableBrandGroups.value,
        enable_donations:      enableDonations.value,
        enable_locations:      enableLocations.value,
        enable_optimization:   enableOptimization.value,
        enable_dishes:         enableDishes.value,
        traceability_mode:     traceabilityMode.value,
        product_form_fields:   productFormFields.value,
      })
      await brandingStore.fetchSettings()
      toast.success("Configuración de productos actualizada exitosamente")
    } catch (error) {
      console.error("Error al guardar configuración de productos:", error)
      toast.error("Error al actualizar la configuración")
    } finally {
      isSaving.value = false
    }
  }, 300)
}

onMounted(() => fetchSettings())
</script>

<template>
  <div class="d-flex flex-column gap-6">

    <!-- Skeletons en estado de carga -->
    <template v-if="isLoading">
      <VCard class="rounded-lg border shadow-sm p-4">
        <VSkeletonLoader type="heading, subtitle, paragraph, paragraph" />
      </VCard>
      <VCard class="rounded-lg border shadow-sm p-4">
        <VSkeletonLoader type="heading, subtitle, paragraph" />
      </VCard>
    </template>

    <template v-else>
      <!-- ── CARD 1: Switches de características ── -->
      <VCard class="rounded-lg border shadow-sm hover-shadow-md transition-all duration-300">
        <VCardItem class="py-4">
          <VCardTitle class="text-h5 font-weight-medium d-flex align-center gap-2">
            <VIcon icon="tabler-settings" class="text-primary" />
            Configuración de Productos
            <VSpacer />
            <VProgressCircular
              v-if="isSaving"
              indeterminate
              size="20"
              width="2"
              color="primary"
              class="ms-2"
            />
          </VCardTitle>
        </VCardItem>

        <VDivider />

        <VCardItem class="py-5">
          <VRow class="match-height">
            <!-- Tipos de Productos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Tipos de Productos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Activa la clasificación por tipo (Redundantes, Exentos, Novaventa, etc.) en filtros y listas.
                  </p>
                </div>
                <VSwitch
                  v-model="enableProductTypes"
                  label="Habilitar Tipos"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Productos Favoritos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Productos Favoritos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Permite marcar productos como favoritos y destacarlos en el catálogo y tienda virtual.
                  </p>
                </div>
                <VSwitch
                  v-model="enableFavorites"
                  label="Habilitar Favoritos"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Variaciones de Productos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Variaciones de Productos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Habilita la pestaña de Variaciones al crear o editar productos (tallas, colores, presentaciones).
                  </p>
                </div>
                <VSwitch
                  v-model="enableVariations"
                  label="Habilitar Variaciones"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Fusión de Productos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Fusión de Productos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Permite fusionar productos duplicados en la lista de inventario.
                  </p>
                </div>
                <VSwitch
                  v-model="enableMerge"
                  label="Habilitar Fusión"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Grupos de Productos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Grupos de Productos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Habilita la agrupación de productos para promociones, combos o clasificaciones comerciales.
                  </p>
                </div>
                <VSwitch
                  v-model="enableGroups"
                  label="Habilitar Grupos"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Módulo de Caducidad -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Módulo de Caducidad</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Muestra u oculta la opción "Caducidad" en el menú de navegación lateral de inventario.
                  </p>
                </div>
                <VSwitch
                  v-model="enableExpirations"
                  label="Habilitar Caducidad"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Donaciones de Productos -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Donación de Productos</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Permite registrar actas y cartas de donación institucional para productos caducados.
                  </p>
                </div>
                <VSwitch
                  v-model="enableDonations"
                  label="Habilitar Donaciones"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Grupos de Marcas -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Grupos de Marcas</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Decide si las marcas se manejan de forma simple o agrupadas en corporaciones/grupos.
                  </p>
                </div>
                <VSwitch
                  v-model="enableBrandGroups"
                  label="Habilitar Marcas"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Ubicaciones de Inventario -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Ubicaciones</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Muestra u oculta la opción "Ubicaciones" en el menú lateral de inventario.
                  </p>
                </div>
                <VSwitch
                  v-model="enableLocations"
                  label="Habilitar Ubicaciones"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Menú de Optimización -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Optimización</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Muestra u oculta el submenú "Optimización" (productos sin grupo, incompletos, lotificación).
                  </p>
                </div>
                <VSwitch
                  v-model="enableOptimization"
                  label="Habilitar Optimización"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Habilitar Platos/Menú -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Platos / Menú</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Muestra u oculta la opción "Platos / Menú" del menú lateral del sistema.
                  </p>
                </div>
                <VSwitch
                  v-model="enableDishes"
                  label="Habilitar Platos"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Modo de Trazabilidad -->
            <VCol cols="12" sm="6" md="4" lg="3">
              <div class="d-flex flex-column justify-space-between h-100 pa-4 rounded-lg module-item-box transition-all">
                <div>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Trazabilidad</span>
                  <p class="text-caption text-medium-emphasis mt-1 mb-4">
                    Define si el seguimiento de stock se calcula por Unidades fijas o por Consumo de peso/volumen.
                  </p>
                </div>
                <VSwitch
                  :model-value="traceabilityMode === 'consumption'"
                  label="Trazabilidad Consumo"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="toggleTraceabilityMode"
                />
              </div>
            </VCol>
          </VRow>
        </VCardItem>

        <!-- Tipos habilitados (solo si está activo) -->
        <template v-if="enableProductTypes">
          <VDivider />
          <VCardItem class="py-6">
            <VCardTitle class="text-h6 font-weight-medium text-high-emphasis mb-2">
              Tipos de Productos Habilitados
            </VCardTitle>
            <div class="text-body-2 text-medium-emphasis mb-4">
              Selecciona cuáles tipos estarán disponibles en los filtros de la lista de productos.
            </div>
            <VRow>
              <VCol
                v-for="item in productTypeOptions"
                :key="item.value"
                cols="12" sm="6" md="4"
              >
                <VCheckbox
                  v-model="enabledProductTypes"
                  :value="item.value"
                  :label="item.label"
                  color="primary"
                  density="compact"
                  @update:model-value="updateSettings"
                />
              </VCol>
            </VRow>
          </VCardItem>
        </template>
      </VCard>

      <!-- ── CARD 2: Campos del formulario de productos ── -->
      <VCard class="rounded-lg border shadow-sm hover-shadow-md transition-all duration-300">
        <VCardItem class="py-4">
          <VCardTitle class="text-h6 font-weight-medium d-flex align-center gap-2">
            <VIcon icon="tabler-forms" class="text-primary" />
            Campos de Creación y Edición de Productos
            <VSpacer />
            <VProgressCircular
              v-if="isSaving"
              indeterminate
              size="20"
              width="2"
              color="primary"
              class="ms-2"
            />
          </VCardTitle>
          <div class="text-body-2 text-medium-emphasis mt-2">
            Selecciona cuáles campos del formulario quieres que estén visibles al crear o editar un producto.
          </div>
        </VCardItem>

        <VDivider />

        <VCardText class="py-6">
          <VRow>
            <VCol
              v-for="field in formFieldsOptions"
              :key="field.value"
              cols="12" sm="6" md="4"
            >
              <VCheckbox
                v-model="productFormFields"
                :value="field.value"
                :label="field.label"
                color="primary"
                density="compact"
                @update:model-value="updateSettings"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </template>

  </div>
</template>

<style scoped>
.module-item-box {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
.module-item-box:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>


