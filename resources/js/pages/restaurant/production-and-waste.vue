<template>
  <div class="restaurant-production-waste-view pb-12">
    <!-- Header / Título Principal -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <div class="d-flex align-center gap-2">
          <v-avatar color="primary" variant="tonal" rounded size="40">
            <v-icon size="24">tabler-chef-hat</v-icon>
          </v-avatar>
          <div>
            <h2 class="text-h6 font-weight-bold mb-0">Producción en Lote & Sub-Recetas</h2>
            <div class="text-caption text-medium-emphasis">Gestión de sub-recetas (salsas, masas, preparaciones base) y órdenes de producción</div>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <v-tabs v-model="activeTab" density="compact" color="primary">
            <v-tab value="sub_recipes">
              <v-icon start size="18">tabler-clipboard-list</v-icon>
              Sub-Recetas / Bases
            </v-tab>
            <v-tab value="production">
              <v-icon start size="18">tabler-packages</v-icon>
              Lotes Producidos
            </v-tab>
          </v-tabs>
        </div>
      </v-card-text>
    </v-card>

    <!-- PESTAÑA 1: SUB-RECETAS / PREPARACIONES BASE (MISMO DISEÑO Y FILTROS QUE DISHES) -->
    <div v-if="activeTab === 'sub_recipes'">
      <!-- Filtros Premium utilizando AppFilterBase idéntico a Dishes -->
      <AppFilterBase
        v-model:search="subRecipeSearch"
        search-placeholder="Buscar sub-receta o preparación base..."
        :show-add="isApprover"
        add-button-text="Nueva Sub-Receta"
        :show-advanced="true"
        :has-advanced-filters="selectedStatus !== null"
        @clear="subRecipeSearch = ''; selectedStatus = null; fetchSubRecipes()"
        @add="openSubRecipeModal"
      >
        <template #advanced-filters>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="selectedStatus"
              :items="statusOptions"
              placeholder="Filtrar por Estado"
              clearable
              density="compact"
              hide-details
            />
          </VCol>
        </template>
      </AppFilterBase>

      <!-- Tabla Principal de Sub-Recetas -->
      <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
        <!-- Loading State con Skeleton Loader en carga inicial -->
        <div v-if="loadingSubRecipes && subRecipes.length === 0" class="pa-6">
          <VSkeletonLoader
            type="table-row-divider@5"
            class="bg-transparent"
          />
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingSubRecipes && subRecipes.length === 0" class="pa-16 text-center text-disabled">
          <VIcon icon="tabler-clipboard-x" size="64" class="mb-4 opacity-20" />
          <h3 class="text-h6 font-weight-black opacity-50 uppercase">No hay sub-recetas registradas</h3>
          <p>Crea tu primera sub-receta o preparación base para utilizarla en tus platillos</p>
          <VBtn color="primary" prepend-icon="tabler-plus" class="mt-2" @click="openSubRecipeModal">
            Crear Sub-Receta
          </VBtn>
        </div>

        <!-- Desktop Table view -->
        <div v-else>
          <VDataTableServer
            :items-per-page="subRecipeItemsPerPage"
            :page="subRecipePage"
            :items="subRecipes"
            :items-length="totalSubRecipes"
            :loading="loadingSubRecipes"
            :sort-by="[{ key: subRecipeSortBy, order: subRecipeOrderBy }]"
            :must-sort="true"
            class="premium-table text-no-wrap"
            @update:options="updateSubRecipeTableOptions"
            :headers="[
              { title: 'ID', key: 'id', sortable: true },
              { title: 'Sub-Receta / Preparación', key: 'name', sortable: true },
              { title: 'Costo Total', key: 'cost_price', sortable: true, align: 'end' },
              { title: 'Rendimiento', key: 'expected_yield_quantity', sortable: true, align: 'center' },
              { title: 'Costo Unitario', key: 'unit_cost', sortable: false, align: 'end' },
              { title: 'Stock Insumo', key: 'output_product.stock', sortable: false, align: 'center' },
              { title: 'Vida Útil', key: 'shelf_life_days', sortable: false, align: 'center' },
              { title: 'Estado', key: 'status', sortable: true, align: 'center' },
              { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
            ]"
          >
            <template #item.id="{ item }">
              <span class="font-weight-black text-primary">{{ item.id }}</span>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="font-weight-bold text-high-emphasis uppercase text-xs">{{ item.name }}</span>
                <span v-if="item.output_product?.name" class="text-super-xs text-disabled">
                  Insumo: {{ item.output_product.name }}
                </span>
              </div>
            </template>

            <template #item.cost_price="{ item }">
              <span class="text-xs font-weight-bold text-high-emphasis">{{ formatCurrency(item.cost_price) }}</span>
            </template>

            <template #item.expected_yield_quantity="{ item }">
              <span class="text-xs font-weight-black text-secondary">
                {{ item.expected_yield_quantity || 1 }} {{ item.output_product?.unit_of_measure || 'porción' }}
              </span>
            </template>

            <template #item.unit_cost="{ item }">
              <span class="text-xs font-weight-black text-success">
                {{ formatCurrency((Number(item.expected_yield_quantity) || 1) > 0 ? ((Number(item.cost_price) || 0) / (Number(item.expected_yield_quantity) || 1)) : item.cost_price) }}
              </span>
            </template>

            <template #item.output_product.stock="{ item }">
              <VChip
                size="x-small"
                :color="(item.output_product?.stock || 0) > 0 ? 'success' : 'warning'"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ item.output_product?.stock || 0 }} {{ item.output_product?.unit_of_measure || 'und' }}
              </VChip>
            </template>

            <template #item.shelf_life_days="{ item }">
              <span class="text-xs text-medium-emphasis">{{ item.shelf_life_days || 7 }} días</span>
            </template>

            <template #item.status="{ item }">
              <VChip :color="getStatusChipColor(item.status)" size="x-small" label class="font-weight-black uppercase">
                {{ getStatusLabel(item.status) }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-2 justify-end">
                <IconBtn
                  v-if="isApprover"
                  color="info"
                  class="bg-info-light"
                  title="Editar Sub-Receta y Rendimiento"
                  @click="openEditSubRecipe(item)"
                >
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent" location="top">Editar Sub-Receta</VTooltip>
                </IconBtn>
                <IconBtn
                  color="primary"
                  class="bg-primary-light"
                  title="Producir Lote de esta Sub-Receta"
                  @click="openProduceFromSubRecipe(item)"
                >
                  <VIcon icon="tabler-packages" size="18" />
                  <VTooltip activator="parent" location="top">Producir Lote</VTooltip>
                </IconBtn>
                <IconBtn
                  v-if="isApprover"
                  color="error"
                  class="bg-error-light"
                  title="Eliminar Sub-Receta"
                  @click="handleDeleteSubRecipe(item)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent" location="top">Eliminar</VTooltip>
                </IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </div>
      </VCard>
    </div>

    <!-- PESTAÑA 2: ÓRDENES DE PRODUCCIÓN / LOTES PRODUCIDOS -->
    <div v-else-if="activeTab === 'production'">
      <AppFilterBase
        v-model:search="prodSearch"
        search-placeholder="Buscar por código, sub-receta o número de lote..."
        :show-add="true"
        add-button-text="Nueva Orden de Producción"
        :show-advanced="true"
        :has-advanced-filters="prodFilterDishId !== null || prodFilterStatus !== null"
        @clear="prodSearch = ''; prodFilterDishId = null; prodFilterStatus = null; fetchProductionOrders()"
        @add="openProductionModal"
      >
        <template #advanced-filters>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="prodFilterDishId"
              :items="subRecipeDishes"
              item-title="name"
              item-value="id"
              placeholder="Filtrar por Sub-Receta"
              clearable
              density="compact"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="prodFilterStatus"
              :items="prodStatusOptions"
              placeholder="Filtrar por Estado"
              clearable
              density="compact"
              hide-details
            />
          </VCol>
        </template>
      </AppFilterBase>

      <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
        <div v-if="loadingProdOrders && productionOrders.length === 0" class="pa-6">
          <VSkeletonLoader
            type="table-row-divider@5"
            class="bg-transparent"
          />
        </div>

        <div v-else-if="!loadingProdOrders && productionOrders.length === 0" class="pa-16 text-center text-disabled">
          <VIcon icon="tabler-packages" size="64" class="mb-4 opacity-20" />
          <h3 class="text-h6 font-weight-black opacity-50 uppercase">No hay órdenes de producción registradas</h3>
          <p>Produce un lote de tus sub-recetas para ingresar inventario elaborado con lote y caducidad</p>
          <VBtn color="primary" prepend-icon="tabler-plus" class="mt-2" @click="openProductionModal">
            Nueva Orden de Producción
          </VBtn>
        </div>

        <div v-else>
          <VDataTableServer
            :items-per-page="prodItemsPerPage"
            :page="prodPage"
            :items="productionOrders"
            :items-length="totalProdOrders"
            :loading="loadingProdOrders"
            :sort-by="[{ key: prodSortBy, order: prodOrderBy }]"
            :must-sort="true"
            class="premium-table text-no-wrap"
            @update:options="updateProdTableOptions"
            :headers="[
              { title: 'Código', key: 'code', sortable: true },
              { title: 'Sub-Receta / Insumo', key: 'dish', sortable: false },
              { title: 'Lote Mult.', key: 'batch_multiplier', sortable: true, align: 'center' },
              { title: 'Esperado vs. Real', key: 'actual_quantity', sortable: true, align: 'center' },
              { title: 'Costo Unit.', key: 'unit_cost_calculated', sortable: false, align: 'end' },
              { title: 'Costo Total', key: 'total_production_cost', sortable: true, align: 'end' },
              { title: 'Lote / Caducidad', key: 'lot_number', sortable: false, align: 'center' },
              { title: 'Estado', key: 'status', sortable: true, align: 'center' },
              { title: 'Fecha', key: 'produced_at', sortable: true },
              { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
            ]"
          >
            <template #item.code="{ item }">
              <span class="font-weight-black text-primary text-xs">{{ item.code }}</span>
            </template>

            <template #item.dish="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="font-weight-bold text-high-emphasis uppercase text-xs">{{ item.dish?.name }}</span>
                <span class="text-super-xs text-disabled">
                  Insumo: {{ item.output_product?.name || 'Insumo Base' }}
                </span>
              </div>
            </template>

            <template #item.batch_multiplier="{ item }">
              <span class="text-xs font-weight-black text-secondary">{{ item.batch_multiplier }}x</span>
            </template>

            <template #item.actual_quantity="{ item }">
              <div class="d-flex flex-column align-center py-1">
                <div>
                  <span class="text-caption text-medium-emphasis">{{ item.expected_quantity }} → </span>
                  <span class="font-weight-black text-success text-xs">
                    {{ item.actual_quantity }} {{ item.output_product?.unit_of_measure || 'und' }}
                  </span>
                </div>
                <VChip
                  size="x-small"
                  :color="(item.yield_efficiency_pct || 100) >= 95 ? 'success' : (item.yield_efficiency_pct || 100) >= 80 ? 'warning' : 'error'"
                  variant="tonal"
                  class="font-weight-bold mt-1"
                >
                  {{ item.yield_efficiency_pct || 100 }}% rend.
                </VChip>
              </div>
            </template>

            <template #item.unit_cost_calculated="{ item }">
              <span class="text-xs font-weight-bold">{{ formatCurrency(item.unit_cost_calculated) }}</span>
            </template>

            <template #item.total_production_cost="{ item }">
              <span class="font-weight-bold text-primary text-xs">{{ formatCurrency(item.total_production_cost) }}</span>
            </template>

            <template #item.lot_number="{ item }">
              <div class="d-flex flex-column align-center py-1">
                <v-chip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ item.lot_number || 'Sin Lote' }}
                </v-chip>
                <span v-if="item.expiration_date" class="text-super-xs text-medium-emphasis mt-1">
                  Vence: {{ item.expiration_date }}
                </span>
              </div>
            </template>

            <template #item.status="{ item }">
              <v-chip
                size="x-small"
                :color="item.status_color || (item.status === 'cancelled' ? 'error' : 'success')"
                variant="tonal"
                class="font-weight-bold uppercase"
              >
                {{ item.status_label || item.status }}
              </v-chip>
            </template>

            <template #item.produced_at="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-xs text-high-emphasis">{{ item.produced_at }}</span>
                <span v-if="item.user?.name" class="text-super-xs text-disabled">Por: {{ item.user.name }}</span>
              </div>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-1 justify-end">
                <IconBtn
                  color="primary"
                  class="bg-primary-light"
                  title="Imprimir Etiqueta de Lote y Caducidad"
                  @click="printPrepLotLabel(item)"
                >
                  <VIcon icon="tabler-printer" size="18" />
                  <VTooltip activator="parent" location="top">Imprimir Etiqueta PEPS</VTooltip>
                </IconBtn>
                <IconBtn
                  v-if="item.status !== 'cancelled' && isApprover"
                  color="error"
                  class="bg-error-light"
                  title="Anular Orden y Revertir Insumos"
                  :loading="cancellingOrderId === item.id"
                  @click="handleCancelProductionOrder(item)"
                >
                  <VIcon icon="tabler-ban" size="18" />
                  <VTooltip activator="parent" location="top">Anular y Revertir Stock</VTooltip>
                </IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </div>
      </VCard>
    </div>

    <!-- DIÁLOGO: CREAR SUB-RECETA / PREPARACIÓN BASE -->
    <v-dialog v-model="showSubRecipeModal" max-width="850">
      <v-card class="rounded-lg overflow-hidden">
        <!-- Cabecera Estandarizada del Sistema con Gradiente Institucional -->
        <v-card-title class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm text-white">
            <v-avatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <v-icon icon="tabler-clipboard-list" color="primary" size="22" />
            </v-avatar>
            <div class="flex-grow-1 text-white">
              <h2 class="text-h6 font-weight-black mb-0 text-uppercase text-white" style="color: #ffffff !important;">
                {{ editingSubRecipeId ? 'Editar Sub-Receta / Preparación Base' : 'Crear Sub-Receta / Preparación Base' }}
              </h2>
              <span class="text-super-xs opacity-75 uppercase font-weight-bold text-white" style="color: #ffffff !important;">
                Configuración de ingredientes y rendimiento de producción
              </span>
            </div>
            <v-spacer />
            <v-btn icon variant="tonal" color="white" size="small" class="rounded-lg ms-3" @click="showSubRecipeModal = false">
              <v-icon>tabler-x</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-4 bg-light" style="max-height: 78vh; overflow-y: auto;">
          <v-form @submit.prevent="submitSubRecipe">
            <!-- Datos Generales -->
            <v-card variant="flat" class="pa-4 bg-white border rounded-lg mb-4">
              <h3 class="text-subtitle-2 font-weight-bold text-uppercase mb-3">Información de la Preparación</h3>
              <v-row dense>
                <v-col cols="12">
                  <v-text-field
                    v-model="subRecipeForm.name"
                    label="Nombre de la Receta / Preparación"
                    placeholder="Ej: Salsa BBQ Casera, Masa de Waffles, Carne Molida Preparada"
                    density="compact"
                    variant="outlined"
                    required
                  />
                </v-col>

                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model.number="subRecipeForm.expected_yield_quantity"
                    type="number"
                    step="0.01"
                    min="0.01"
                    label="Rendimiento / Unidades que salen"
                    placeholder="Ej: 10"
                    density="compact"
                    variant="outlined"
                    required
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="subRecipeForm.unit_of_measure"
                    :items="['porción', 'porciones', 'lt', 'ml', 'kg', 'gr', 'und', 'pza']"
                    label="Unidad de Medida del Insumo"
                    density="compact"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model.number="subRecipeForm.shelf_life_days"
                    type="number"
                    min="1"
                    label="Días de Vida Útil (Caducidad)"
                    density="compact"
                    variant="outlined"
                  />
                </v-col>
              </v-row>
            </v-card>

            <!-- Sección de Insumos -->
            <v-card variant="flat" class="pa-4 bg-white border rounded-lg mb-4">
              <div class="d-flex align-center justify-space-between mb-3">
                <h3 class="text-subtitle-2 font-weight-bold text-uppercase mb-0">Insumos que Componen esta Receta</h3>
              </div>

              <!-- Fila para agregar ingrediente -->
              <v-row dense class="align-center mb-2">
                <v-col cols="12" sm="6">
                  <v-autocomplete
                    v-model="selectedIngredient"
                    v-model:search="ingredientSearch"
                    :items="ingredientOptions"
                    item-title="name"
                    return-object
                    :loading="loadingIngredients"
                    placeholder="Buscar Insumo / Ingrediente"
                    density="compact"
                    variant="outlined"
                    clearable
                    hide-details
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props" :title="item.raw.name">
                        <template #subtitle>
                          <span>Costo: {{ formatCurrency(item.raw.unit_cost) }} / {{ item.raw.presentation }} {{ item.raw.unit_of_measure }}</span>
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>
                <v-col cols="9" sm="5">
                  <v-text-field
                    v-model.number="ingredientPortion"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    label="Cantidad / Porción"
                    :suffix="selectedIngredient ? selectedIngredient.unit_of_measure : 'und'"
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                </v-col>
                <v-col cols="3" sm="1" class="d-flex align-center justify-center">
                  <v-btn
                    color="primary"
                    variant="flat"
                    icon="tabler-plus"
                    height="40"
                    width="40"
                    class="rounded-lg"
                    :disabled="!selectedIngredient || !ingredientPortion"
                    @click="addIngredientToSubRecipe"
                  />
                </v-col>
              </v-row>

              <!-- Tabla de Insumos Agregados -->
              <v-table density="compact" class="border rounded-lg mt-3">
                <thead>
                  <tr>
                    <th>Insumo</th>
                    <th class="text-center">Porción Requerida</th>
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-center">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in subRecipeForm.ingredients" :key="item.product_id">
                    <td class="font-weight-medium">{{ item.name }}</td>
                    <td class="text-center">{{ item.portion }} {{ item.unit_of_measure }}</td>
                    <td class="text-right">{{ formatCurrency(item.unit_cost) }}</td>
                    <td class="text-right font-weight-bold text-error">
                      {{ formatCurrency(item.designated_cost) }}
                    </td>
                    <td class="text-center">
                      <v-btn
                        icon
                        size="x-small"
                        color="error"
                        variant="text"
                        @click="subRecipeForm.ingredients.splice(index, 1)"
                      >
                        <v-icon size="16">tabler-trash</v-icon>
                      </v-btn>
                    </td>
                  </tr>
                  <tr v-if="!subRecipeForm.ingredients.length">
                    <td colspan="5" class="text-center py-4 text-medium-emphasis text-caption">
                      No has agregado insumos a esta sub-receta aún.
                    </td>
                  </tr>
                </tbody>
              </v-table>

              <!-- Resumen de Costo -->
              <div class="d-flex justify-space-between align-center mt-3 pt-3 border-t">
                <span class="text-caption font-weight-bold">
                  COSTO TOTAL: <strong class="text-error">{{ formatCurrency(subRecipeTotalCost) }}</strong>
                </span>
                <span class="text-caption font-weight-bold">
                  COSTO UNITARIO:
                  <strong class="text-success">{{ formatCurrency(subRecipeUnitCost) }}</strong>
                </span>
              </div>
            </v-card>

            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              class="font-weight-bold"
              :loading="submittingSubRecipe"
              :disabled="!subRecipeForm.name || !subRecipeForm.ingredients.length"
            >
              {{ editingSubRecipeId ? 'Actualizar Sub-Receta y Recalcular Costos' : 'Guardar Sub-Receta Y Habilitar En Platos' }}
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: CREAR ORDEN DE PRODUCCIÓN -->
    <v-dialog
      v-model="showProductionModal"
      max-width="720"
      :fullscreen="$vuetify.display.xs"
      scrollable
    >
      <v-card class="rounded-lg overflow-hidden">
        <v-card-title class="pa-0">
          <div class="bg-primary pa-4 d-flex align-center shadow-sm text-white">
            <v-avatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <v-icon icon="tabler-packages" color="primary" size="22" />
            </v-avatar>
            <div class="flex-grow-1 text-white">
              <h2 class="text-subtitle-1 font-weight-black mb-0 text-uppercase text-white" style="color: #ffffff !important;">
                Nueva Orden de Producción
              </h2>
              <span class="text-super-xs opacity-75 uppercase font-weight-bold text-white" style="color: #ffffff !important;">
                Explosión de insumos a costo promediado de inventario y generación de lote FEFO
              </span>
            </div>
            <v-spacer />
            <v-btn icon variant="tonal" color="white" size="small" class="rounded-lg" @click="showProductionModal = false">
              <v-icon>tabler-x</v-icon>
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text class="pa-4 flex-grow-1 overflow-y-auto">
          <v-form id="prodOrderForm" @submit.prevent="submitProduction">
            <v-select
              v-model="productionForm.dish_id"
              :items="subRecipeDishes"
              item-title="name"
              item-value="id"
              label="Seleccionar Sub-Receta / Preparación Base"
              density="compact"
              variant="outlined"
              no-data-text="No hay sub-recetas base disponibles"
              class="mb-3"
              required
            />
            <v-row dense>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="productionForm.batch_multiplier"
                  type="number"
                  step="0.1"
                  min="0.1"
                  label="Multiplicador de Lote"
                  suffix="x"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  :model-value="selectedProductionExpectedQty"
                  label="Rendimiento Esperado"
                  :suffix="selectedProductionDish?.unit_of_measure || 'und'"
                  density="compact"
                  variant="outlined"
                  readonly
                  disabled
                  class="mb-3"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="productionForm.actual_quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Rendimiento Real Obtenido"
                  :suffix="selectedProductionDish?.unit_of_measure || 'und'"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <!-- Explosión de Materiales (MRP) de la Orden de Producción -->
            <div v-if="productionMrpLines.length" class="mb-3">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-black uppercase text-medium-emphasis">
                  Insumos Requeridos vs. Stock Actual
                </span>
                <v-chip
                  size="x-small"
                  :color="hasInsufficientProductionStock ? 'warning' : 'success'"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ hasInsufficientProductionStock ? 'Stock parcial en almacén' : 'Stock completo disponible' }}
                </v-chip>
              </div>

              <v-table density="compact" class="border rounded-lg text-no-wrap">
                <thead>
                  <tr>
                    <th>Insumo</th>
                    <th class="text-center">Requerido</th>
                    <th class="text-center">Disponible</th>
                    <th class="text-right">Costo Unit. (Prod)</th>
                    <th class="text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in productionMrpLines" :key="line.id">
                    <td class="text-xs font-weight-medium">{{ line.name }}</td>
                    <td class="text-center text-xs font-weight-bold">
                      {{ line.packsRequired }} {{ line.unit_of_measure }}
                    </td>
                    <td class="text-center">
                      <v-chip
                        size="x-small"
                        :color="line.hasStock ? 'success' : 'error'"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ line.stock }} {{ line.unit_of_measure }}
                      </v-chip>
                    </td>
                    <td class="text-right text-xs">{{ formatCurrency(line.unit_cost) }}</td>
                    <td class="text-right text-xs font-weight-bold text-primary">{{ formatCurrency(line.lineCost) }}</td>
                  </tr>
                </tbody>
              </v-table>

              <div class="d-flex flex-wrap justify-space-between align-center mt-2 pa-2 rounded bg-light border text-caption">
                <div>
                  <strong>Caducidad estimada:</strong> {{ selectedProductionDish?.shelf_life_days || 7 }} días
                </div>
                <div>
                  <strong>Costo Lote (+5%):</strong>
                  <span class="text-primary font-weight-black ms-1">{{ formatCurrency(estimatedBatchTotalCost) }}</span>
                  <span class="mx-2">|</span>
                  <strong>Costo Unit. Real:</strong>
                  <span class="text-success font-weight-black ms-1">{{ formatCurrency(estimatedBatchUnitCost) }}</span>
                </div>
              </div>
            </div>

            <v-textarea
              v-model="productionForm.notes"
              label="Notas u observaciones de cocción"
              rows="2"
              density="compact"
              variant="outlined"
              hide-details
            />
          </v-form>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3 bg-surface flex-shrink-0 d-flex justify-end gap-2">
          <v-btn variant="outlined" color="secondary" @click="showProductionModal = false">
            Cancelar
          </v-btn>
          <v-btn
            type="submit"
            form="prodOrderForm"
            color="primary"
            variant="flat"
            :loading="submitting"
          >
            Producir y Descontar Insumos
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { formatCurrency as formatRawCurrency } from '@/utils/currencyFormatter'
import AppFilterBase from '@/components/AppFilterBase.vue'

const authStore = useAuthStore()
const brandingStore = useBrandingStore()
const isApprover = computed(() => Boolean(authStore.isAdmin || authStore.isSupervisor))

const defaultCurrency = computed(() => brandingStore.settings?.default_currency || 'USD')

const activeExchangeRate = computed(() => {
  const currency = defaultCurrency.value
  if (currency === 'USD') return 1

  const rates = Array.isArray(brandingStore.exchangeRates) ? brandingStore.exchangeRates : []

  if (currency === 'COP') {
    const copObj = rates.find((r) => r && (r.currency_code === 'COP' || r.currency_code === 'COPC'))
    return Number(copObj?.rate || 4000)
  }

  if (currency === 'BS' || currency === 'Bs') {
    const bsObj = rates.find((r) => r && r.currency_code === 'BS')
    return Number(bsObj?.rate || 1)
  }

  return 1
})

const formatCurrency = (price) => {
  const numPrice = Number(price)
  if (isNaN(numPrice)) return '—'

  const currency = defaultCurrency.value
  if (currency === 'USD') {
    return formatRawCurrency(numPrice, 'USD')
  }

  const rate = activeExchangeRate.value

  if (currency === 'COP') {
    const rawCop = numPrice * rate
    if (rawCop > 0 && rawCop < 100) {
      return formatRawCurrency(Math.round(rawCop), 'COP')
    }
    const cleanInt = Math.round(rawCop)
    const rounded = cleanInt % 100 === 0 ? cleanInt : Math.ceil(cleanInt / 100) * 100
    return formatRawCurrency(rounded, 'COP')
  }

  if (currency === 'BS' || currency === 'Bs') {
    return formatRawCurrency(numPrice * rate, 'BS')
  }

  return formatRawCurrency(numPrice, currency)
}

const cleanIngredientName = (name) => {
  if (!name) return ''
  return String(name).replace(/\s*\[.*?\]\s*/g, ' ').trim()
}

// Pestaña activa (por defecto en Sub-recetas para que el usuario vea de inmediato las sub-recetas creadas)
const activeTab = ref('sub_recipes')

// Estado para Sub-Recetas (Mismo diseño y reactividad que /inventory/dishes)
const subRecipes = ref([])
const totalSubRecipes = ref(0)
const loadingSubRecipes = ref(false)
const subRecipePage = ref(1)
const subRecipeItemsPerPage = ref(10)
const subRecipeSortBy = ref('id')
const subRecipeOrderBy = ref('desc')
const subRecipeSearch = ref('')
const selectedCategory = ref(null)
const selectedStatus = ref(null)

const statusOptions = [
  { title: 'Base (Sub-receta)', value: 3 },
  { title: 'Activo', value: 1 },
  { title: 'Inactivo', value: 0 },
]

const getStatusChipColor = (status) => {
  if (status === 1) return 'success'
  if (status === 2) return 'info'
  if (status === 3) return 'warning'
  return 'error'
}

const getStatusLabel = (status) => {
  if (status === 1) return 'Activo'
  if (status === 2) return 'En Revisión'
  if (status === 3) return 'Base'
  return 'Inactivo'
}

// Consultar listado de sub-recetas
const fetchSubRecipes = async () => {
  loadingSubRecipes.value = true
  try {
    const params = {
      q: subRecipeSearch.value,
      status: selectedStatus.value !== null ? selectedStatus.value : 3, // Por defecto filtrar las sub-recetas (status 3)
      page: subRecipePage.value,
      itemsPerPage: subRecipeItemsPerPage.value,
      sortBy: subRecipeSortBy.value,
      orderBy: subRecipeOrderBy.value,
    }
    const response = await axios.get('/dishes', { params })
    subRecipes.value = response.data.data || []
    totalSubRecipes.value = response.data.meta?.total || subRecipes.value.length
  } catch (error) {
    console.error('Error al cargar sub-recetas:', error)
    toast.error('No se pudieron cargar las sub-recetas.')
  } finally {
    loadingSubRecipes.value = false
  }
}

const updateSubRecipeTableOptions = (options) => {
  subRecipePage.value = options.page
  subRecipeItemsPerPage.value = options.itemsPerPage
  if (options.sortBy && options.sortBy.length > 0) {
    subRecipeSortBy.value = options.sortBy[0].key
    subRecipeOrderBy.value = options.sortBy[0].order
  }
}

// Watchers con debounce para recarga reactiva de sub-recetas
let subRecipeDebounceTimer
watch([subRecipeSearch, selectedStatus], () => {
  subRecipePage.value = 1
})

watch(
  [subRecipePage, subRecipeItemsPerPage, subRecipeSortBy, subRecipeOrderBy, subRecipeSearch, selectedStatus],
  () => {
    clearTimeout(subRecipeDebounceTimer)
    subRecipeDebounceTimer = setTimeout(() => fetchSubRecipes(), 300)
  }
)

// Eliminar Sub-receta
const handleDeleteSubRecipe = async (dish) => {
  const result = await Swal.fire({
    title: `¿Eliminar sub-receta "${dish.name}"?`,
    text: 'Esta acción desactivará la preparación base del recetario.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/dishes/${dish.id}`)
      toast.success('Sub-receta eliminada con éxito.')
      fetchSubRecipes()
      loadData()
    } catch (error) {
      console.error('Error al eliminar sub-receta:', error)
      toast.error(error.response?.data?.message || 'No se pudo eliminar la sub-receta.')
    }
  }
}

// Abrir modal de producción directamente para una sub-receta
const openProduceFromSubRecipe = async (dish) => {
  await loadSubRecipeDishes()
  productionForm.dish_id = dish.id
  productionForm.batch_multiplier = 1.0
  productionForm.actual_quantity = Number(dish.expected_yield_quantity) || 1.0
  productionForm.notes = ''
  showProductionModal.value = true
}

// Estado para Lotes de Producción (Server-Side)
const productionOrders = ref([])
const totalProdOrders = ref(0)
const loadingProdOrders = ref(false)
const prodPage = ref(1)
const prodItemsPerPage = ref(10)
const prodSortBy = ref('id')
const prodOrderBy = ref('desc')
const prodSearch = ref('')
const prodFilterDishId = ref(null)
const prodFilterStatus = ref(null)
const cancellingOrderId = ref(null)

const prodStatusOptions = [
  { title: 'Completada', value: 'completed' },
  { title: 'Cancelada', value: 'cancelled' },
]

const subRecipeDishes = ref([])
const categories = ref([])
const submitting = ref(false)

const showProductionModal = ref(false)
const showSubRecipeModal = ref(false)
const submittingSubRecipe = ref(false)
const editingSubRecipeId = ref(null)

// Formulario de creación de sub-receta
const subRecipeForm = reactive({
  name: '',
  category_id: null,
  kitchen_station_id: null,
  expected_yield_quantity: 1.0,
  unit_of_measure: 'porción',
  shelf_life_days: 7,
  ingredients: [],
})

// Insumos para la sub-receta
const ingredientSearch = ref('')
const ingredientOptions = ref([])
const loadingIngredients = ref(false)
const selectedIngredient = ref(null)
const ingredientPortion = ref(1)

const subRecipeTotalCost = computed(() => {
  const raw = subRecipeForm.ingredients.reduce((acc, ing) => acc + (Number(ing.designated_cost) || 0), 0)
  return parseFloat((raw * 1.05).toFixed(2)) // Sumar 5% por Imprevisto
})

const subRecipeUnitCost = computed(() => {
  const yieldQty = Number(subRecipeForm.expected_yield_quantity) || 1
  return yieldQty > 0 ? subRecipeTotalCost.value / yieldQty : 0
})

const searchIngredients = async (query = '') => {
  loadingIngredients.value = true
  try {
    const { data } = await axios.get('/products/autocomplete', {
      params: { q: query },
    })
    const rawItems = (data.data || []).filter(item => !item.is_base_dish).map(item => ({
      ...item,
      name: cleanIngredientName(item.name),
      is_sub_recipe: Boolean(item.is_sub_recipe),
    }))

    const currentSubRecipeName = (subRecipeForm.name || '').toLowerCase().trim().replace(/\s+/g, ' ')
    const uniqueMap = new Map()
    rawItems.forEach(item => {
      const key = item.name.toLowerCase().trim().replace(/\s+/g, ' ')
      // Evitar que una sub-receta se seleccione a sí misma como ingrediente si existe el insumo base con el mismo nombre
      if (item.is_sub_recipe && currentSubRecipeName && key === currentSubRecipeName) {
        return
      }
      if (!uniqueMap.has(key)) {
        uniqueMap.set(key, item)
      } else {
        const current = uniqueMap.get(key)
        // Dentro de la receta de una sub-receta, priorizar el insumo base de inventario sobre una sub-receta homónima
        if (current.is_sub_recipe && !item.is_sub_recipe) {
          uniqueMap.set(key, item)
        }
      }
    })

    ingredientOptions.value = Array.from(uniqueMap.values())
  } catch (error) {
    console.error('Error al buscar ingredientes:', error)
  } finally {
    loadingIngredients.value = false
  }
}

let ingredientDebounceTimer = null
watch(ingredientSearch, (val) => {
  clearTimeout(ingredientDebounceTimer)
  ingredientDebounceTimer = setTimeout(() => {
    searchIngredients(val || '')
  }, 300)
})

const addIngredientToSubRecipe = () => {
  if (!selectedIngredient.value || !ingredientPortion.value) return

  const portion = parseFloat(ingredientPortion.value)
  const unitCost = parseFloat(selectedIngredient.value.unit_cost || 0)
  const presentation = parseFloat(selectedIngredient.value.presentation || 1)
  const cost = parseFloat(((portion * unitCost) / presentation).toFixed(4))

  const existing = subRecipeForm.ingredients.find(i => i.product_id === selectedIngredient.value.id)
  if (existing) {
    existing.portion = portion
    existing.designated_cost = cost
  } else {
    subRecipeForm.ingredients.push({
      product_id: selectedIngredient.value.id,
      name: cleanIngredientName(selectedIngredient.value.name),
      portion: portion,
      unit_cost: unitCost,
      presentation: presentation,
      unit_of_measure: selectedIngredient.value.unit_of_measure || 'und',
      designated_cost: cost,
    })
  }

  selectedIngredient.value = null
  ingredientPortion.value = 1
  ingredientSearch.value = ''
  ingredientOptions.value = []
}

const openSubRecipeModal = () => {
  editingSubRecipeId.value = null
  subRecipeForm.name = ''
  subRecipeForm.category_id = null
  subRecipeForm.kitchen_station_id = null
  subRecipeForm.expected_yield_quantity = 1.0
  subRecipeForm.unit_of_measure = 'porción'
  subRecipeForm.shelf_life_days = 7
  subRecipeForm.ingredients = []
  selectedIngredient.value = null
  ingredientPortion.value = 1
  searchIngredients('')
  showSubRecipeModal.value = true
}

const openEditSubRecipe = (item) => {
  editingSubRecipeId.value = item.id
  subRecipeForm.name = item.name || ''
  subRecipeForm.category_id = item.category_id ?? item.category?.id ?? null
  subRecipeForm.kitchen_station_id = item.kitchen_station_id ?? item.kitchen_station?.id ?? null
  subRecipeForm.expected_yield_quantity = Number(item.expected_yield_quantity) || 1.0
  subRecipeForm.unit_of_measure = item.output_product?.unit_of_measure || 'porción'
  subRecipeForm.shelf_life_days = Number(item.shelf_life_days) || 7
  subRecipeForm.ingredients = (item.ingredients || []).map(ing => {
    const portion = parseFloat(ing.portion ?? ing.pivot?.portion ?? 1)
    const unitCost = parseFloat(ing.unit_cost || 0)
    const presentation = parseFloat(ing.presentation || 1)
    const designatedCost = unitCost > 0
      ? parseFloat(((portion * unitCost) / (presentation > 0 ? presentation : 1)).toFixed(4))
      : parseFloat(ing.designated_cost ?? ing.pivot?.designated_cost ?? 0)
    return {
      product_id: ing.id || ing.product_id,
      name: cleanIngredientName(ing.name),
      portion,
      unit_cost: unitCost,
      presentation,
      unit_of_measure: ing.unit_of_measure || 'und',
      designated_cost: designatedCost,
    }
  })
  selectedIngredient.value = null
  ingredientPortion.value = 1
  searchIngredients('')
  showSubRecipeModal.value = true
}

const submitSubRecipe = async () => {
  if (!subRecipeForm.ingredients.length) {
    toast.warning('Debes agregar al menos un insumo a la sub-receta.')
    return
  }

  submittingSubRecipe.value = true
  try {
    if (editingSubRecipeId.value) {
      const { data } = await axios.put(`/restaurant/production/sub-recipes/${editingSubRecipeId.value}`, subRecipeForm)
      toast.success(data.message || 'Sub-receta actualizada con éxito.')
    } else {
      const { data } = await axios.post('/restaurant/production/sub-recipes', subRecipeForm)
      toast.success(data.message || 'Sub-receta creada con éxito.')
    }
    showSubRecipeModal.value = false
    editingSubRecipeId.value = null
    await fetchSubRecipes()
    await loadData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar la sub-receta.')
  } finally {
    submittingSubRecipe.value = false
  }
}

// Formulario de producción
const productionForm = reactive({
  dish_id: null,
  batch_multiplier: 1.0,
  actual_quantity: null,
  notes: '',
})

const selectedProductionDish = computed(() =>
  subRecipeDishes.value.find(d => d.id === productionForm.dish_id) || null
)

const selectedProductionExpectedQty = computed(() => {
  const baseYield = Number(selectedProductionDish.value?.expected_yield_quantity) || 1.0
  const mult = Number(productionForm.batch_multiplier) || 1.0
  return parseFloat((baseYield * mult).toFixed(2))
})

const productionMrpLines = computed(() => {
  const dish = selectedProductionDish.value
  if (!dish || !Array.isArray(dish.ingredients)) return []
  const mult = Number(productionForm.batch_multiplier) || 1.0

  return dish.ingredients.map(ing => {
    const portionNeeded = (Number(ing.portion) || 0) * mult
    const yieldPct = Number(ing.yield_percentage) > 0 ? Number(ing.yield_percentage) : 100.0
    const grossQty = portionNeeded / (yieldPct / 100.0)
    const presentation = Number(ing.presentation) > 0 ? Number(ing.presentation) : 1.0
    const packsRequired = parseFloat((grossQty / presentation).toFixed(4))
    const stock = Number(ing.stock) || 0
    const unitCost = Number(ing.unit_cost) || 0
    const lineCost = parseFloat((packsRequired * unitCost).toFixed(4))

    return {
      id: ing.id,
      name: cleanIngredientName(ing.name),
      packsRequired,
      stock,
      hasStock: stock >= packsRequired,
      unit_of_measure: ing.unit_of_measure || 'und',
      unit_cost: unitCost,
      lineCost,
    }
  })
})

const hasInsufficientProductionStock = computed(() =>
  productionMrpLines.value.some(line => !line.hasStock)
)

const estimatedBatchTotalCost = computed(() => {
  const raw = productionMrpLines.value.reduce((acc, l) => acc + l.lineCost, 0)
  if (raw > 0) return parseFloat((raw * 1.05).toFixed(4))
  const fallback = Number(selectedProductionDish.value?.cost_price) || 0
  return parseFloat((fallback * (Number(productionForm.batch_multiplier) || 1.0)).toFixed(4))
})

const estimatedBatchUnitCost = computed(() => {
  const actual = Number(productionForm.actual_quantity) || selectedProductionExpectedQty.value || 1.0
  return actual > 0 ? parseFloat((estimatedBatchTotalCost.value / actual).toFixed(4)) : estimatedBatchTotalCost.value
})

const fetchProductionOrders = async () => {
  loadingProdOrders.value = true
  try {
    const { data } = await axios.get('/restaurant/production/orders', {
      params: {
        q: prodSearch.value,
        dish_id: prodFilterDishId.value,
        status: prodFilterStatus.value,
        page: prodPage.value,
        per_page: prodItemsPerPage.value,
        sortBy: prodSortBy.value,
        orderBy: prodOrderBy.value,
      },
    })
    productionOrders.value = data?.data || []
    totalProdOrders.value = data?.meta?.total ?? data?.total ?? productionOrders.value.length
  } catch (err) {
    console.error('Error cargando órdenes de producción:', err)
    toast.error('No se pudieron cargar los lotes producidos.')
  } finally {
    loadingProdOrders.value = false
  }
}

const updateProdTableOptions = (options) => {
  prodPage.value = options.page
  prodItemsPerPage.value = options.itemsPerPage
  if (options.sortBy && options.sortBy.length > 0) {
    prodSortBy.value = options.sortBy[0].key
    prodOrderBy.value = options.sortBy[0].order
  }
}

let prodDebounceTimer
watch([prodSearch, prodFilterDishId, prodFilterStatus], () => {
  prodPage.value = 1
})

watch(
  [prodPage, prodItemsPerPage, prodSortBy, prodOrderBy, prodSearch, prodFilterDishId, prodFilterStatus],
  () => {
    clearTimeout(prodDebounceTimer)
    prodDebounceTimer = setTimeout(() => fetchProductionOrders(), 300)
  }
)

const handleCancelProductionOrder = async (order) => {
  const result = await Swal.fire({
    title: `¿Anular orden ${order.code}?`,
    text: 'Se retirará el lote elaborado del inventario y se devolverán los insumos consumidos a sus existencias.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, anular y revertir',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  })

  if (!result.isConfirmed) return

  cancellingOrderId.value = order.id
  try {
    const { data } = await axios.delete(`/restaurant/production/orders/${order.id}`)
    toast.success(data?.message || 'Orden de producción anulada e insumos restaurados.')
    await fetchProductionOrders()
    await fetchSubRecipes()
    await loadSubRecipeDishes()
  } catch (err) {
    toast.error(err.response?.data?.message || 'No se pudo anular la orden de producción.')
  } finally {
    cancellingOrderId.value = null
  }
}

const printPrepLotLabel = (order) => {
  const win = window.open('', '_blank', 'width=420,height=320')
  if (!win) {
    toast.warning('Habilita las ventanas emergentes para imprimir la etiqueta.')
    return
  }
  const html = `
    <html>
      <head>
        <title>Etiqueta ${order.lot_number || order.code}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 12px; margin: 0; color: #111; }
          .box { border: 2px solid #111; border-radius: 6px; padding: 10px; }
          .title { font-size: 16px; font-weight: 900; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #111; padding-bottom: 4px; }
          .row { font-size: 12px; margin: 4px 0; display: flex; justify-content: space-between; }
          .lot { font-size: 14px; font-weight: bold; margin-top: 6px; text-align: center; background: #eee; padding: 4px; border-radius: 4px; }
        </style>
      </head>
      <body onload="window.print(); window.close();">
        <div class="box">
          <div class="title">${order.dish?.name || 'SUB-RECETA'}</div>
          <div class="row"><span><strong>Cantidad:</strong></span><span>${order.actual_quantity} ${order.output_product?.unit_of_measure || 'und'}</span></div>
          <div class="row"><span><strong>Elaborado:</strong></span><span>${order.produced_at || ''}</span></div>
          <div class="row"><span><strong>Vence:</strong></span><span><strong>${order.expiration_date || 'N/A'}</strong></span></div>
          <div class="row"><span><strong>Responsable:</strong></span><span>${order.user?.name || 'Cocina'}</span></div>
          <div class="lot">LOTE: ${order.lot_number || order.code}</div>
        </div>
      </body>
    </html>
  `
  win.document.write(html)
  win.document.close()
}

const loadSubRecipeDishes = async () => {
  try {
    const res = await axios.get('/dishes', {
      params: {
        status: 3,
        itemsPerPage: 500,
      },
    })
    subRecipeDishes.value = (res.data?.data || []).map(d => ({
      id: d.id,
      name: d.name,
      status: d.status,
      cost_price: Number(d.cost_price) || 0,
      expected_yield_quantity: Number(d.expected_yield_quantity) || 1.0,
      shelf_life_days: Number(d.shelf_life_days) || 7,
      unit_of_measure: d.output_product?.unit_of_measure || d.unit_of_measure || 'porciones',
      ingredients: d.ingredients || [],
    }))
  } catch (e) {
    console.error('Error cargando sub-recetas para producción:', e)
  }
}

const loadData = async () => {
  try {
    await Promise.allSettled([
      fetchProductionOrders(),
      loadSubRecipeDishes(),
    ])
  } catch (err) {
    console.error('Error cargando producción:', err)
  }
}

const openProductionModal = async () => {
  await loadSubRecipeDishes()
  if (subRecipeDishes.value.length) {
    if (!productionForm.dish_id || !subRecipeDishes.value.some(d => d.id === productionForm.dish_id)) {
      const first = subRecipeDishes.value[0]
      productionForm.dish_id = first.id
      productionForm.batch_multiplier = 1.0
      productionForm.actual_quantity = first.expected_yield_quantity
    }
  }
  showProductionModal.value = true
}

watch(() => productionForm.dish_id, (newId) => {
  if (newId) {
    const found = subRecipeDishes.value.find(d => d.id === newId)
    if (found) {
      const mult = Number(productionForm.batch_multiplier) || 1.0
      productionForm.actual_quantity = parseFloat(((found.expected_yield_quantity || 1.0) * mult).toFixed(2))
    }
  }
})

watch(() => productionForm.batch_multiplier, (newMult) => {
  const mult = Number(newMult) || 1.0
  const found = subRecipeDishes.value.find(d => d.id === productionForm.dish_id)
  if (found) {
    productionForm.actual_quantity = parseFloat(((found.expected_yield_quantity || 1.0) * mult).toFixed(2))
  }
})

const submitProduction = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/restaurant/production/orders', productionForm)
    toast.success(res.data?.message || 'Producción completada.')
    showProductionModal.value = false
    await loadData()
    await fetchSubRecipes()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error en orden de producción.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  brandingStore.fetchSettings?.()
  fetchSubRecipes()
  loadData()
})
</script>

<style scoped>
.restaurant-production-waste-view {
  min-height: 100vh;
}
.bg-light {
  background-color: #f8fafc !important;
}
.header-gradient {
  background: linear-gradient(135deg, #7A0099, #E20074) !important;
}
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}
.bg-primary-light {
  background-color: rgba(var(--v-theme-primary), 0.1) !important;
}
.bg-error-light {
  background-color: rgba(var(--v-theme-error), 0.1) !important;
}
.text-super-xs {
  font-size: 0.62rem !important;
  line-height: normal;
}
</style>