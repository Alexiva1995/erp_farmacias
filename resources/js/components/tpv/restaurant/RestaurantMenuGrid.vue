<script setup>
import { computed, ref } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'
import { roundUpToNearestHundred } from '@/utils/roundUpToNearesHundred.js'

const props = defineProps({
  dishes: { type: Array, default: () => [] },
  dishesLoading: { type: Boolean, default: false },
  dishCategories: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  productsLoading: { type: Boolean, default: false },
  selectedDisplayCurrency: { type: String, default: 'USD' },
  exchangeRates: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['add-dish', 'add-product', 'search-products'])

const activeMenuTab = ref('dishes')
const dishSearch = ref('')
const selectedCategory = ref(null)
const productSearch = ref('')
const showImages = ref(true)
const viewByCategories = ref(true)

const normalizedCategories = computed(() => {
  return (props.dishCategories || []).map((cat) => {
    if (typeof cat === 'string') {
      return { name: cat, photo_url: null, count: 0 }
    }
    return cat
  })
})

const showCategoryGrid = computed(() => {
  return (
    activeMenuTab.value === 'dishes' &&
    viewByCategories.value &&
    !selectedCategory.value &&
    !dishSearch.value.trim() &&
    normalizedCategories.value.length > 0
  )
})

const filteredDishes = computed(() => {
  let list = props.dishes || []
  if (selectedCategory.value) {
    const selectedObj = normalizedCategories.value.find((c) => c.name === selectedCategory.value)
    if (selectedObj?.isOther) {
      const mainCategoryIds = new Set(
        normalizedCategories.value.filter((c) => !c.isOther && c.id).map((c) => Number(c.id))
      )
      const mainCategoryNames = new Set(
        normalizedCategories.value.filter((c) => !c.isOther).map((c) => c.name)
      )
      list = list.filter(
        (d) => !mainCategoryIds.has(Number(d.category_id)) && !mainCategoryNames.has(d.category?.name)
      )
    } else if (selectedObj?.id) {
      list = list.filter(
        (d) => Number(d.category_id) === Number(selectedObj.id) || d.category?.name === selectedCategory.value
      )
    } else {
      list = list.filter((d) => d.category?.name === selectedCategory.value)
    }
  }
  const q = dishSearch.value.trim().toLowerCase()
  if (q) {
    list = list.filter((d) =>
      String(d.name || '').toLowerCase().includes(q) ||
      String(d.category?.name || '').toLowerCase().includes(q)
    )
  }
  return list
})

const commercialProducts = computed(() => {
  return (props.products || []).filter((p) => !p.item_type || p.item_type === 'product')
})

const getDishDisplayPrice = (dish) => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  const usd = Number(dish?.designated_price || 0)
  if (curr === 'COP') {
    // Respetar el precio COP asignado directamente al plato sin redondear
    if (Number(dish?.designated_price_cop) > 0) return Number(dish.designated_price_cop)
    const rate = Number(props.exchangeRates?.USD?.COP || props.exchangeRates?.USD_COP || props.exchangeRates?.cop || 4000)
    return roundUpToNearestHundred(usd * (rate > 0 ? rate : 4000))
  }
  if (curr === 'BS') {
    const rate = Number(props.exchangeRates?.USD?.BS || props.exchangeRates?.USD_BS || props.exchangeRates?.bs || 1)
    return usd * (rate > 0 ? rate : 1)
  }
  return usd
}

const getProductDisplayPrice = (product) => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  if (curr === 'COP') {
    // Productos individuales en COP siempre redondeados hacia arriba a la centena (100)
    if (Number(product?.price_cop) > 0) return roundUpToNearestHundred(Number(product.price_cop))
    const usd = Number(product?.sale_price ?? product?.price ?? 0)
    const rate = Number(props.exchangeRates?.USD?.COP || props.exchangeRates?.USD_COP || props.exchangeRates?.cop || 4000)
    return roundUpToNearestHundred(usd * (rate > 0 ? rate : 4000))
  }
  if (curr === 'BS') {
    if (Number(product?.price_bs) > 0) return Number(product.price_bs)
    const usd = Number(product?.sale_price ?? product?.price ?? 0)
    const rate = Number(props.exchangeRates?.USD?.BS || props.exchangeRates?.USD_BS || props.exchangeRates?.bs || 1)
    return usd * (rate > 0 ? rate : 1)
  }
  return Number(product?.sale_price ?? product?.price ?? 0)
}

const handleProductSearchInput = (val) => {
  productSearch.value = val
  emit('search-products', val)
}

const openCategory = (catName) => {
  selectedCategory.value = catName
}

const backToCategories = () => {
  selectedCategory.value = null
}
</script>

<template>
  <VCard variant="outlined" class="rounded-lg h-100 d-flex flex-column">
    <VCardText class="pa-3 pb-0">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <VTabs v-model="activeMenuTab" color="primary" density="compact">
          <VTab value="dishes" class="font-weight-bold">
            <VIcon start icon="tabler-tools-kitchen-2" size="18" />
            Menú de Platos
          </VTab>
          <VTab value="products" class="font-weight-bold">
            <VIcon start icon="tabler-bottle" size="18" />
            Bebidas y Productos
          </VTab>
        </VTabs>

        <div class="d-flex align-center gap-2">
          <VBtnToggle
            v-if="activeMenuTab === 'dishes'"
            v-model="viewByCategories"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
          >
            <VBtn :value="true" size="x-small" prepend-icon="tabler-layout-grid">
              Categorías
            </VBtn>
            <VBtn
              :value="false"
              size="x-small"
              prepend-icon="tabler-list"
              @click="selectedCategory = null"
            >
              Todos
            </VBtn>
          </VBtnToggle>

          <VBtn
            size="x-small"
            :color="showImages ? 'primary' : 'secondary'"
            :variant="showImages ? 'tonal' : 'outlined'"
            :prepend-icon="showImages ? 'tabler-photo' : 'tabler-photo-off'"
            @click="showImages = !showImages"
          >
            {{ showImages ? 'Con fotos' : 'Compacto' }}
          </VBtn>
        </div>
      </div>

      <!-- Buscador y Navegación de Platos -->
      <template v-if="activeMenuTab === 'dishes'">
        <VTextField
          v-model="dishSearch"
          placeholder="Buscar plato o categoría..."
          prepend-inner-icon="tabler-search"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          class="mb-3"
        />

        <!-- Breadcrumb cuando se entra a una categoría en modo Por Categorías -->
        <div
          v-if="viewByCategories && selectedCategory && !dishSearch.trim()"
          class="d-flex align-center gap-2 mb-3"
        >
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-arrow-left"
            @click="backToCategories"
          >
            Categorías
          </VBtn>
          <VIcon icon="tabler-chevron-right" size="16" color="disabled" />
          <VChip color="primary" variant="flat" size="small" class="font-weight-bold">
            {{ selectedCategory }} ({{ filteredDishes.length }})
          </VChip>
        </div>

        <!-- Barra de chips rápida cuando está en modo Todos -->
        <div
          v-else-if="!viewByCategories"
          class="d-flex align-center gap-2 flex-wrap mb-3"
        >
          <VChip
            size="small"
            :color="selectedCategory === null ? 'primary' : 'default'"
            :variant="selectedCategory === null ? 'flat' : 'tonal'"
            class="cursor-pointer font-weight-bold"
            @click="selectedCategory = null"
          >
            Todos ({{ dishes.length }})
          </VChip>
          <VChip
            v-for="cat in normalizedCategories"
            :key="cat.name"
            size="small"
            :color="selectedCategory === cat.name ? 'primary' : 'default'"
            :variant="selectedCategory === cat.name ? 'flat' : 'tonal'"
            class="cursor-pointer font-weight-bold"
            @click="selectedCategory = selectedCategory === cat.name ? null : cat.name"
          >
            {{ cat.name }} ({{ cat.count }})
          </VChip>
        </div>
      </template>

      <!-- Buscador de Productos Comerciales -->
      <template v-else>
        <VTextField
          :model-value="productSearch"
          placeholder="Buscar bebida o producto comercial..."
          prepend-inner-icon="tabler-search"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          class="mb-3"
          @update:model-value="handleProductSearchInput"
        />
      </template>
    </VCardText>

    <VDivider />

    <VCardText class="pa-3 flex-grow-1" style="max-height: 68vh; overflow-y: auto;">
      <!-- Grid de Platos / Categorías -->
      <template v-if="activeMenuTab === 'dishes'">
        <VRow v-if="dishesLoading" dense>
          <VCol v-for="n in 8" :key="n" cols="6" sm="4" lg="3">
            <VSkeletonLoader type="card" />
          </VCol>
        </VRow>

        <!-- Vista de Tarjetas de Categorías con Imágenes -->
        <VRow v-else-if="showCategoryGrid" dense>
          <VCol
            v-for="cat in normalizedCategories"
            :key="cat.name"
            cols="6"
            sm="4"
            lg="3"
          >
            <VCard
              variant="outlined"
              class="dish-card h-100 d-flex flex-column justify-space-between rounded-lg overflow-hidden"
              @click="openCategory(cat.name)"
            >
              <div v-if="showImages">
                <VImg
                  v-if="cat.photo_url"
                  :src="cat.photo_url"
                  height="110"
                  cover
                />
                <div
                  v-else
                  class="d-flex align-center justify-center bg-grey-lighten-4"
                  style="height: 110px;"
                >
                  <VIcon icon="tabler-category" size="38" color="primary" />
                </div>
              </div>

              <VCardText class="pa-3 d-flex align-center justify-space-between gap-2">
                <div class="text-subtitle-2 font-weight-black text-high-emphasis text-truncate">
                  {{ cat.name }}
                </div>
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ cat.count }}
                </VChip>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Estado Vacío -->
        <div
          v-else-if="filteredDishes.length === 0"
          class="py-10 d-flex flex-column align-center justify-center text-center"
        >
          <VAvatar size="56" color="primary" variant="tonal" class="mb-3">
            <VIcon icon="tabler-tools-kitchen-off" size="28" />
          </VAvatar>
          <h4 class="text-subtitle-1 font-weight-bold mb-1">Sin platos disponibles</h4>
          <p class="text-caption text-medium-emphasis mb-0">
            No hay platos que coincidan con el filtro seleccionado.
          </p>
        </div>

        <!-- Grid de Platos -->
        <VRow v-else dense>
          <VCol
            v-for="dish in filteredDishes"
            :key="dish.id"
            cols="6"
            sm="4"
            lg="3"
          >
            <VCard
              variant="outlined"
              class="dish-card h-100 d-flex flex-column justify-space-between rounded-lg overflow-hidden"
              @click="emit('add-dish', { dish, quantity: 1 })"
            >
              <div v-if="showImages">
                <VImg
                  v-if="dish.photo_url"
                  :src="dish.photo_url"
                  height="105"
                  cover
                />
                <div
                  v-else
                  class="d-flex align-center justify-center bg-grey-lighten-4"
                  style="height: 105px;"
                >
                  <VIcon icon="tabler-tools-kitchen-2" size="34" color="primary" />
                </div>
              </div>

              <VCardText class="pa-3 pb-2">
                <div class="d-flex align-center justify-space-between gap-1 mb-1 flex-wrap">
                  <VChip
                    v-if="dish.category?.name && dish.category?.type !== 'inventory'"
                    size="x-small"
                    color="secondary"
                    variant="tonal"
                    class="text-truncate"
                  >
                    {{ dish.category.name }}
                  </VChip>
                  <VChip
                    v-if="dish.modifier_groups?.length"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                  >
                    Sabores/Extras
                  </VChip>
                </div>

                <div class="text-subtitle-2 font-weight-bold text-high-emphasis mt-1">
                  {{ dish.name }}
                </div>
              </VCardText>

              <VCardActions class="pa-3 pt-0 d-flex align-center justify-space-between">
                <span class="text-subtitle-1 font-weight-black text-primary">
                  {{ formatCurrency(getDishDisplayPrice(dish), selectedDisplayCurrency) }}
                </span>
                <VBtn
                  icon="tabler-plus"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                />
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
      </template>

      <!-- Grid de Productos / Bebidas -->
      <template v-else>
        <VRow v-if="productsLoading" dense>
          <VCol v-for="n in 8" :key="n" cols="6" sm="4" lg="3">
            <VSkeletonLoader type="card" />
          </VCol>
        </VRow>

        <div
          v-else-if="commercialProducts.length === 0"
          class="py-10 d-flex flex-column align-center justify-center text-center"
        >
          <VAvatar size="56" color="secondary" variant="tonal" class="mb-3">
            <VIcon icon="tabler-package-off" size="28" />
          </VAvatar>
          <h4 class="text-subtitle-1 font-weight-bold mb-1">Sin productos</h4>
          <p class="text-caption text-medium-emphasis mb-0">
            No se encontraron bebidas o productos con ese criterio.
          </p>
        </div>

        <VRow v-else dense>
          <VCol
            v-for="prod in commercialProducts"
            :key="prod.id"
            cols="6"
            sm="4"
            lg="3"
          >
            <VCard
              variant="outlined"
              class="dish-card h-100 d-flex flex-column justify-space-between rounded-lg overflow-hidden"
              :disabled="Number(prod.valid_stock_sum ?? 0) <= 0"
              @click="emit('add-product', { productId: prod.id, quantity: 1, productData: prod })"
            >
              <div v-if="showImages">
                <VImg
                  v-if="prod.photo_url"
                  :src="prod.photo_url"
                  height="105"
                  cover
                />
                <div
                  v-else
                  class="d-flex align-center justify-center bg-grey-lighten-4"
                  style="height: 105px;"
                >
                  <VIcon icon="tabler-bottle" size="34" color="secondary" />
                </div>
              </div>

              <VCardText class="pa-3 pb-2">
                <VChip
                  size="x-small"
                  :color="Number(prod.valid_stock_sum ?? 0) > 0 ? 'success' : 'error'"
                  variant="tonal"
                  class="mb-1"
                >
                  Stock: {{ Number(prod.valid_stock_sum ?? 0) }}
                </VChip>
                <div class="text-subtitle-2 font-weight-bold text-high-emphasis">
                  {{ prod.name }}
                </div>
              </VCardText>

              <VCardActions class="pa-3 pt-0 d-flex align-center justify-space-between">
                <span class="text-subtitle-1 font-weight-black text-primary">
                  {{ formatCurrency(getProductDisplayPrice(prod), selectedDisplayCurrency) }}
                </span>
                <VBtn
                  icon="tabler-plus"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  :disabled="Number(prod.valid_stock_sum ?? 0) <= 0"
                />
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
      </template>
    </VCardText>
  </VCard>
</template>
