<script setup>
import { computed, ref } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'

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

const searchQuery = ref('')
const selectedCategory = ref(null)
const showImages = ref(true)
const viewByCategories = ref(true)

const getDishDisplayPrice = (dish) => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  const usd = Number(dish?.designated_price || 0)
  if (curr === 'COP') {
    if (Number(dish?.designated_price_cop) > 0) return Number(dish.designated_price_cop)
    const rate = Number(props.exchangeRates?.USD_COP || props.exchangeRates?.cop || 4000)
    return Math.ceil((usd * (rate > 0 ? rate : 4000)) / 100) * 100
  }
  if (curr === 'BS') {
    const rate = Number(props.exchangeRates?.USD_BS || props.exchangeRates?.bs || 1)
    return usd * (rate > 0 ? rate : 1)
  }
  return usd
}

const getProductDisplayPrice = (product) => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  if (curr === 'COP') return Number(product?.price_cop ?? 0)
  if (curr === 'BS') return Number(product?.price_bs ?? 0)
  return Number(product?.sale_price ?? product?.price ?? 0)
}

// Productos comerciales vendibles (excluyendo insumos/sub-recetas internas)
const sellableProducts = computed(() => {
  return (props.products || []).filter(
    (p) => (!p.item_type || p.item_type === 'product') && !p.is_sub_recipe
  )
})

const baseMenuCategoryMap = computed(() => {
  const map = new Map()
  ;(props.dishCategories || []).forEach((cat) => {
    if (typeof cat === 'string') {
      map.set(cat, { id: cat, name: cat, photo_url: null, count: 0, isOther: false })
    } else if (!cat.isOther) {
      map.set(cat.name, { ...cat })
    }
  })
  return map
})

// Unificar platos y productos comerciales en una sola lista de ítems vendibles
const unifiedMenuItems = computed(() => {
  const mainCatNames = new Set(baseMenuCategoryMap.value.keys())

  const dishItems = (props.dishes || []).map((dish) => {
    const catName = dish.category?.name
    const assignedCategory = catName && mainCatNames.has(catName) ? catName : 'Otros Platos'
    return {
      uid: `dish-${dish.id}`,
      itemType: 'dish',
      id: dish.id,
      name: dish.name,
      photo_url: dish.photo_url || null,
      categoryName: assignedCategory,
      showCategoryChip: Boolean(catName && dish.category?.type !== 'inventory'),
      displayCategoryChip: catName,
      hasModifiers: Array.isArray(dish.modifier_groups) && dish.modifier_groups.length > 0,
      displayPrice: getDishDisplayPrice(dish),
      stock: null,
      outOfStock: false,
      raw: dish,
    }
  })

  const productItems = sellableProducts.value.map((prod) => {
    const catName = prod.category?.name
    const assignedCategory = catName && mainCatNames.has(catName) ? catName : 'Bebidas y Productos'
    const stockVal = Number(prod.valid_stock_sum ?? 0)
    return {
      uid: `prod-${prod.id}`,
      itemType: 'product',
      id: prod.id,
      name: prod.name,
      photo_url: prod.photo_url || null,
      categoryName: assignedCategory,
      showCategoryChip: true,
      displayCategoryChip: assignedCategory,
      hasModifiers: false,
      displayPrice: getProductDisplayPrice(prod),
      stock: stockVal,
      outOfStock: stockVal <= 0,
      raw: prod,
    }
  })

  return [...dishItems, ...productItems]
})

// Categorías unificadas con conteo real e imagen (propia o fallback del primer ítem)
const unifiedCategories = computed(() => {
  const counts = new Map()
  const fallbackPhotos = new Map()

  unifiedMenuItems.value.forEach((item) => {
    counts.set(item.categoryName, (counts.get(item.categoryName) || 0) + 1)
    if (!fallbackPhotos.has(item.categoryName) && item.photo_url) {
      fallbackPhotos.set(item.categoryName, item.photo_url)
    }
  })

  const result = []
  baseMenuCategoryMap.value.forEach((cat, name) => {
    result.push({
      id: cat.id,
      name,
      photo_url: cat.photo_url || fallbackPhotos.get(name) || null,
      count: counts.get(name) || 0,
      isOther: false,
    })
  })

  if ((counts.get('Bebidas y Productos') || 0) > 0 && !baseMenuCategoryMap.value.has('Bebidas y Productos')) {
    result.push({
      id: 'commercial-products',
      name: 'Bebidas y Productos',
      photo_url: fallbackPhotos.get('Bebidas y Productos') || null,
      count: counts.get('Bebidas y Productos'),
      isOther: true,
    })
  }

  if ((counts.get('Otros Platos') || 0) > 0) {
    result.push({
      id: 'other-dishes',
      name: 'Otros Platos',
      photo_url: fallbackPhotos.get('Otros Platos') || null,
      count: counts.get('Otros Platos'),
      isOther: true,
    })
  }

  return result
})

const showCategoryGrid = computed(() => {
  return (
    viewByCategories.value &&
    !selectedCategory.value &&
    !searchQuery.value.trim() &&
    unifiedCategories.value.length > 0
  )
})

const filteredItems = computed(() => {
  let list = unifiedMenuItems.value
  if (selectedCategory.value) {
    list = list.filter((item) => item.categoryName === selectedCategory.value)
  }
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter((item) =>
      String(item.name || '').toLowerCase().includes(q) ||
      String(item.categoryName || '').toLowerCase().includes(q)
    )
  }
  return list
})

const isLoading = computed(() => props.dishesLoading && unifiedMenuItems.value.length === 0)

const handleSearchInput = (val) => {
  searchQuery.value = val || ''
  emit('search-products', searchQuery.value)
}

const handleItemClick = (item) => {
  if (item.outOfStock) return
  if (item.itemType === 'dish') {
    emit('add-dish', { dish: item.raw, quantity: 1 })
  } else {
    emit('add-product', { productId: item.id, quantity: 1, productData: item.raw })
  }
}
</script>

<template>
  <VCard variant="outlined" class="rounded-lg h-100 d-flex flex-column">
    <VCardText class="pa-3 pb-0">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-tools-kitchen-2" color="primary" size="20" />
          <span class="text-subtitle-1 font-weight-black">Menú y Productos</span>
          <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
            {{ unifiedMenuItems.length }}
          </VChip>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtnToggle
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

      <VTextField
        :model-value="searchQuery"
        placeholder="Buscar plato, bebida o categoría..."
        prepend-inner-icon="tabler-search"
        clearable
        density="compact"
        variant="outlined"
        hide-details
        class="mb-3"
        @update:model-value="handleSearchInput"
      />

      <!-- Breadcrumb cuando se entra a una categoría en modo Por Categorías -->
      <div
        v-if="viewByCategories && selectedCategory && !searchQuery.trim()"
        class="d-flex align-center gap-2 mb-3"
      >
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-arrow-left"
          @click="selectedCategory = null"
        >
          Categorías
        </VBtn>
        <VIcon icon="tabler-chevron-right" size="16" color="disabled" />
        <VChip color="primary" variant="flat" size="small" class="font-weight-bold">
          {{ selectedCategory }} ({{ filteredItems.length }})
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
          Todos ({{ unifiedMenuItems.length }})
        </VChip>
        <VChip
          v-for="cat in unifiedCategories"
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
    </VCardText>

    <VDivider />

    <VCardText class="pa-3 flex-grow-1" style="max-height: 68vh; overflow-y: auto;">
      <VRow v-if="isLoading" dense>
        <VCol v-for="n in 8" :key="n" cols="6" sm="4" lg="3">
          <VSkeletonLoader type="card" />
        </VCol>
      </VRow>

      <!-- Vista de Tarjetas de Categorías Unificadas con Imágenes -->
      <VRow v-else-if="showCategoryGrid" dense>
        <VCol
          v-for="cat in unifiedCategories"
          :key="cat.name"
          cols="6"
          sm="4"
          lg="3"
        >
          <VCard
            variant="outlined"
            class="dish-card h-100 d-flex flex-column justify-space-between rounded-lg overflow-hidden"
            @click="selectedCategory = cat.name"
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
                <VIcon
                  :icon="cat.id === 'commercial-products' ? 'tabler-bottle' : 'tabler-category'"
                  size="38"
                  color="primary"
                />
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
        v-else-if="filteredItems.length === 0"
        class="py-10 d-flex flex-column align-center justify-center text-center"
      >
        <VAvatar size="56" color="primary" variant="tonal" class="mb-3">
          <VIcon icon="tabler-tools-kitchen-off" size="28" />
        </VAvatar>
        <h4 class="text-subtitle-1 font-weight-bold mb-1">Sin resultados</h4>
        <p class="text-caption text-medium-emphasis mb-0">
          No hay platos ni productos que coincidan con el filtro seleccionado.
        </p>
      </div>

      <!-- Grid Unificado de Platos y Productos Vendibles -->
      <VRow v-else dense>
        <VCol
          v-for="item in filteredItems"
          :key="item.uid"
          cols="6"
          sm="4"
          lg="3"
        >
          <VCard
            variant="outlined"
            class="dish-card h-100 d-flex flex-column justify-space-between rounded-lg overflow-hidden"
            :disabled="item.outOfStock"
            @click="handleItemClick(item)"
          >
            <div v-if="showImages">
              <VImg
                v-if="item.photo_url"
                :src="item.photo_url"
                height="105"
                cover
              />
              <div
                v-else
                class="d-flex align-center justify-center bg-grey-lighten-4"
                style="height: 105px;"
              >
                <VIcon
                  :icon="item.itemType === 'dish' ? 'tabler-tools-kitchen-2' : 'tabler-bottle'"
                  size="34"
                  :color="item.itemType === 'dish' ? 'primary' : 'secondary'"
                />
              </div>
            </div>

            <VCardText class="pa-3 pb-2">
              <div class="d-flex align-center justify-space-between gap-1 mb-1 flex-wrap">
                <VChip
                  v-if="item.itemType === 'product'"
                  size="x-small"
                  :color="item.stock > 0 ? 'success' : 'error'"
                  variant="tonal"
                >
                  Stock: {{ item.stock }}
                </VChip>
                <VChip
                  v-else-if="item.showCategoryChip"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  class="text-truncate"
                >
                  {{ item.displayCategoryChip }}
                </VChip>
                <VChip
                  v-if="item.hasModifiers"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                >
                  Sabores/Extras
                </VChip>
              </div>

              <div class="text-subtitle-2 font-weight-bold text-high-emphasis mt-1">
                {{ item.name }}
              </div>
            </VCardText>

            <VCardActions class="pa-3 pt-0 d-flex align-center justify-space-between">
              <span class="text-subtitle-1 font-weight-black text-primary">
                {{ formatCurrency(item.displayPrice, selectedDisplayCurrency) }}
              </span>
              <VBtn
                icon="tabler-plus"
                size="x-small"
                color="primary"
                variant="tonal"
                :disabled="item.outOfStock"
              />
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
