<script setup>
import { ref, onMounted, watch, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { formatPrice as formatCurrency } from "@/utils/formatters";
import AppFilterBase from "@/components/AppFilterBase.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

// Listado de platos
const dishes = ref([]);
const totalDishes = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("id");
const orderBy = ref("desc");

// Filtros
const searchQuery = ref("");
const selectedCategory = ref(null);
const selectedStatus = ref(null);
const categories = ref([]);

const statusOptions = [
  { title: "Inactivo", value: 0 },
  { title: "Activo", value: 1 },
  { title: "En Revisión", value: 2 },
  { title: "Base", value: 3 },
];

const statusOptionsWithoutBase = [
  { title: "Inactivo", value: 0 },
  { title: "Activo", value: 1 },
  { title: "En Revisión", value: 2 },
];

const isBaseDish = ref(false);
const dishStatus = ref(1);

// Diálogo de edición / creación
const isEditDialogVisible = ref(false);
const currentDish = ref({
  id: null,
  name: "",
  category_id: null,
  percentage_profit: 1.5,
  cost_price: 0,
  suggested_price: 0,
  designated_price: 0,
  status: 1,
  ingredients: [],
});

const formErrors = ref({});
const isSaving = ref(false);

// Agregar ingrediente
const productSearch = ref("");
const productOptions = ref([]);
const loadingProducts = ref(false);
const selectedProduct = ref(null);
const ingredientPortion = ref(0);
const isCpvDeductible = ref(false);

const fetchCategories = async () => {
  try {
    // Obtener únicamente categorías relacionadas con platos
    const { data } = await axios.get("/categories", { params: { type: "dishes" } });
    categories.value = data;
  } catch (error) {
    console.error("Error al obtener categorías:", error);
  }
};

const fetchDishes = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    category_id: selectedCategory.value,
    status: selectedStatus.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  try {
    const response = await axios.get("/dishes", { params });
    dishes.value = response.data.data;
    totalDishes.value = response.data.meta.total;
  } catch (error) {
    console.error("Error al obtener platos:", error);
    toast.error("No se pudieron cargar los platos.");
  } finally {
    loading.value = false;
  }
};

// Autocomplete de productos para ingredientes
const searchProducts = async (query) => {
  loadingProducts.value = true;
  try {
    const { data } = await axios.get("/products/autocomplete", {
      params: { q: query || "" },
    });
    // El backend para forAutocomplete devuelve los datos envueltos en response.data.data
    const items = data.data || [];
    productOptions.value = items.map((p) => ({
      id: p.id,
      name: p.name,
      unit_cost: parseFloat(p.unit_cost || 0),
      presentation: parseFloat(p.presentation || 1),
      unit_of_measure: p.unit_of_measure || "und",
      is_base_dish: p.is_base_dish || false,
      ingredients: p.ingredients || [],
    }));
  } catch (error) {
    console.error("Error al buscar productos:", error);
  } finally {
    loadingProducts.value = false;
  }
};

let searchDebounce;
watch(productSearch, (newVal) => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    searchProducts(newVal);
  }, 300);
});

// Calcular costo de la porción
const calculatedPortionCost = computed(() => {
  if (!selectedProduct.value || !ingredientPortion.value) return 0;
  const cost = selectedProduct.value.unit_cost;
  const presentation = selectedProduct.value.presentation || 1;
  return parseFloat(((ingredientPortion.value * cost) / presentation).toFixed(4));
});

// Añadir ingrediente a la lista local
const addIngredient = () => {
  if (!selectedProduct.value || !ingredientPortion.value) {
    toast.warning("Debes seleccionar un ingrediente y especificar la porción.");
    return;
  }

  // Si es un plato base, expandir e importar/sumar sus ingredientes
  if (selectedProduct.value.is_base_dish) {
    const qty = parseFloat(ingredientPortion.value || 1);
    
    selectedProduct.value.ingredients.forEach((ing) => {
      // Buscar si ya existe el ingrediente en el plato actual
      const existing = currentDish.value.ingredients.find(
        (i) => i.product_id === ing.product_id
      );

      const ingPortion = parseFloat(ing.portion || 0) * qty;
      const ingCost = parseFloat(ing.unit_cost || 0);
      const ingPresentation = parseFloat(ing.presentation || 1);
      const portionCost = parseFloat(((ingPortion * ingCost) / ingPresentation).toFixed(4));

      if (existing) {
        // Si ya existe, sumar la porción y recalcular el costo de la porción acumulada
        existing.portion = parseFloat((existing.portion + ingPortion).toFixed(4));
        existing.designated_cost = parseFloat((existing.designated_cost + portionCost).toFixed(4));
      } else {
        // Si no existe, agregarlo como nuevo ingrediente
        currentDish.value.ingredients.push({
          product_id: ing.product_id,
          name: ing.name,
          portion: ingPortion,
          unit_cost: ingCost,
          presentation: ingPresentation,
          unit_of_measure: ing.unit_of_measure || "und",
          designated_cost: portionCost,
          cpv_deductible: false,
        });
      }
    });

    // Limpiar campos
    selectedProduct.value = null;
    ingredientPortion.value = 0;
    isCpvDeductible.value = false;
    productSearch.value = "";
    productOptions.value = [];

    recalculateDishTotals();
    toast.success("Ingredientes de la receta base añadidos.");
    return;
  }

  // Comprobar si ya existe (ingrediente normal)
  const existing = currentDish.value.ingredients.find(
    (i) => i.product_id === selectedProduct.value.id
  );

  if (existing) {
    const qty = parseFloat(ingredientPortion.value || 0);
    existing.portion = parseFloat((existing.portion + qty).toFixed(4));
    existing.designated_cost = parseFloat((existing.designated_cost + calculatedPortionCost.value).toFixed(4));
    
    // Limpiar campos
    selectedProduct.value = null;
    ingredientPortion.value = 0;
    isCpvDeductible.value = false;
    productSearch.value = "";
    productOptions.value = [];

    recalculateDishTotals();
    toast.success("Cantidad sumada al ingrediente existente.");
    return;
  }

  currentDish.value.ingredients.push({
    product_id: selectedProduct.value.id,
    name: selectedProduct.value.name,
    portion: parseFloat(ingredientPortion.value),
    unit_cost: selectedProduct.value.unit_cost,
    presentation: selectedProduct.value.presentation,
    unit_of_measure: selectedProduct.value.unit_of_measure,
    designated_cost: calculatedPortionCost.value,
    cpv_deductible: isCpvDeductible.value,
  });

  // Limpiar campos
  selectedProduct.value = null;
  ingredientPortion.value = 0;
  isCpvDeductible.value = false;
  productSearch.value = "";
  productOptions.value = [];

  recalculateDishTotals();
  toast.success("Ingrediente añadido.");
};

// Eliminar ingrediente de la lista local
const removeIngredient = (index) => {
  currentDish.value.ingredients.splice(index, 1);
  recalculateDishTotals();
};

// Recalcular costo total y sugerido
const recalculateDishTotals = () => {
  let cost = 0;
  currentDish.value.ingredients.forEach((ing) => {
    cost += parseFloat(ing.designated_cost);
  });

  currentDish.value.cost_price = parseFloat(cost.toFixed(2));
  
  // Calcular sugerido = costo * porcentaje_ganancia
  const profitMultiplier = parseFloat(currentDish.value.percentage_profit || 0);
  currentDish.value.suggested_price = parseFloat((cost * profitMultiplier).toFixed(2));
  
  // Por defecto, igualar precio designado al sugerido si es un plato nuevo
  if (!currentDish.value.id) {
    currentDish.value.designated_price = currentDish.value.suggested_price;
  }
};

watch(
  () => currentDish.value.percentage_profit,
  () => {
    recalculateDishTotals();
  }
);

// Paginación y filtros optimizados
let debounceTimer;

watch([searchQuery, selectedCategory, selectedStatus], () => {
  page.value = 1;
});

watch(
  [page, itemsPerPage, searchQuery, selectedCategory, selectedStatus],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchDishes(), 300);
  }
);

// Cargar la lista inicial de productos para ingredientes al abrir el diálogo
watch(isEditDialogVisible, (val) => {
  if (val) {
    searchProducts("");
  }
});

// Detectar si el usuario selecciona un plato base para inicializar la cantidad por defecto en 1
watch(selectedProduct, (newVal) => {
  if (newVal) {
    if (newVal.is_base_dish) {
      ingredientPortion.value = 1;
    } else {
      ingredientPortion.value = 0;
    }
  }
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
};

// Acciones de dialogos
const handleAddDish = () => {
  currentDish.value = {
    id: null,
    name: "",
    category_id: null,
    percentage_profit: 1.5,
    cost_price: 0,
    suggested_price: 0,
    designated_price: 0,
    status: 1,
    ingredients: [],
  };
  isBaseDish.value = false;
  dishStatus.value = 1;
  formErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleEditDish = async (dish) => {
  try {
    const { data } = await axios.get(`/dishes/${dish.id}`);
    const details = data.data;

    currentDish.value = {
      id: details.id,
      name: details.name,
      category_id: details.category_id,
      percentage_profit: details.percentage_profit,
      cost_price: details.cost_price,
      suggested_price: details.suggested_price,
      designated_price: details.designated_price,
      status: details.status,
      ingredients: details.ingredients.map((ing) => ({
        product_id: ing.id,
        name: ing.name,
        portion: ing.portion,
        unit_cost: ing.unit_cost,
        presentation: ing.presentation,
        unit_of_measure: ing.unit_of_measure,
        designated_cost: ing.designated_cost,
        cpv_deductible: false, // Opcional
      })),
    };
    isBaseDish.value = details.status === 3;
    dishStatus.value = details.status === 3 ? 1 : details.status;
    formErrors.value = {};
    isEditDialogVisible.value = true;
  } catch (error) {
    console.error("Error al cargar detalles del plato:", error);
    toast.error("No se pudo cargar la información del plato.");
  }
};

const handleSaveDish = async () => {
  if (currentDish.value.ingredients.length === 0) {
    toast.warning("Debes agregar al menos un ingrediente al plato.");
    return;
  }

  currentDish.value.status = isBaseDish.value ? 3 : dishStatus.value;
  isSaving.value = true;
  formErrors.value = {};

  const isNew = !currentDish.value.id;
  const url = isNew ? "/dishes" : `/dishes/${currentDish.value.id}`;
  const method = isNew ? "post" : "put";

  try {
    await axios[method](url, currentDish.value);
    toast.success(`Plato ${isNew ? "creado" : "actualizado"} con éxito.`);
    isEditDialogVisible.value = false;
    fetchDishes();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar el plato:", error);
      toast.error("Hubo un error al guardar el plato.");
    }
  } finally {
    isSaving.value = false;
  }
};

const handleDeleteDish = async (dish) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `¡No podrás revertir la eliminación del plato "${dish.name}"!`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
    customClass: {
      confirmButton:
        "v-btn v-btn--variant-flat bg-error text-white h-auto py-2 px-6 rounded-lg font-weight-black uppercase",
      cancelButton:
        "v-btn v-btn--variant-tonal text-secondary h-auto py-2 px-6 rounded-lg font-weight-black uppercase",
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/dishes/${dish.id}`);
      toast.success("Plato eliminado con éxito.");
      fetchDishes();
    } catch (error) {
      console.error("Error al eliminar plato:", error);
      toast.error(
        error.response?.data?.message || "No se pudo eliminar el plato."
      );
    }
  }
};

const getStatusChipColor = (status) => {
  if (status === 1) return "success";
  if (status === 2) return "info";
  if (status === 3) return "warning";
  return "error";
};

const getStatusLabel = (status) => {
  if (status === 1) return "Activo";
  if (status === 2) return "En Revisión";
  if (status === 3) return "Base";
  return "Inactivo";
};

onMounted(() => {
  fetchCategories();
  fetchDishes();
});
</script>

<template>
  <div class="inventory-dishes-view pb-12">
    <!-- Header/Filters Premium -->
    <!-- Header/Filters Premium utilizando AppFilterBase -->
    <AppFilterBase
      v-model:search="searchQuery"
      search-placeholder="Buscar platos..."
      :show-add="true"
      add-button-text="Crear Plato"
      :show-advanced="true"
      :has-advanced-filters="!!selectedCategory || !!selectedStatus"
      @clear="searchQuery = ''; selectedCategory = null; selectedStatus = null; fetchDishes()"
      @add="handleAddDish"
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

    <!-- Listado Principal -->
    <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
      <!-- Loading State con Skeleton Loader -->
      <div v-if="loading" class="pa-6">
        <VSkeletonLoader
          type="table-row-divider@5"
          class="bg-transparent"
        />
      </div>

      <!-- Empty State -->
      <div v-else-if="dishes.length === 0" class="pa-16 text-center text-disabled">
        <VIcon icon="tabler-meat" size="64" class="mb-4 opacity-20" />
        <h3 class="text-h6 font-weight-black opacity-50 uppercase">No hay platos registrados</h3>
        <p>Registra un nuevo plato o ajusta tus filtros</p>
      </div>

      <!-- Desktop Table view -->
      <div v-else class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="itemsPerPage"
          :page="page"
          :items="dishes"
          :items-length="totalDishes"
          :loading="loading"
          class="premium-table text-no-wrap"
          @update:options="updateTableOptions"
          :headers="[
            { title: 'ID', key: 'id', sortable: true },
            { title: 'Nombre', key: 'name', sortable: true },
            { title: 'Categoría', key: 'category.name', sortable: false },
            { title: 'Costo', key: 'cost_price', align: 'end' },
            { title: 'Ganancia', key: 'percentage_profit', align: 'end' },
            { title: 'Sugerido', key: 'suggested_price', align: 'end' },
            { title: 'Designado', key: 'designated_price', align: 'end' },
            { title: 'Estado', key: 'status', align: 'center' },
            { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
          ]"
        >
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">#{{ item.id }}</span>
          </template>

          <template #item.name="{ item }">
            <span class="font-weight-bold text-high-emphasis uppercase text-xs">{{ item.name }}</span>
          </template>

          <template #item.category.name="{ item }">
            <span class="text-xs uppercase text-disabled">{{ item.category?.name || 'S/C' }}</span>
          </template>

          <template #item.cost_price="{ item }">
            <span class="text-xs text-high-emphasis">{{ formatCurrency(item.cost_price) }}</span>
          </template>

          <template #item.percentage_profit="{ item }">
            <span class="text-xs font-weight-black text-secondary">{{ item.percentage_profit }}x</span>
          </template>

          <template #item.suggested_price="{ item }">
            <span class="text-xs text-disabled">{{ formatCurrency(item.suggested_price) }}</span>
          </template>

          <template #item.designated_price="{ item }">
            <span class="text-xs font-weight-black text-success">{{ formatCurrency(item.designated_price) }}</span>
          </template>

          <template #item.status="{ item }">
            <VChip :color="getStatusChipColor(item.status)" size="x-small" label class="font-weight-black uppercase">
              {{ getStatusLabel(item.status) }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-2 justify-end">
              <IconBtn color="warning" class="bg-warning-light" @click="handleEditDish(item)">
                <VIcon icon="tabler-edit" />
              </IconBtn>
              <IconBtn color="error" class="bg-error-light" @click="handleDeleteDish(item)">
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Mobile Cards view -->
      <div v-if="dishes.length > 0" class="d-block d-md-none pa-2">
        <div class="d-flex flex-column gap-2">
          <VCard
            v-for="item in dishes"
            :key="item.id"
            variant="flat"
            class="product-mobile-card border mb-1"
          >
            <div class="pa-3">
              <div class="d-flex gap-3 align-start">
                <div class="flex-grow-1 min-width-0">
                  <div class="d-flex align-center gap-1 mb-1">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate-2-lines">
                      <span class="text-primary text-xs">#{{ item.id }}</span>
                      <span class="mx-1 text-disabled">|</span>
                      {{ item.name.toUpperCase() }}
                    </h3>
                  </div>
                  
                  <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                    <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.category?.name || 'S/C' }}</span>
                  </div>
                </div>
                
                <VChip :color="getStatusChipColor(item.status)" size="x-small" label class="font-weight-black uppercase">
                  {{ getStatusLabel(item.status) }}
                </VChip>
              </div>

              <VDivider class="my-3 border-opacity-10" />

              <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Costo</span>
                  <span class="text-base font-weight-black">
                    {{ formatCurrency(item.cost_price) }}
                  </span>
                </div>
                <div class="d-flex flex-column text-right">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Designado</span>
                  <span class="text-base font-weight-black text-success">
                    {{ formatCurrency(item.designated_price) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Acciones Rectangulares al 100% -->
            <div class="d-flex border-t border-opacity-10">
              <VBtn 
                color="warning" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="40"
                icon="tabler-edit" 
                @click="handleEditDish(item)"
              />
              <VDivider vertical class="border-opacity-10" />
              <VBtn 
                color="error" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="40"
                icon="tabler-trash" 
                @click="handleDeleteDish(item)"
              />
            </div>
          </VCard>
        </div>

        <div class="mt-4">
          <AppMobilePagination
            :page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalDishes"
            :loading="loading"
            :sort-by="sortBy"
            :order-by="orderBy"
            @change="updateTableOptions"
          />
        </div>
      </div>
    </VCard>

    <!-- Diálogo de Formulario -->
    <VDialog v-model="isEditDialogVisible" max-width="1100px" persistent :fullscreen="$vuetify.display.xs" scrollable>
      <VCard class="overflow-hidden rounded-lg">
        <!-- Header Premium -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm text-white">
            <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
              <VIcon icon="tabler-meat" color="primary" size="26" />
            </VAvatar>
            <div class="flex-grow-1 text-white">
              <h2 class="text-h6 font-weight-black mb-0 text-uppercase text-white" style="color: #ffffff !important;">
                {{ currentDish.id ? "Editar Plato" : "Crear Nuevo Plato" }}
              </h2>
              <span class="text-super-xs opacity-75 uppercase font-weight-bold text-white" style="color: #ffffff !important;">
                Asignación de ingredientes, porciones y precios
              </span>
            </div>
            <VSpacer />
            <VBtn icon variant="tonal" color="white" size="small" class="rounded-lg ms-3" @click="isEditDialogVisible = false">
              <VIcon>tabler-x</VIcon>
            </VBtn>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="bg-light pa-4 pa-sm-6" style="overflow-y: auto;">
          <VRow>
            <!-- Lado Izquierdo: Datos Básicos del Plato -->
            <VCol cols="12" md="5" class="d-flex flex-column gap-6">
              <div>
                <h3 class="text-subtitle-2 font-weight-black text-high-emphasis uppercase mb-4">
                  Información Básica
                </h3>
                <VCard variant="flat" class="pa-4 bg-white border rounded-lg d-flex flex-column gap-4">
                  <AppTextField
                    v-model="currentDish.name"
                    label="Nombre del Plato"
                    placeholder="Ej: Waffle con Nutella"
                    :error-messages="formErrors.name"
                    required
                  />

                  <VSelect
                    v-model="currentDish.category_id"
                    :items="categories"
                    item-title="name"
                    item-value="id"
                    label="Categoría"
                    placeholder="Selecciona Categoría"
                    :error-messages="formErrors.category_id"
                    required
                  />

                  <VSelect
                    v-model="dishStatus"
                    :items="statusOptionsWithoutBase"
                    label="Estado"
                    placeholder="Selecciona Estado"
                    :disabled="isBaseDish"
                    :error-messages="formErrors.status"
                    required
                  />

                  <VCheckbox
                    v-model="isBaseDish"
                    label="Base"
                    class="mt-2"
                  />
                </VCard>
              </div>

              <div>
                <h3 class="text-subtitle-2 font-weight-black text-high-emphasis uppercase mb-4">
                  Ajustes de Precios e Ingresos
                </h3>
                <VCard variant="flat" class="pa-4 bg-white border rounded-lg d-flex flex-column gap-4">
                  <VRow>
                    <VCol cols="6">
                      <AppTextField
                        v-model="currentDish.percentage_profit"
                        label="Ganancia (Multiplicador)"
                        type="number"
                        step="0.01"
                        :error-messages="formErrors.percentage_profit"
                        required
                      />
                    </VCol>
                    <VCol cols="6">
                      <AppTextField
                        v-model="currentDish.cost_price"
                        label="Costo de Plato (Calculado)"
                        readonly
                        prefix="$"
                      />
                    </VCol>
                  </VRow>

                  <VRow>
                    <VCol cols="6">
                      <AppTextField
                        v-model="currentDish.suggested_price"
                        label="Precio Sugerido"
                        readonly
                        prefix="$"
                      />
                    </VCol>
                    <VCol cols="6">
                      <AppTextField
                        v-model="currentDish.designated_price"
                        label="Precio Designado (Venta)"
                        type="number"
                        step="0.01"
                        prefix="$"
                        :error-messages="formErrors.designated_price"
                        required
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </div>
            </VCol>

            <!-- Lado Derecho: Añadir Ingredientes y Receta -->
            <VCol cols="12" md="7" class="d-flex flex-column gap-6">
              <div>
                <h3 class="text-subtitle-2 font-weight-black text-high-emphasis uppercase mb-4">
                  Añadir Ingredientes (Productos)
                </h3>
                <VCard variant="flat" class="pa-4 bg-white border rounded-lg">
                  <VRow align="center">
                    <VCol cols="12" sm="12" class="pb-2">
                      <!-- Buscador inteligente de productos en base de datos -->
                      <VAutocomplete
                        v-model="selectedProduct"
                        v-model:search="productSearch"
                        :items="productOptions"
                        :loading="loadingProducts"
                        item-title="name"
                        return-object
                        placeholder="Buscar producto ingrediente..."
                        clearable
                        density="comfortable"
                        hide-details
                        :no-filter="true"
                        no-data-text="Escribe para buscar ingredientes..."
                      />
                    </VCol>


                    
                    <VCol cols="12" sm="5" v-if="selectedProduct" class="py-1">
                      <div class="text-xs text-disabled">
                        Costo base: <strong>{{ formatCurrency(selectedProduct.unit_cost) }}</strong> | Presentación: <strong>{{ selectedProduct.presentation }} {{ selectedProduct.unit_of_measure }}</strong>
                      </div>
                    </VCol>
                  </VRow>

                  <VRow align="center" v-if="selectedProduct" class="mt-2">
                    <VCol cols="12" sm="4">
                      <AppTextField
                        v-model="ingredientPortion"
                        :label="selectedProduct.is_base_dish ? 'Cantidad de Base' : 'Porción utilizada'"
                        type="number"
                        step="0.01"
                        :suffix="selectedProduct.unit_of_measure"
                        hide-details
                      />
                    </VCol>
                    <VCol cols="12" sm="4">
                      <AppTextField
                        :model-value="calculatedPortionCost"
                        label="Costo de Porción"
                        prefix="$"
                        readonly
                        hide-details
                      />
                    </VCol>
                    <VCol cols="12" sm="4" class="text-right">
                      <VBtn color="success" block class="font-weight-black mt-5" @click="addIngredient">
                        <VIcon icon="tabler-plus" class="me-1" />
                        Añadir
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCard>
              </div>

              <div>
                <h3 class="text-subtitle-2 font-weight-black text-high-emphasis uppercase mb-4">
                  Receta / Ingredientes Seleccionados
                </h3>
                <VCard variant="flat" class="border rounded-lg overflow-hidden bg-white">
                  <VTable density="compact" class="bg-transparent inner-recipe-table">
                    <thead>
                      <tr>
                        <th class="text-super-xs font-weight-black uppercase">Ingrediente</th>
                        <th class="text-super-xs font-weight-black uppercase text-center" style="width: 120px;">Porción</th>
                        <th class="text-super-xs font-weight-black uppercase text-end" style="width: 120px;">Costo Porción</th>
                        <th class="text-super-xs font-weight-black uppercase text-center" style="width: 80px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(ing, index) in currentDish.ingredients" :key="index">
                        <td>
                          <span class="text-xs font-weight-bold text-high-emphasis uppercase">{{ ing.name }}</span>
                        </td>
                        <td class="text-center">
                          <span class="text-xs">{{ ing.portion }} {{ ing.unit_of_measure }}</span>
                        </td>
                        <td class="text-end">
                          <span class="text-xs font-weight-black text-primary">{{ formatCurrency(ing.designated_cost) }}</span>
                        </td>
                        <td class="text-center">
                          <IconBtn color="error" size="small" @click="removeIngredient(index)">
                            <VIcon icon="tabler-x" size="16" />
                          </IconBtn>
                        </td>
                      </tr>
                      <tr v-if="currentDish.ingredients.length === 0">
                        <td colspan="4" class="text-center py-6 text-disabled text-xs font-weight-bold uppercase">
                          No has añadido ingredientes a la receta aún
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </VCard>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light">
          <VRow no-gutters class="w-100">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn color="secondary" variant="tonal" size="large" block height="50" class="font-weight-black rounded-lg uppercase" @click="isEditDialogVisible = false">
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn color="primary" variant="flat" size="large" block height="50" class="font-weight-black rounded-lg shadow-primary uppercase" :loading="isSaving" @click="handleSaveDish">
                <VIcon icon="tabler-device-floppy" class="me-2" />
                {{ currentDish.id ? 'Guardar Cambios' : 'Crear Plato' }}
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.inventory-dishes-view {
  min-height: 100vh;
}

.bg-light {
  background-color: #f8fafc !important;
}

.header-gradient {
  background: linear-gradient(135deg, #7A0099 0%, #E20074 100%) !important;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.product-row {
  transition: transform 0.2s, box-shadow 0.2s;
}

.product-row:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.premium-table {
  border-radius: 8px;
  overflow: hidden;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.bg-warning-light {
  background-color: rgba(var(--v-theme-warning), 0.1) !important;
}

.bg-error-light {
  background-color: rgba(var(--v-theme-error), 0.1) !important;
}

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.inner-recipe-table :deep(th) {
  background-color: rgba(var(--v-border-color), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-super-xs {
  font-size: 0.62rem !important;
  line-height: normal;
}
</style>
