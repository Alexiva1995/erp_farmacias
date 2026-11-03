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
            <h2 class="text-h6 font-weight-bold mb-0">Producción en Lote & Mermas de Cocina</h2>
            <div class="text-caption text-medium-emphasis">Gestión de sub-recetas (salsas, masas, preparaciones base) y control de desperdicios</div>
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
            <v-tab value="waste">
              <v-icon start size="18">tabler-trash-x</v-icon>
              Control de Mermas
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
        :show-add="true"
        add-button-text="Nueva Sub-Receta"
        :show-advanced="true"
        :has-advanced-filters="!!selectedCategory || selectedStatus !== null"
        @clear="subRecipeSearch = ''; selectedCategory = null; selectedStatus = null; fetchSubRecipes()"
        @add="openSubRecipeModal"
      >
        <template #advanced-filters>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="selectedCategory"
              :items="categories"
              item-title="name"
              item-value="id"
              placeholder="Filtrar por Categoría"
              clearable
              density="compact"
              hide-details
            />
          </VCol>
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
              { title: 'Categoría', key: 'category.name', sortable: false },
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

            <template #item.category.name="{ item }">
              <span class="text-xs uppercase text-disabled">{{ item.category?.name || 'S/C' }}</span>
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
                  color="primary"
                  class="bg-primary-light"
                  title="Producir Lote de esta Sub-Receta"
                  @click="openProduceFromSubRecipe(item)"
                >
                  <VIcon icon="tabler-packages" size="18" />
                  <VTooltip activator="parent" location="top">Producir Lote</VTooltip>
                </IconBtn>
                <IconBtn
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
      <v-row class="mb-4" dense>
        <v-col cols="12" class="d-flex justify-end gap-2">
          <v-btn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openProductionModal"
          >
            Nueva Orden de Producción
          </v-btn>
        </v-col>
      </v-row>

      <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
        <v-table density="comfortable" hover class="premium-table text-no-wrap">
          <thead>
            <tr>
              <th>Código</th>
              <th>Sub-Receta / Preparación</th>
              <th>Producto Elaborado</th>
              <th class="text-center">Multiplicador</th>
              <th class="text-center">Cantidad Real</th>
              <th class="text-right">Costo Unit. Calculado</th>
              <th class="text-right">Costo Total</th>
              <th>Lote Generado</th>
              <th>Estado</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in productionOrders" :key="order.id">
              <td class="font-weight-bold text-primary">{{ order.code }}</td>
              <td class="font-weight-bold text-xs uppercase">{{ order.dish?.name }}</td>
              <td>
                <span class="font-weight-medium text-xs">{{ order.output_product?.name || 'Insumo Base' }}</span>
              </td>
              <td class="text-center text-xs font-weight-black text-secondary">{{ order.batch_multiplier }}x</td>
              <td class="text-center font-weight-bold text-success text-xs">{{ order.actual_quantity }}</td>
              <td class="text-right text-xs font-weight-bold">{{ formatCurrency(order.unit_cost_calculated) }}</td>
              <td class="text-right font-weight-bold text-primary text-xs">{{ formatCurrency(order.total_production_cost) }}</td>
              <td>
                <v-chip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ order.lot_number || 'N/A' }}
                </v-chip>
              </td>
              <td>
                <v-chip size="small" color="success" class="font-weight-bold uppercase">
                  {{ order.status_label || order.status }}
                </v-chip>
              </td>
              <td class="text-caption text-medium-emphasis">{{ order.produced_at }}</td>
            </tr>
            <tr v-if="!productionOrders.length">
              <td colspan="10" class="text-center py-8 text-medium-emphasis">
                No hay órdenes de producción registradas aún.
              </td>
            </tr>
          </tbody>
        </v-table>
      </VCard>
    </div>

    <!-- PESTAÑA 3: CONTROL DE MERMAS Y DESPERDICIOS -->
    <div v-else>
      <v-row class="mb-4" dense>
        <v-col cols="12" class="d-flex justify-end">
          <v-btn
            color="error"
            prepend-icon="tabler-alert-triangle"
            @click="openWasteModal"
          >
            Registrar Merma / Baja
          </v-btn>
        </v-col>
      </v-row>

      <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
        <v-table density="comfortable" hover class="premium-table text-no-wrap">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Ítem Afectado</th>
              <th>Estación</th>
              <th class="text-center">Cantidad</th>
              <th class="text-right">Costo Unitario</th>
              <th class="text-right">Pérdida Total</th>
              <th>Motivo de Baja</th>
              <th>Reportado Por</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="waste in kitchenWastes" :key="waste.id">
              <td>
                <v-chip size="x-small" :color="waste.item_type === 'dish' ? 'warning' : 'info'" class="font-weight-bold">
                  {{ waste.item_type === 'dish' ? 'Platillo' : 'Insumo' }}
                </v-chip>
              </td>
              <td class="font-weight-bold text-xs uppercase">{{ waste.item_name }}</td>
              <td class="text-xs text-medium-emphasis">{{ waste.station_name }}</td>
              <td class="text-center font-weight-bold text-error text-xs">{{ waste.quantity }}</td>
              <td class="text-right text-xs">{{ formatCurrency(waste.unit_cost) }}</td>
              <td class="text-right font-weight-bold text-error text-xs">
                -{{ formatCurrency(waste.total_loss_usd) }}
              </td>
              <td>
                <v-chip size="small" color="error" variant="outlined" class="font-weight-bold">
                  {{ waste.reason_label || waste.reason }}
                </v-chip>
              </td>
              <td class="text-caption">{{ waste.user_name }}</td>
              <td class="text-caption text-medium-emphasis">{{ waste.occurred_at }}</td>
            </tr>
            <tr v-if="!kitchenWastes.length">
              <td colspan="9" class="text-center py-8 text-medium-emphasis">
                No hay mermas registradas en el período.
              </td>
            </tr>
          </tbody>
        </v-table>
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
                Crear Sub-Receta / Preparación Base
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
              Guardar Sub-Receta Y Habilitar En Platos
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: CREAR ORDEN DE PRODUCCIÓN -->
    <v-dialog v-model="showProductionModal" max-width="600">
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
            </div>
            <v-spacer />
            <v-btn icon variant="tonal" color="white" size="small" class="rounded-lg" @click="showProductionModal = false">
              <v-icon>tabler-x</v-icon>
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitProduction">
            <v-select
              v-model="productionForm.dish_id"
              :items="subRecipeDishes"
              item-title="name"
              item-value="id"
              label="Seleccionar Sub-Receta / Preparación Base"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="productionForm.batch_multiplier"
                  type="number"
                  step="0.1"
                  min="0.1"
                  label="Multiplicador de Lote"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="productionForm.actual_quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Rendimiento Real Obtenido"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>
            <v-textarea
              v-model="productionForm.notes"
              label="Notas u observaciones de cocción"
              rows="2"
              density="compact"
              variant="outlined"
              class="mb-3"
            />
            <v-btn type="submit" color="primary" block :loading="submitting">
              Producir y Descontar Insumos (FEFO + Rendimiento)
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: REGISTRAR MERMA -->
    <v-dialog v-model="showWasteModal" max-width="500">
      <v-card class="rounded-lg overflow-hidden">
        <v-card-title class="pa-0">
          <div class="bg-error pa-4 d-flex align-center shadow-sm text-white">
            <v-avatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <v-icon icon="tabler-trash-x" color="error" size="22" />
            </v-avatar>
            <div class="flex-grow-1 text-white">
              <h2 class="text-subtitle-1 font-weight-black mb-0 text-uppercase text-white" style="color: #ffffff !important;">
                Registrar Merma / Desperdicio
              </h2>
            </div>
            <v-spacer />
            <v-btn icon variant="tonal" color="white" size="small" class="rounded-lg" @click="showWasteModal = false">
              <v-icon>tabler-x</v-icon>
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitWaste">
            <v-select
              v-model="wasteForm.reason"
              :items="wasteReasons"
              item-title="label"
              item-value="value"
              label="Motivo de la Merma / Pérdida"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />
            <v-row dense>
              <v-col cols="6">
                <v-select
                  v-model="wasteForm.item_type"
                  :items="[{ title: 'Insumo (Producto)', value: 'product' }, { title: 'Platillo Terminado', value: 'dish' }]"
                  label="Tipo de Ítem"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="wasteForm.quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Cantidad Perdida"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
            </v-row>
            <v-textarea
              v-model="wasteForm.notes"
              label="Explicación / Justificación"
              rows="2"
              density="compact"
              variant="outlined"
              class="mb-3"
            />
            <v-btn type="submit" color="error" block :loading="submitting">
              Registrar Baja de Cocina
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { formatCurrency as formatRawCurrency } from '@/utils/currencyFormatter'
import AppFilterBase from '@/components/AppFilterBase.vue'

const brandingStore = useBrandingStore()

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
    const rounded = rawCop >= 100 ? Math.ceil(rawCop / 100) * 100 : Math.round(rawCop)
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
      category_id: selectedCategory.value,
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
watch([subRecipeSearch, selectedCategory, selectedStatus], () => {
  subRecipePage.value = 1
})

watch(
  [subRecipePage, subRecipeItemsPerPage, subRecipeSortBy, subRecipeOrderBy, subRecipeSearch, selectedCategory, selectedStatus],
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
const openProduceFromSubRecipe = (dish) => {
  productionForm.dish_id = dish.id
  productionForm.batch_multiplier = 1.0
  productionForm.actual_quantity = dish.expected_yield_quantity || 1.0
  showProductionModal.value = true
}

// Estado para Lotes y Mermas
const productionOrders = ref([])
const kitchenWastes = ref([])
const subRecipeDishes = ref([])
const categories = ref([])
const submitting = ref(false)

const showProductionModal = ref(false)
const showWasteModal = ref(false)
const showSubRecipeModal = ref(false)
const submittingSubRecipe = ref(false)

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
    ingredientOptions.value = (data.data || []).filter(item => !item.is_base_dish).map(item => ({
      ...item,
      name: cleanIngredientName(item.name),
    }))
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
    existing.portion += portion
    existing.designated_cost = parseFloat((existing.designated_cost + cost).toFixed(4))
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
  subRecipeForm.name = ''
  subRecipeForm.category_id = null
  subRecipeForm.kitchen_station_id = null
  subRecipeForm.expected_yield_quantity = 1.0
  subRecipeForm.unit_of_measure = 'porción'
  subRecipeForm.shelf_life_days = 7
  subRecipeForm.ingredients = []
  selectedIngredient.value = null
  ingredientPortion.value = 1
  showSubRecipeModal.value = true
}

const submitSubRecipe = async () => {
  if (!subRecipeForm.ingredients.length) {
    toast.warning('Debes agregar al menos un insumo a la sub-receta.')
    return
  }

  submittingSubRecipe.value = true
  try {
    const { data } = await axios.post('/restaurant/production/sub-recipes', subRecipeForm)
    toast.success(data.message || 'Sub-receta creada con éxito.')
    showSubRecipeModal.value = false
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

// Formulario de mermas
const wasteForm = reactive({
  item_type: 'product',
  product_id: 1,
  dish_id: null,
  quantity: 1.0,
  reason: 'burned_spoiled',
  notes: '',
})

const wasteReasons = [
  { label: 'Quemado / Malogrado', value: 'burned_spoiled' },
  { label: 'Vencimiento / Caducado', value: 'expired' },
  { label: 'Merma de Limpieza / Cocción', value: 'preparation_trim' },
  { label: 'Comida de Personal', value: 'staff_meal' },
  { label: 'Cortesía de la Casa', value: 'courtesy' },
  { label: 'Caída / Accidente', value: 'dropped' },
]

const loadData = async () => {
  try {
    const [resProd, resWaste, resDishes, resCats] = await Promise.all([
      axios.get('/restaurant/production/orders'),
      axios.get('/restaurant/waste/records'),
      axios.get('/dishes?per_page=100'),
      axios.get('/categories', { params: { type: 'dishes' } }),
    ])
    productionOrders.value = resProd.data?.data || []
    kitchenWastes.value = resWaste.data?.data || []
    subRecipeDishes.value = (resDishes.data?.data || []).map(d => ({
      id: d.id,
      name: d.name,
      status: d.status,
    }))
    categories.value = resCats.data || []
  } catch (err) {
    console.error('Error cargando producción/mermas:', err)
  }
}

const openProductionModal = () => {
  if (subRecipeDishes.value.length) {
    productionForm.dish_id = subRecipeDishes.value[0].id
  }
  showProductionModal.value = true
}

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

const openWasteModal = () => {
  showWasteModal.value = true
}

const submitWaste = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/restaurant/waste/records', wasteForm)
    toast.success(res.data?.message || 'Merma registrada.')
    showWasteModal.value = false
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al registrar merma.')
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