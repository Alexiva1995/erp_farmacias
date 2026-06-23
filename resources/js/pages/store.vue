<script setup>
definePage({
  meta: {
    requiresAuth: true,
    // Esto utilizará la plantilla por defecto (default.vue) que contiene el sidebar, header, VApp y todo el sistema.
  },
})

import { ref, onMounted, computed, watch } from 'vue'
import axios from '@/plugins/axios'

// ——— Estado principal ———
const products = ref([])
const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const loading = ref(false)
const orderDialog = ref(false)
const orderSubmitting = ref(false)
const cartDrawer = ref(false)
const quickViewDialog = ref(false)
const selectedProduct = ref(null)
const selectedVariant = ref(null)
const orderSuccess = ref(false)
const lastOrderId = ref(null)

// ——— Carrito ———
const cart = ref([])

const cartTotalItems = computed(() => cart.value.reduce((acc, i) => acc + i.quantity, 0))
const cartTotalPrice = computed(() =>
  cart.value.reduce((acc, i) => {
    const base = Number(i.product.sale_price) || 0
    const mod  = i.variant ? (Number(i.variant.price_modifier) || 0) : 0
    return acc + (base + mod) * i.quantity
  }, 0)
)

// ——— Formulario de orden ———
const orderForm = ref({
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  shipping_address: '',
  notes: '',
})
const orderFormValid = computed(() =>
  orderForm.value.customer_name.trim() &&
  orderForm.value.customer_phone.trim()
)

// ——— Fetch datos ———
const fetchCategories = async () => {
  try {
    const { data } = await axios.get('/public/ecommerce/categories')
    if (data.success) categories.value = data.data
  } catch (e) {
    console.error('Error al obtener categorías:', e)
  }
}

const fetchProducts = async () => {
  loading.value = true
  try {
    const params = {}
    if (selectedCategory.value) params.category = selectedCategory.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const { data } = await axios.get('/public/ecommerce/products', { params })
    if (data.success) {
      products.value = Array.isArray(data.data?.data) ? data.data.data : (Array.isArray(data.data) ? data.data : [])
    }
  } catch (e) {
    console.error('Error al obtener productos:', e)
  } finally {
    loading.value = false
  }
}

// Búsqueda con debounce
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchProducts, 400)
})

const selectCategory = (slug) => {
  selectedCategory.value = selectedCategory.value === slug ? null : slug
  fetchProducts()
}

// ——— Carrito ———
const addToCart = (product, variant = null) => {
  const key = variant ? `${product.id}_${variant.id}` : `${product.id}`
  const existing = cart.value.find(i => i.cartKey === key)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({ cartKey: key, product, variant, quantity: 1 })
  }
  cartDrawer.value = true
}

const updateQty = (key, delta) => {
  const item = cart.value.find(i => i.cartKey === key)
  if (!item) return
  item.quantity += delta
  if (item.quantity <= 0) cart.value = cart.value.filter(i => i.cartKey !== key)
}

const removeFromCart = (key) => {
  cart.value = cart.value.filter(i => i.cartKey !== key)
}

const clearCart = () => { cart.value = [] }

// ——— Vista rápida ———
const openQuickView = (product) => {
  selectedProduct.value = product
  selectedVariant.value = product.variants?.length ? product.variants[0] : null
  quickViewDialog.value = true
}

const quickAddToCart = () => {
  if (selectedProduct.value) {
    addToCart(selectedProduct.value, selectedVariant.value)
    quickViewDialog.value = false
  }
}

// ——— Precio con variante ———
const productPrice = (product, variant = null) => {
  const base = Number(product.sale_price) || 0
  const mod  = variant ? (Number(variant.price_modifier) || 0) : 0
  return (base + mod).toFixed(2)
}

const formatPrice = (n) => `$${Number(n).toFixed(2)}`

// ——— Checkout ———
const submitOrder = async () => {
  if (!cart.value.length || !orderFormValid.value) return
  orderSubmitting.value = true
  try {
    const payload = {
      ...orderForm.value,
      items: cart.value.map(i => ({
        product_id: i.product.id,
        variant_id: i.variant ? i.variant.id : null,
        quantity: i.quantity,
      })),
    }
    const { data } = await axios.post('/public/ecommerce/checkout', payload)
    if (data.success) {
      lastOrderId.value = data.order_id
      orderSuccess.value = true
      clearCart()
      orderDialog.value = false
      cartDrawer.value = false
      orderForm.value = { customer_name: '', customer_email: '', customer_phone: '', shipping_address: '', notes: '' }
      await fetchProducts()
    }
  } catch (e) {
    console.error('Error en checkout:', e)
  } finally {
    orderSubmitting.value = false
  }
}

// ——— Íconos de categoría ———
const categoryIcons = {
  'anillos': 'ri-vip-crown-line',
  'pulseras': 'ri-hand-coin-line',
  'aretes': 'ri-star-smile-line',
  'skin-care': 'ri-leaf-line',
  'maquillaje': 'ri-palette-line',
  'cabello': 'ri-scissors-line',
  'default': 'ri-shopping-bag-line',
}
const getCategoryIcon = (slug) => {
  if (!slug) return categoryIcons.default
  return categoryIcons[slug] || categoryIcons.default
}

const categoryGradients = [
  'linear-gradient(135deg, #ff9a9e, #fad0c4)',
  'linear-gradient(135deg, #a18cd1, #fbc2eb)',
  'linear-gradient(135deg, #fda085, #f6d365)',
  'linear-gradient(135deg, #84fab0, #8fd3f4)',
  'linear-gradient(135deg, #f093fb, #f5576c)',
  'linear-gradient(135deg, #4facfe, #00f2fe)',
]

onMounted(() => {
  fetchCategories()
  fetchProducts()
})
</script>

<template>
  <VContainer fluid class="tova-admin-store-container">
    <!-- Header Integrado en el Sistema -->
    <VRow class="mb-6" align="center" justify="space-between">
      <VCol cols="12" md="6">
        <h1 class="text-h4 font-weight-bold tova-title-accent">TOVA Beauty & Gems</h1>
        <p class="text-subtitle-1 text-muted">Vista del catálogo interno para administración y personal</p>
      </VCol>
      <VCol cols="12" md="4" class="d-flex justify-md-end align-center gap-4">
        <!-- Buscador integrado de Vuetify -->
        <VTextField
          v-model="searchQuery"
          prepend-inner-icon="ri-search-line"
          label="Buscar productos..."
          variant="outlined"
          density="compact"
          hide-details
          rounded
          class="mr-3"
        />
        <!-- Carrito integrado en Vuetify -->
        <VBtn
          color="primary"
          icon
          variant="flat"
          class="position-relative"
          @click="cartDrawer = true"
        >
          <VIcon icon="ri-shopping-bag-3-line" />
          <VBadge
            v-if="cartTotalItems"
            color="error"
            :content="cartTotalItems"
            floating
            location="top right"
          />
        </VBtn>
      </VCol>
    </VRow>

    <!-- CATEGORÍAS INTEGRADAS -->
    <VRow class="mb-6">
      <VCol cols="12">
        <h3 class="text-h6 font-weight-bold mb-3">Categorías</h3>
        <div class="d-flex flex-wrap gap-3">
          <VBtn
            v-for="(cat, i) in categories"
            :key="cat.id"
            :color="selectedCategory === cat.slug ? 'primary' : 'default'"
            :variant="selectedCategory === cat.slug ? 'flat' : 'outlined'"
            rounded
            class="text-capitalize"
            @click="selectCategory(cat.slug)"
          >
            <VIcon :icon="getCategoryIcon(cat.slug)" class="mr-2" />
            {{ cat.name }}
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- CATÁLOGO -->
    <VRow>
      <VCol cols="12">
        <div class="d-flex align-center justify-between mb-4">
          <span class="text-subtitle-2 font-weight-bold">
            {{ products.length }} productos encontrados
          </span>
          <VBtn
            v-if="selectedCategory"
            variant="text"
            color="primary"
            density="compact"
            class="text-capitalize"
            @click="selectedCategory = null; fetchProducts()"
          >
            Limpiar Filtros
          </VBtn>
        </div>
      </VCol>

      <!-- Skeleton Loading -->
      <template v-if="loading">
        <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="4" lg="3">
          <VSkeletonLoader type="card, article" />
        </VCol>
      </template>

      <!-- Catálogo de Productos -->
      <template v-else-if="products.length">
        <VCol
          v-for="product in products"
          :key="product.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            class="product-admin-card h-100 d-flex flex-column rounded-xl"
            variant="outlined"
            @click="openQuickView(product)"
          >
            <div class="product-img-container bg-grey-lighten-4 position-relative">
              <VImg
                v-if="product.image_url"
                :src="product.image_url"
                height="220"
                cover
              />
              <div v-else class="d-flex align-center justify-center" style="height: 220px;">
                <VIcon :icon="getCategoryIcon(product.category?.slug)" size="64" color="primary" class="opacity-30" />
              </div>
              <VChip
                v-if="product.category?.name"
                color="primary"
                size="small"
                variant="flat"
                class="position-absolute m-3"
                style="top: 8px; left: 8px;"
              >
                {{ product.category.name }}
              </VChip>
            </div>

            <VCardText class="flex-grow-1 d-flex flex-column p-4">
              <span class="text-caption text-uppercase tracking-wider text-primary font-weight-bold mb-1">
                {{ product.brand || 'TOVA Collection' }}
              </span>
              <h3 class="text-subtitle-1 font-weight-bold text-truncate mb-2">{{ product.name }}</h3>
              <div class="mt-auto d-flex align-center justify-space-between">
                <span class="text-h6 font-weight-black text-primary">{{ formatPrice(product.sale_price) }}</span>
                <VBtn
                  color="primary"
                  icon="ri-add-line"
                  size="small"
                  variant="flat"
                  @click.stop="addToCart(product)"
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </template>

      <!-- Empty State -->
      <VCol v-else cols="12" class="text-center py-12">
        <VIcon icon="ri-search-line" size="64" class="text-muted mb-4" />
        <h3 class="text-h5 font-weight-bold">Sin resultados</h3>
        <p class="text-muted mb-6">Intenta con otra búsqueda o categoría.</p>
        <VBtn color="primary" rounded @click="searchQuery = ''; selectedCategory = null; fetchProducts()">
          Ver todo
        </VBtn>
      </VCol>
    </VRow>

    <!-- ====== DRAWER DEL CARRITO ====== -->
    <VNavigationDrawer
      v-model="cartDrawer"
      location="right"
      temporary
      width="400"
      class="rounded-left-xl"
    >
      <div class="d-flex flex-column h-100">
        <div class="d-flex align-center justify-space-between p-4 border-bottom">
          <h3 class="text-h6 font-weight-bold d-flex align-center gap-2">
            Carrito
            <VChip color="primary" size="small">{{ cartTotalItems }}</VChip>
          </h3>
          <VBtn icon="ri-close-line" variant="text" @click="cartDrawer = false" />
        </div>

        <!-- Items del Carrito -->
        <div class="flex-grow-1 overflow-y-auto p-4 d-flex flex-column gap-3">
          <template v-if="cart.length">
            <div
              v-for="item in cart"
              :key="item.cartKey"
              class="d-flex align-center gap-3 p-3 bg-grey-lighten-4 rounded-lg position-relative"
            >
              <VAvatar size="60" rounded="lg" class="bg-white border">
                <VImg v-if="item.product.image_url" :src="item.product.image_url" cover />
                <VIcon v-else icon="ri-sparkling-fill" color="primary" />
              </VAvatar>

              <div class="flex-grow-1 min-width-0">
                <p class="text-subtitle-2 font-weight-bold text-truncate mb-0">{{ item.product.name }}</p>
                <p v-if="item.variant" class="text-caption text-muted mb-0">{{ item.variant.attribute_value }}</p>
                <p class="text-subtitle-2 text-primary font-weight-bold mb-0">
                  {{ formatPrice(productPrice(item.product, item.variant)) }}
                </p>
              </div>

              <div class="d-flex align-center bg-white rounded-pill border p-1 gap-2">
                <VBtn icon="ri-subtract-line" variant="text" density="compact" size="small" @click="updateQty(item.cartKey, -1)" />
                <span class="text-subtitle-2 font-weight-bold px-1">{{ item.quantity }}</span>
                <VBtn icon="ri-add-line" variant="text" density="compact" size="small" @click="updateQty(item.cartKey, 1)" />
              </div>

              <VBtn
                icon="ri-delete-bin-line"
                variant="text"
                color="error"
                density="compact"
                size="small"
                class="position-absolute"
                style="top: 4px; right: 4px;"
                @click="removeFromCart(item.cartKey)"
              />
            </div>
          </template>
          <div v-else class="d-flex flex-column align-center justify-center h-100 text-muted">
            <VIcon icon="ri-shopping-bag-3-line" size="64" class="mb-3" />
            <p class="text-body-1">Tu carrito está vacío</p>
          </div>
        </div>

        <!-- Footer del Carrito -->
        <div v-if="cart.length" class="p-4 border-top bg-white">
          <div class="d-flex align-center justify-space-between mb-4">
            <span class="text-subtitle-1">Subtotal</span>
            <span class="text-h5 font-weight-bold text-primary">{{ formatPrice(cartTotalPrice) }}</span>
          </div>
          <VBtn
            color="primary"
            block
            rounded="xl"
            size="large"
            class="mb-2"
            @click="cartDrawer = false; orderDialog = true"
          >
            Proceder al Pago
          </VBtn>
          <VBtn variant="text" color="error" block rounded="xl" @click="clearCart">
            Vaciar Carrito
          </VBtn>
        </div>
      </div>
    </VNavigationDrawer>

    <!-- ====== VISTA RÁPIDA DIALOG ====== -->
    <VDialog v-model="quickViewDialog" max-width="800">
      <VCard v-if="selectedProduct" class="rounded-xl overflow-hidden">
        <div class="position-absolute" style="top: 16px; right: 16px; z-index: 10;">
          <VBtn icon="ri-close-line" variant="tonal" color="white" @click="quickViewDialog = false" />
        </div>
        <VRow no-gutters>
          <VCol cols="12" md="6" class="bg-grey-lighten-4">
            <VImg
              v-if="selectedProduct.image_url"
              :src="selectedProduct.image_url"
              height="100%"
              min-height="350"
              cover
            />
            <div v-else class="d-flex align-center justify-center h-100 min-vh-50" style="min-height: 350px;">
              <VIcon icon="ri-sparkling-line" size="120" color="primary" class="opacity-20" />
            </div>
          </VCol>
          <VCol cols="12" md="6" class="p-6 d-flex flex-column justify-center">
            <span class="text-caption text-uppercase tracking-wider text-primary font-weight-bold mb-2">
              {{ selectedProduct.category?.name || 'TOVA Collection' }}
            </span>
            <h2 class="text-h5 font-weight-bold mb-3">{{ selectedProduct.name }}</h2>
            <p class="text-body-2 text-muted mb-4">
              {{ selectedProduct.description || 'Producto de alta calidad de la colección TOVA.' }}
            </p>
            <h3 class="text-h4 font-weight-black text-primary mb-5">
              {{ formatPrice(productPrice(selectedProduct, selectedVariant)) }}
            </h3>

            <!-- Variantes -->
            <div v-if="selectedProduct.variants?.length" class="mb-6">
              <p class="text-subtitle-2 font-weight-bold mb-2">Selecciona variante:</p>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="v in selectedProduct.variants"
                  :key="v.id"
                  :color="selectedVariant?.id === v.id ? 'primary' : 'default'"
                  :variant="selectedVariant?.id === v.id ? 'flat' : 'outlined'"
                  class="cursor-pointer"
                  @click="selectedVariant = v"
                >
                  {{ v.attribute_value }}
                  <span v-if="Number(v.price_modifier) > 0" class="ml-1 opacity-70">
                    +{{ formatPrice(v.price_modifier) }}
                  </span>
                </VChip>
              </div>
            </div>

            <VBtn
              color="primary"
              block
              size="large"
              rounded="xl"
              prepend-icon="ri-shopping-bag-3-line"
              @click="quickAddToCart"
            >
              Añadir al Carrito
            </VBtn>
          </VCol>
        </VRow>
      </VCard>
    </VDialog>

    <!-- ====== CHECKOUT DIALOG ====== -->
    <VDialog v-model="orderDialog" max-width="600">
      <VCard class="rounded-xl p-6">
        <div class="d-flex align-center justify-space-between mb-4">
          <h2 class="text-h5 font-weight-bold">Finalizar Compra</h2>
          <VBtn icon="ri-close-line" variant="text" @click="orderDialog = false" />
        </div>

        <!-- Resumen del Pedido -->
        <VCard variant="outlined" class="rounded-lg mb-6 p-4 bg-grey-lighten-5">
          <h3 class="text-subtitle-2 font-weight-bold mb-3 text-uppercase tracking-wider text-muted">Resumen del Pedido</h3>
          <div
            v-for="item in cart"
            :key="item.cartKey"
            class="d-flex justify-space-between align-center py-2 border-bottom text-body-2"
          >
            <span>
              {{ item.product.name }}
              <span v-if="item.variant" class="text-caption text-muted">({{ item.variant.attribute_value }})</span>
            </span>
            <span class="font-weight-bold text-primary">x{{ item.quantity }}</span>
            <span class="font-weight-bold">{{ formatPrice(productPrice(item.product, item.variant) * item.quantity) }}</span>
          </div>
          <div class="d-flex justify-space-between align-center pt-3 font-weight-bold">
            <span>Total</span>
            <span class="text-h6 text-primary">{{ formatPrice(cartTotalPrice) }}</span>
          </div>
        </VCard>

        <!-- Formulario -->
        <VForm>
          <h3 class="text-subtitle-2 font-weight-bold mb-3 text-uppercase tracking-wider text-muted">Tus Datos</h3>
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField v-model="orderForm.customer_name" label="Nombre Completo *" variant="outlined" density="comfortable" required />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="orderForm.customer_phone" label="Teléfono *" variant="outlined" density="comfortable" required />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="orderForm.customer_email" label="Email" type="email" variant="outlined" density="comfortable" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="orderForm.shipping_address" label="Dirección de Entrega" variant="outlined" density="comfortable" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="orderForm.notes" label="Notas adicionales" rows="2" variant="outlined" density="comfortable" />
            </VCol>
          </VRow>
        </VForm>

        <VBtn
          color="primary"
          block
          size="large"
          rounded="xl"
          class="mt-4"
          :disabled="!orderFormValid || orderSubmitting"
          @click="submitOrder"
        >
          <span v-if="orderSubmitting">Procesando...</span>
          <span v-else>Confirmar Pedido · {{ formatPrice(cartTotalPrice) }}</span>
        </VBtn>
      </VCard>
    </VDialog>

    <!-- ====== SUCCESS SNACKBAR ====== -->
    <VSnackbar
      v-model="orderSuccess"
      color="success"
      location="bottom"
      timeout="5000"
      class="rounded-xl"
    >
      <div class="d-flex align-center gap-2">
        <VIcon icon="ri-checkbox-circle-line" size="24" />
        <div>
          <p class="font-weight-bold mb-0">¡Pedido confirmado!</p>
          <p class="text-caption mb-0">Orden #{{ lastOrderId }} registrada correctamente.</p>
        </div>
      </div>
      <template v-slot:actions>
        <VBtn variant="text" icon="ri-close-line" @click="orderSuccess = false" />
      </template>
    </VSnackbar>
  </VContainer>
</template>

<style scoped>
.tova-title-accent {
  background: linear-gradient(135deg, #e91e8c, #ff6bb5, #f5c842);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.product-admin-card {
  transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
  cursor: pointer;
  background-color: white;
}

.product-admin-card:hover {
  transform: translateY(-6px);
  border-color: #e91e8c !important;
  box-shadow: 0 12px 24px rgba(233, 30, 140, 0.15);
}

.product-img-container {
  overflow: hidden;
  border-top-left-radius: inherit;
  border-top-right-radius: inherit;
}

.product-img-container .v-img {
  transition: transform 0.5s ease;
}

.product-admin-card:hover .product-img-container .v-img {
  transform: scale(1.06);
}
</style>
