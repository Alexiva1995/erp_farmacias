<script setup>
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

import { ref, onMounted, computed, watch } from 'vue'
import axios from '@/plugins/axios'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()

// ——— Estados de Carga de Imágenes ———
const heroImageLoaded = ref(false)
const section2ImageLoaded = ref(false)
const section3ImageLoaded = ref(false)

// ——— Estado principal ———
const products = ref([])
const favoriteProducts = ref([])
const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const showMobileSearch = ref(false)
const loading = ref(false)
const orderDialog = ref(false)
const orderSubmitting = ref(false)
const cartDrawer = ref(false)
const quickViewDialog = ref(false)
const selectedProduct = ref(null)
const selectedVariant = ref(null)
const mobileMenuOpen = ref(false)
const orderSuccess = ref(false)
const lastOrderId = ref(null)

// ——— Paginación del catálogo ———
const currentPage = ref(1)
const lastPage = ref(1)

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
const binanceRate = ref(45.50) // Tasa de cambio Binance de referencia para VES
const copRate = ref(4000.00) // Tasa de cambio COP de referencia
const selectedCurrency = ref(localStorage.getItem('ecommerce_currency') || 'USD') // Moneda seleccionada por el usuario (VES, USD, COP)
const paymentProof = ref(null) // Archivo de comprobante de pago (solo transferencias)
const orderForm = ref({
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  shipping_address: '',
  notes: '',
  payment_method: '', // Métodos: 'pago_movil', 'zelle', 'contraentrega', 'binance', 'transferencia'
  customer_document_type: 'V-',
  customer_document_number: '',
})
const orderFormValid = computed(() =>
  orderForm.value.customer_name.trim() &&
  orderForm.value.customer_phone.trim() &&
  orderForm.value.customer_document_number.trim() &&
  selectedCurrency.value &&
  orderForm.value.payment_method
)

// ——— Búsqueda de cliente por cédula ———
const clientLookupState = ref('idle') // 'idle' | 'searching' | 'found' | 'new' | 'error'
const clientLookupMessage = ref('')
let _documentDebounceTimer = null

/**
 * Busca el cliente primero en la BD (silencioso).
 * Solo si no existe y es venezolano, consulta api.cedula.com.ve mostrando feedback.
 */
const lookupClientByDocument = async (docNumber) => {
  const num = (docNumber || '').trim()
  if (!num || num.length < 4) {
    clientLookupState.value = 'idle'
    clientLookupMessage.value = ''
    return
  }

  // 1️⃣ Buscar en BD — silencioso, sin mensaje visible al usuario
  clientLookupState.value = 'idle'
  clientLookupMessage.value = ''

  try {
    const { data } = await axios.get(`/public/clients/identification/${num}`)
    if (data.success && data.data) {
      const c = data.data
      orderForm.value.customer_name = `${c.name || ''} ${c.last_name || ''}`.trim()
      orderForm.value.customer_phone = c.phone || orderForm.value.customer_phone
      orderForm.value.customer_email = c.email || orderForm.value.customer_email
      orderForm.value.customer_document_type = c.identification_type || orderForm.value.customer_document_type
      clientLookupState.value = 'found'
      clientLookupMessage.value = `✓ Cliente encontrado: ${orderForm.value.customer_name}`
      return
    }
  } catch (err) {
    // 404 → cliente no existe en BD, continuamos al CNE
    if (!err.response || err.response.status !== 404) {
      // Error inesperado — no bloqueamos, solo continuamos al CNE
      console.warn('[TOVA] Error BD:', err)
    }
  }

  // 2️⃣ No encontrado en BD → mostrar "buscando en CNE" solo a partir de aquí
  if (orderForm.value.customer_document_type === 'V-') {
    clientLookupState.value = 'searching'
    clientLookupMessage.value = 'Buscando en api.cedula.com.ve...'
    try {
      const cneResp = await axios.post('/public/clients/cne-verify', { identification: num })
      if (cneResp.data && cneResp.data.data) {
        const cne = cneResp.data.data
        orderForm.value.customer_name = `${cne.name || ''} ${cne.last_name || ''}`.trim()
        clientLookupState.value = 'new'
        clientLookupMessage.value = `Datos obtenidos del CNE: ${orderForm.value.customer_name} — se registrará como cliente nuevo`
        return
      }
    } catch (cneErr) {
      console.log('[TOVA] CNE sin resultados:', cneErr)
    }
  }

  clientLookupState.value = 'new'
  clientLookupMessage.value = 'Documento no encontrado. Se registrará como cliente nuevo.'
}

// Disparar la búsqueda con debounce de 600ms al cambiar el número de documento
watch(
  () => orderForm.value.customer_document_number,
  (val) => {
    clearTimeout(_documentDebounceTimer)
    clientLookupState.value = 'idle'
    clientLookupMessage.value = ''
    _documentDebounceTimer = setTimeout(() => lookupClientByDocument(val), 600)
  }
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

const fetchExchangeRates = async () => {
  try {
    const { data } = await axios.get('/public/exchange-rates')
    if (Array.isArray(data)) {
      const binance = data.find(r => r.currency_code === 'BINANCE')
      if (binance && binance.rate) {
        binanceRate.value = parseFloat(binance.rate)
      }
      const cop = data.find(r => r.currency_code === 'COP')
      if (cop && cop.rate) {
        copRate.value = parseFloat(cop.rate)
      }
    }
  } catch (e) {
    console.error('Error al obtener tasas de cambio:', e)
  }
}

const fetchFavorites = async () => {
  try {
    const { data } = await axios.get('/public/ecommerce/products', { params: { favorites_only: true } })
    if (data.success) {
      favoriteProducts.value = Array.isArray(data.data) ? data.data : (data.data?.data || [])
    }
  } catch (e) {
    console.error('Error al obtener favoritos:', e)
  }
}

const fetchProducts = async (page = 1) => {
  loading.value = true
  currentPage.value = page
  try {
    const params = { page }
    if (selectedCategory.value) params.category = selectedCategory.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const { data } = await axios.get('/public/ecommerce/products', { params })
    if (data.success) {
      if (data.data && data.data.data) {
        products.value = data.data.data
        currentPage.value = data.data.current_page
        lastPage.value = data.data.last_page
      } else {
        products.value = Array.isArray(data.data) ? data.data : []
        currentPage.value = 1
        lastPage.value = 1
      }
    }
  } catch (e) {
    console.error('Error al obtener productos:', e)
  } finally {
    loading.value = false
  }
}

const toggleFavorite = async (product) => {
  try {
    const { data } = await axios.post(`/public/ecommerce/products/${product.id}/toggle-favorite`)
    if (data.success) {
      product.is_favorite = data.data.is_favorite
      // Sincronizar el estado en ambas listas
      const favProd = favoriteProducts.value.find(p => p.id === product.id)
      if (favProd) {
        favProd.is_favorite = data.data.is_favorite
      }
      const catProd = products.value.find(p => p.id === product.id)
      if (catProd) {
        catProd.is_favorite = data.data.is_favorite
      }
      // Recargar favoritos para mantener consistencia
      await fetchFavorites()
    }
  } catch (e) {
    console.error('Error al cambiar favorito:', e)
  }
}

const handleImageError = (product) => {
  product.image_failed = true
}

const carouselContainer = ref(null)

const scrollCarousel = (direction) => {
  if (!carouselContainer.value) return
  const scrollAmount = 300
  if (direction === 'left') {
    carouselContainer.value.scrollBy({ left: -scrollAmount, behavior: 'smooth' })
  } else {
    carouselContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' })
  }
}

// Búsqueda con debounce
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchProducts(1), 400)
})

const categoryLoadingSlug = ref(null)

const selectCategory = async (slug) => {
  if (loading.value) return
  categoryLoadingSlug.value = slug
  selectedCategory.value = selectedCategory.value === slug ? null : slug
  try {
    await fetchProducts(1)
  } finally {
    categoryLoadingSlug.value = null
  }
}

// ——— Carrito ———
const addToCart = (product, variant = null) => {
  if (Number(product.stock) <= 0) return
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
  if (Number(product.stock) <= 0) return
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


const changeCurrency = (currency) => {
  selectedCurrency.value = currency
  localStorage.setItem('ecommerce_currency', currency)
}

const getRateFor = (currency) => {
  if (currency === 'USD') return 1
  if (currency === 'COP') {
    const r = brandingStore.exchangeRates.find(x => x.currency_code === 'COP')
    return r ? Number(r.rate) : 3200
  }
  if (currency === 'Bs' || currency === 'VES') {
    const r = brandingStore.exchangeRates.find(x => x.currency_code === 'BINANCE')
    return r ? Number(r.rate) : 840
  }
  return 1
}

const formatPrice = (n) => {
  const rate = getRateFor(selectedCurrency.value)
  const converted = Number(n) * rate
  
  if (selectedCurrency.value === 'COP') {
    return 'COP$ ' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(converted)
  } else if (selectedCurrency.value === 'Bs' || selectedCurrency.value === 'VES') {
    return 'Bs. ' + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(converted)
  }
  return `$${Number(n).toFixed(2)}`
}

// ——— Checkout ———
const submitOrder = async () => {
  if (!cart.value.length || !orderFormValid.value) return
  orderSubmitting.value = true
  try {
    // Si hay comprobante adjunto, enviar como multipart/form-data
    let data
    if (paymentProof.value) {
      const fd = new FormData()
      Object.entries(orderForm.value).forEach(([k, v]) => fd.append(k, v ?? ''))
      cart.value.forEach((i, idx) => {
        fd.append(`items[${idx}][product_id]`, i.product.id)
        fd.append(`items[${idx}][variant_id]`, i.variant ? i.variant.id : '')
        fd.append(`items[${idx}][quantity]`, i.quantity)
      })
      fd.append('payment_proof', paymentProof.value)
      ;({ data } = await axios.post('/public/ecommerce/checkout', fd, { headers: { 'Content-Type': 'multipart/form-data' } }))
    } else {
      const payload = {
        ...orderForm.value,
        items: cart.value.map(i => ({
          product_id: i.product.id,
          variant_id: i.variant ? i.variant.id : null,
          quantity: i.quantity,
        })),
      }
      ;({ data } = await axios.post('/public/ecommerce/checkout', payload))
    }
    const fakeResp = { data }
    if (fakeResp.data.success) {
      lastOrderId.value = fakeResp.data.order_id
      orderSuccess.value = true
      clearCart()
      orderDialog.value = false
      cartDrawer.value = false
      orderForm.value = { customer_name: '', customer_email: '', customer_phone: '', shipping_address: '', notes: '', payment_method: '', customer_document_type: 'V-', customer_document_number: '' }
      selectedCurrency.value = ''
      paymentProof.value = null
      clientLookupState.value = 'idle'
      clientLookupMessage.value = ''
      await fetchProducts()
    }
  } catch (e) {
    console.error('Error en checkout:', e)
  } finally {
    orderSubmitting.value = false
  }
}

const handleMenuClick = (item) => {
  if (item.type === 'category') {
    // Buscar la categoría por ID en el listado de categorías para obtener su slug
    const found = categories.value.find(c => c.id === item.value)
    if (found) {
      selectedCategory.value = found.id // O found.slug dependiente del filtro
      fetchProducts()
    }
    scrollToCatalog()
  } else if (item.type === 'custom') {
    if (item.value.startsWith('#')) {
      const el = document.getElementById(item.value.substring(1))
      if (el) el.scrollIntoView({ behavior: 'smooth' })
    } else {
      window.location.href = item.value
    }
  }
}

const scrollToCatalog = () => {
  const el = document.getElementById('catalog')
  if (el) el.scrollIntoView({ behavior: 'smooth' })
}

onMounted(async () => {
  try {
    await brandingStore.fetchSettings()
  } catch (e) {
    // Silenciar fallos de branding
  }
  fetchExchangeRates()
  fetchCategories()
  fetchFavorites()
  fetchProducts(1)

  // Intersection Observer para revelar secciones al hacer scroll
  setTimeout(() => {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('section-revealed')
        }
      })
    }, { threshold: 0.05 })

    document.querySelectorAll('.reveal-on-scroll').forEach(el => {
      observer.observe(el)
    })
  }, 50)
})
</script>

<template>
  <div class="tova-editorial-root">
    <!-- BARRA DE NAVEGACIÓN DE LUJO EDITORIAL -->
    <nav class="editorial-nav">
      <div class="editorial-nav-inner">
        <div class="brand-logo" @click="selectedCategory = null; fetchProducts()">
          <template v-if="brandingStore.settings.app_logo">
            <img :src="brandingStore.settings.app_logo" alt="Logo" class="editorial-logo-img" style="max-height: 48px; object-fit: contain;" />
          </template>
          <template v-else>
            <span class="logo-main">{{ brandingStore.settings.app_name ? brandingStore.settings.app_name.toUpperCase() : 'TOVA' }}</span>
            <span class="logo-sub">BEAUTY & GEMS</span>
          </template>
        </div>

        <!-- Enlaces minimalistas de navegación central (Dinámicos estilo WordPress) -->
        <div class="nav-links-center d-none d-md-flex">
          <!-- Si hay un menú dinámico configurado, lo mostramos -->
          <template v-if="brandingStore.settings.ecommerce_menu && brandingStore.settings.ecommerce_menu.length">
            <div
              v-for="item in brandingStore.settings.ecommerce_menu"
              :key="item.id"
              class="nav-menu-wrapper-editorial"
            >
              <span class="nav-link-item" @click="handleMenuClick(item)">
                {{ item.label }}
                <VIcon v-if="item.children && item.children.length" icon="tabler-chevron-down" size="10" class="ml-1" />
              </span>
              
              <!-- Desplegable para Submenús -->
              <div v-if="item.children && item.children.length" class="submenu-dropdown-editorial">
                <span
                  v-for="child in item.children"
                  :key="child.id"
                  class="submenu-link-item"
                  @click="handleMenuClick(child)"
                >
                  {{ child.label }}
                </span>
              </div>
            </div>
          </template>
          <!-- Si no hay menú, cargamos enlaces por defecto -->
          <template v-else>
            <span class="nav-link-item" @click="scrollToCatalog">COMPRAR</span>
            <span class="nav-link-item" @click="selectedCategory = 9; fetchProducts(); scrollToCatalog()">MAQUILLAJE</span>
            <span class="nav-link-item" @click="selectedCategory = 5; fetchProducts(); scrollToCatalog()">SKIN CARE</span>
            <span class="nav-link-item" @click="selectedCategory = 1; fetchProducts(); scrollToCatalog()">JOYERÍA</span>
          </template>
        </div>

        <!-- Elementos del lado derecho -->
        <div class="nav-actions-right">
          <!-- Input de búsqueda súper minimalista tipo Fenty en desktop -->
          <div class="search-editorial-wrap d-none d-sm-block">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="BUSCAR..."
              class="search-editorial-input"
            />
            <span class="search-line-effect"></span>
          </div>

          <!-- Botón de Búsqueda para Móvil (xs) -->
          <button
            class="d-flex d-sm-none align-center justify-center bg-transparent border-0 px-2 py-1 cursor-pointer mr-1"
            style="color: var(--editorial-black);"
            @click="showMobileSearch = !showMobileSearch"
            title="Buscar productos"
          >
            <VIcon :icon="showMobileSearch ? 'tabler-x' : 'tabler-search'" size="20" />
          </button>

          <!-- Selector de Moneda -->
          <VMenu transition="slide-y-transition" location="bottom end">
            <template #activator="{ props }">
              <button v-bind="props" class="d-flex align-center bg-transparent border-0 px-2 py-1 cursor-pointer mr-3" style="font-size: 11px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black); text-transform: uppercase;">
                <VIcon icon="tabler-currency" size="18" class="mr-1" />
                <span>{{ selectedCurrency }}</span>
              </button>
            </template>
            <VList density="compact">
              <VListItem @click="changeCurrency('USD')">
                <VListItemTitle style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">USD ($)</VListItemTitle>
              </VListItem>
              <VListItem @click="changeCurrency('COP')">
                <VListItemTitle style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">COP (COP$)</VListItemTitle>
              </VListItem>
              <VListItem @click="changeCurrency('Bs')">
                <VListItemTitle style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">Bs. (BINANCE)</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <button class="cart-btn-editorial" @click="cartDrawer = true">
            <VIcon icon="tabler-shopping-bag" size="20" class="text-dark hover-accent" />
            <span v-if="cartTotalItems" class="cart-badge-count">({{ cartTotalItems }})</span>
          </button>
        </div>
      </div>

      <!-- Input de búsqueda desplegable en móvil -->
      <VExpandTransition>
        <div v-if="showMobileSearch" class="mobile-search-bar-wrap d-block d-sm-none">
          <div class="mobile-search-bar-inner">
            <VIcon icon="tabler-search" size="18" class="text-muted mr-2" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="BUSCAR PRODUCTOS..."
              class="mobile-search-input"
              @keyup.enter="scrollToCatalog()"
            />
            <button v-if="searchQuery" class="bg-transparent border-0 p-1" @click="searchQuery = ''">
              <VIcon icon="tabler-x" size="16" />
            </button>
          </div>
        </div>
      </VExpandTransition>
    </nav>

    <!-- 1. HERO BANNER EDITORIAL (CAMPAÑA INSPIRADA EN FENTY BEAUTY) -->
    <section class="editorial-hero">
      <div class="hero-split-layout">
        <!-- Bloque de Texto de Campaña -->
        <div class="hero-text-block animate-fade-in-left">
          <div class="hero-text-content">
            <span class="hero-tagline">{{ brandingStore.settings.hero_tagline || 'NUEVA COLECCIÓN' }}</span>
            <h1 class="hero-heading-serif">{{ brandingStore.settings.hero_title || 'YOUR NEW BOMB NUDES' }}</h1>
            <p class="hero-description-light">
              {{ brandingStore.settings.hero_subtitle || 'Tonos sofisticados, texturas sedosas y fórmulas de alta gama diseñadas para realzar tu belleza natural con un acabado impecable de pasarela.' }}
            </p>
            <button class="editorial-btn-dark" @click="scrollToCatalog">
              {{ brandingStore.settings.hero_button_text || 'COMPRAR AHORA' }}
            </button>
          </div>
        </div>
        <!-- Bloque de Imagen de Campaña -->
        <div class="hero-image-block animate-fade-in-right">
          <div class="hero-img-wrap">
            <img 
              v-if="brandingStore.settings.hero_image"
              :src="brandingStore.settings.hero_image" 
              alt="VICTORIA DORE Campaña Hero" 
              class="hero-campaign-image fade-image"
              :class="{ 'image-visible': heroImageLoaded }"
              @load="heroImageLoaded = true"
            />
            <div v-else class="hero-image-fallback" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: var(--editorial-grey-bg); color: var(--editorial-black); font-family: var(--editorial-font-serif); font-size: 40px; letter-spacing: 8px;">
              <span>VICTORIA DORE</span>
            </div>
          </div>
        </div>
      </div>
    </section>



    <!-- 3. EXPLORAR VICTORIA DORE & CATÁLOGO GENERAL EN GRILLA DE DOS FILAS (4x2) CON PAGINACIÓN -->
    <section id="catalog" class="editorial-products-section reveal-on-scroll" style="background-color: var(--editorial-grey-bg); border-bottom: 1px solid var(--editorial-border);">
      <div class="section-title-wrap" style="text-align: center; margin-bottom: 30px;">
        <h2 class="editorial-title-serif">EXPLORAR VICTORIA DORE</h2>
        <div class="title-decor-line"></div>
      </div>

      <!-- Filtros por Categoría (Chips Minimalistas) -->
      <div class="categories-editorial-flex" style="margin-bottom: 45px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
        <button
          v-for="cat in categories"
          :key="cat.id"
          class="category-editorial-chip"
          :class="{ 
            'chip-active': selectedCategory === cat.slug,
            'chip-loading': categoryLoadingSlug === cat.slug
          }"
          :disabled="loading"
          @click="selectCategory(cat.slug)"
        >
          <span v-if="categoryLoadingSlug === cat.slug" class="chip-spinner"></span>
          <span>{{ cat.name.toUpperCase() }}</span>
        </button>
      </div>

      <!-- Grid de Productos (Cargando inicial cuando está vacío) -->
      <div v-if="loading && products.length === 0" class="editorial-grid">
        <div v-for="n in 8" :key="n" class="editorial-product-skeleton">
          <div class="skeleton-img-flat"></div>
          <div class="skeleton-text-flat line-1"></div>
          <div class="skeleton-text-flat line-2"></div>
        </div>
      </div>

      <!-- Grid de Productos (Catálogo Paginado / Transición de Opacidad) -->
      <div v-else-if="products.length" class="editorial-grid" :class="{ 'grid-loading': loading }">
        <div
          v-for="product in products"
          :key="product.id"
          class="editorial-product-card"
        >
          <!-- Contenedor de Imagen -->
          <div class="editorial-product-img-wrap" @click="openQuickView(product)" :style="Number(product.stock) <= 0 ? 'cursor: not-allowed;' : ''">
            <div style="position: absolute; top: 12px; left: 12px; display: flex; flex-direction: column; gap: 6px; z-index: 5;">
              <span v-if="Number(product.stock) <= 0" style="background-color: #000000; color: #FFFFFF; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">AGOTADO</span>
              <span v-if="product.is_favorite && brandingStore.settings.enable_favorites" class="product-badge-editorial" style="position: static; margin-bottom: 0;">FAVORITO</span>
              <span v-if="product.discount_percentage" style="background-color: #E20074; color: #FFFFFF; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">-{{ Math.round(product.discount_percentage) }}%</span>
            </div>
            <img
              v-if="(product.photo_url || product.image_url) && !product.image_failed"
              :src="product.photo_url || product.image_url"
              :alt="product.name"
              class="editorial-product-image fade-image"
              loading="lazy"
              @load="($event) => $event.target.classList.add('image-visible')"
              @error="handleImageError(product)"
            />
            <div v-else class="editorial-product-image-fallback">
              <span class="fallback-logo">VICTORIA DORE</span>
              <span class="fallback-sub">NO DISPONIBLE</span>
            </div>
            <div class="editorial-card-hover-action">
              <span class="hover-action-label">{{ Number(product.stock) <= 0 ? 'AGOTADO' : 'VISTA RÁPIDA' }}</span>
            </div>
          </div>

          <!-- Info -->
          <div class="editorial-product-info" style="margin-top: 15px;">
            <h3 class="editorial-product-name" @click="openQuickView(product)" :style="Number(product.stock) <= 0 ? 'cursor: not-allowed; margin-bottom: 4px;' : 'cursor: pointer; margin-bottom: 4px;'">{{ product.name.toUpperCase() }}</h3>
            <span v-if="product.brand" class="editorial-product-brand" style="display: block; margin-bottom: 8px;">{{ product.brand }}</span>
            <div class="editorial-product-footer" style="display: flex; gap: 8px; align-items: baseline;">
              <span v-if="product.original_price" class="editorial-product-price-original" style="text-decoration: line-through; color: #888888; font-size: 13px; font-weight: 500;">{{ formatPrice(product.original_price) }}</span>
              <span class="editorial-product-price" style="font-weight: 700;">{{ formatPrice(product.sale_price) }}</span>
            </div>
            <!-- Botón de Ancho Completo Táctil y Accesible (Estilo Premium Zara/Fenty) -->
            <button
              class="editorial-add-bag-btn"
              :disabled="Number(product.stock) <= 0"
              @click.stop="openQuickView(product)"
              :style="Number(product.stock) <= 0 ? 'background: #999999; color: #fff; border: none; width: 100%; padding: 12px; font-size: 11px; font-weight: 700; letter-spacing: 2px; cursor: not-allowed; text-transform: uppercase; margin-top: 12px; display: block; text-align: center;' : 'background: var(--editorial-black); color: #fff; border: none; width: 100%; padding: 12px; font-size: 11px; font-weight: 700; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; margin-top: 12px; display: block; text-align: center; transition: all 0.3s ease;'"
            >
              {{ Number(product.stock) <= 0 ? 'AGOTADO' : '+ VER DETALLES' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Estado Vacío -->
      <div v-else class="editorial-empty-state" style="text-align: center; padding: 60px 20px;">
        <p class="empty-message-serif" style="font-size: 16px; letter-spacing: 2px; color: #888; margin-bottom: 20px;">No se encontraron productos con imagen configurada en esta categoría.</p>
        <button class="editorial-btn-dark-outline" @click="selectedCategory = null; fetchProducts(1)">
          VER TODA LA TIENDA
        </button>
      </div>

      <!-- Paginación Elegante -->
      <div v-if="lastPage > 1" class="editorial-pagination-wrap" style="display: flex; justify-content: center; align-items: center; gap: 30px; margin-top: 60px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 30px;">
        <button 
          class="editorial-btn-dark-outline" 
          style="padding: 12px 28px; font-size: 11px; letter-spacing: 2px; border-width: 1px; font-weight: 700;"
          :disabled="currentPage === 1" 
          @click="fetchProducts(currentPage - 1); scrollToCatalog()"
        >
          ← ANTERIOR
        </button>
        <span class="pagination-info" style="font-size: 12px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
          PÁGINA {{ currentPage }} DE {{ lastPage }}
        </span>
        <button 
          class="editorial-btn-dark-outline" 
          style="padding: 12px 28px; font-size: 11px; letter-spacing: 2px; border-width: 1px; font-weight: 700;"
          :disabled="currentPage === lastPage" 
          @click="fetchProducts(currentPage + 1); scrollToCatalog()"
        >
          SIGUIENTE →
        </button>
      </div>
    </section>

    <!-- 4. HÍBRIDO 50/50: MEET YOUR MATCH (TINTED MOISTURIZER) -->
    <section class="editorial-campaign-split reveal-on-scroll">
      <div class="split-row">
        <div class="split-col image-col animate-fade-in-left">
          <img 
            v-if="brandingStore.settings.section2_image"
            :src="brandingStore.settings.section2_image" 
            alt="TOVA Tinted Moisturizer" 
            class="split-image fade-image"
            :class="{ 'image-visible': section2ImageLoaded }"
            @load="section2ImageLoaded = true"
          />
        </div>
        <div class="split-col text-col bg-nude-light animate-fade-in-right">
          <div class="split-text-inner">
            <span class="split-eyebrow">{{ brandingStore.settings.section2_tagline || 'PIEL RADIANTE' }}</span>
            <h2 class="split-heading-serif">{{ brandingStore.settings.section2_title || 'MEET YOUR DONE-IN-ONE TINTED MOISTURIZER' }}</h2>
            <p class="split-paragraph" style="color: #2E2523; font-weight: 500;">
              {{ brandingStore.settings.section2_subtitle || 'Nuestra fórmula ultraligera que unifica el tono de la piel, hidrata profundamente y aporta una luminosidad natural y fresca durante todo el día. Disponible en 25 tonos flexibles.' }}
            </p>
            <button class="editorial-btn-dark-outline" @click="selectedCategory = 'skin-care'; fetchProducts(1); scrollToCatalog()">
              {{ brandingStore.settings.section2_button_text || 'DESCUBRIR TONOS' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. ICONIC PICKS - DESLIZADOR HORIZONTAL EXCLUSIVO (ESTILO FENTY - ORIGINALMENTE EN MEDIO DE LAS HÍBRIDAS) -->
    <section v-if="favoriteProducts.length && brandingStore.settings.enable_favorites" class="editorial-products-section favorites-section-wrap" style="border-top: 1px solid var(--editorial-border); border-bottom: 1px solid var(--editorial-border); background-color: var(--editorial-white);">
      <div class="catalog-header-editorial favorites-header-editorial">
        <div style="display: flex; align-items: baseline; gap: 30px; flex-wrap: wrap;">
          <h2 class="editorial-title-serif" style="margin: 0; font-size: 24px; letter-spacing: 2px;">ICONIC PICKS</h2>
        </div>
        <div class="fenty-carousel-controls" style="display: flex; gap: 12px;">
          <button class="carousel-control-circle-btn" @click="scrollCarousel('left')" title="Desplazar a la izquierda">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          </button>
          <button class="carousel-control-circle-btn" @click="scrollCarousel('right')" title="Desplazar a la derecha">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          </button>
        </div>
      </div>

      <div ref="carouselContainer" class="fenty-horizontal-row">
        <div
          v-for="product in favoriteProducts"
          :key="product.id"
          class="editorial-product-card fav-carousel-card"
        >
          <!-- Contenedor de Imagen -->
          <div class="editorial-product-img-wrap" @click="openQuickView(product)" :style="Number(product.stock) <= 0 ? 'aspect-ratio: 0.95; background-color: #F3F3F3; border: none; position: relative; cursor: not-allowed;' : 'aspect-ratio: 0.95; background-color: #F3F3F3; border: none; position: relative; cursor: pointer;'">
             <!-- Badges planos apilados como la referencia -->
             <div style="position: absolute; top: 12px; left: 12px; display: flex; flex-direction: column; gap: 6px; z-index: 5;">
               <span v-if="Number(product.stock) <= 0" style="background-color: #000000; color: #FFFFFF; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">AGOTADO</span>
               <span style="background-color: #FFFFFF; color: #000000; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 4px rgba(0,0,0,0.03);">BESTSELLER</span>
               <span v-if="product.is_favorite" style="background-color: #FFFFFF; color: #000000; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 4px rgba(0,0,0,0.03);">FAVORITO</span>
               <span v-if="product.discount_percentage" style="background-color: #E20074; color: #FFFFFF; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">-{{ Math.round(product.discount_percentage) }}%</span>
             </div>
            <img
              v-if="(product.photo_url || product.image_url) && !product.image_failed"
              :src="product.photo_url || product.image_url"
              :alt="product.name"
              class="editorial-product-image fade-image"
              loading="lazy"
              @load="($event) => $event.target.classList.add('image-visible')"
              @error="handleImageError(product)"
              style="width: 100%; height: 100%; object-fit: cover;"
            />
            <div v-else class="editorial-product-image-fallback" style="background-color: #ECECEC;">
              <span class="fallback-logo">VICTORIA DORE</span>
              <span class="fallback-sub">NO DISPONIBLE</span>
            </div>
            <div class="editorial-card-hover-action">
              <span class="hover-action-label">{{ Number(product.stock) <= 0 ? 'AGOTADO' : 'VISTA RÁPIDA' }}</span>
            </div>
          </div>

          <!-- Información de Producto estilo Fenty exacto -->
          <div class="editorial-product-info" style="padding-top: 15px; text-align: left; display: flex; flex-direction: column; flex-grow: 1;">
            <h3 class="editorial-product-name" @click="openQuickView(product)" :style="Number(product.stock) <= 0 ? 'cursor: not-allowed; font-family: var(--editorial-font-sans); font-size: 14px; font-weight: 600; letter-spacing: 0.5px; color: #000000; margin-bottom: 4px; line-height: 1.4; text-transform: none;' : 'cursor: pointer; font-family: var(--editorial-font-sans); font-size: 14px; font-weight: 600; letter-spacing: 0.5px; color: #000000; margin-bottom: 4px; line-height: 1.4; text-transform: none;'">
              {{ product.name }}
            </h3>
            
            <!-- Marca -->
            <div v-if="product.brand" style="font-size: 12px; color: #555555; margin-bottom: 6px; font-weight: 500;">
              <span style="text-transform: uppercase; font-size: 10px; font-weight: 700; color: var(--editorial-nude-dark); letter-spacing: 1px;">{{ product.brand }}</span>
            </div>
            
             <!-- Precio con empuje automático al fondo si es necesario -->
             <div class="editorial-product-price-container" style="display: flex; gap: 8px; align-items: baseline; margin-top: auto; margin-bottom: 4px;">
               <span v-if="product.original_price" style="text-decoration: line-through; color: #888888; font-size: 13px; font-weight: 500;">{{ formatPrice(product.original_price) }}</span>
               <span class="editorial-product-price" style="font-size: 16px; font-weight: 750; color: #000000; letter-spacing: 0.5px;">{{ formatPrice(product.sale_price) }}</span>
             </div>
            <!-- Botón de Ancho Completo Táctil y Accesible (Consistencia con catálogo) -->
            <button
              class="editorial-add-bag-btn"
              :disabled="Number(product.stock) <= 0"
              @click.stop="openQuickView(product)"
              :style="Number(product.stock) <= 0 ? 'background: #999999; color: #fff; border: none; width: 100%; padding: 12px; font-size: 11px; font-weight: 700; letter-spacing: 2px; cursor: not-allowed; text-transform: uppercase; margin-top: 12px; display: block; text-align: center;' : 'background: var(--editorial-black); color: #fff; border: none; width: 100%; padding: 12px; font-size: 11px; font-weight: 700; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; margin-top: 12px; display: block; text-align: center; transition: all 0.3s ease;'"
            >
              {{ Number(product.stock) <= 0 ? 'AGOTADO' : '+ VER DETALLES' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- 5. HÍBRIDO 50/50: SUN STALKER BRONZER (INVERSIÓN DE BLOQUES) -->
    <section class="editorial-campaign-split reverse-split reveal-on-scroll">
      <div class="split-row">
        <div class="split-col text-col bg-terracotta-light animate-fade-in-left">
          <div class="split-text-inner">
            <span class="split-eyebrow">{{ brandingStore.settings.section3_tagline || 'EFECTO SOL' }}</span>
            <h2 class="split-heading-serif">{{ brandingStore.settings.section3_title || 'SUN STALK\'R SOUFFLÉ PRESSED MOUSSE BRONZER' }}</h2>
            <p class="split-paragraph" style="color: #2E2523; font-weight: 500;">
              {{ brandingStore.settings.section3_subtitle || 'El bronceador definitivo que aporta calidez instantánea a tu rostro con un acabado sedoso y de larga duración. Su textura mousse prensada se funde perfectamente sobre la piel sin esfuerzo.' }}
            </p>
            <button class="editorial-btn-dark-outline" @click="selectedCategory = 'maquillaje'; fetchProducts(1); scrollToCatalog()">
              {{ brandingStore.settings.section3_button_text || 'COMPRAR BRONCEADOR' }}
            </button>
          </div>
        </div>
        <div class="split-col image-col animate-fade-in-right">
          <img 
            v-if="brandingStore.settings.section3_image"
            :src="brandingStore.settings.section3_image" 
            alt="TOVA Bronzer Compact" 
            class="split-image fade-image"
            :class="{ 'image-visible': section3ImageLoaded }"
            @load="section3ImageLoaded = true"
          />
        </div>
      </div>
    </section>

    <!-- PIE DE PÁGINA EDITORIAL SOFISTICADO -->
    <footer class="editorial-footer" style="padding: 40px 20px;">
      <div class="footer-container">
        <div class="footer-bottom-bar" style="padding-top: 0; text-align: center; justify-content: center; display: flex; width: 100%;">
          <p class="copyright-text" style="color: #FFFFFF !important; opacity: 0.9; font-size: 13px; line-height: 1.6; letter-spacing: 1px;">
            © {{ new Date().getFullYear() }} {{ brandingStore.settings.app_name ? brandingStore.settings.app_name.toUpperCase() : 'TOVA' }}. Todos los derechos reservados | Diseñado y Desarrollado por 
            <a href="https://tovaerp.com/" target="_blank" style="color: var(--editorial-nude-dark) !important; text-decoration: none; font-weight: 700;">Tova</a>
          </p>
        </div>
      </div>
    </footer>

    <!-- ====== CARRITO LATERAL EDITORIAL (DRAWER) ====== -->
    <transition name="drawer-fade">
      <div v-if="cartDrawer" class="editorial-cart-overlay" @click.self="cartDrawer = false">
        <div class="editorial-cart-drawer">
          <div class="editorial-cart-header">
            <h3 class="editorial-cart-title">TU BOLSA DE COMPRAS</h3>
            <button class="editorial-cart-close" @click="cartDrawer = false">✕</button>
          </div>

          <div class="editorial-cart-items" v-if="cart.length">
            <div v-for="item in cart" :key="item.cartKey" class="editorial-cart-item">
              <div class="cart-item-img-wrap">
                <img v-if="item.product.image_url" :src="item.product.image_url" :alt="item.product.name" />
                <div v-else class="cart-fallback-img">✦</div>
              </div>
              <div class="cart-item-details">
                <h4 class="cart-item-name-editorial">{{ item.product.name.toUpperCase() }}</h4>
                <p v-if="item.variant" class="cart-item-variant-editorial">{{ item.variant.attribute_value.toUpperCase() }}</p>
                <p class="cart-item-price-editorial">{{ formatPrice(productPrice(item.product, item.variant)) }}</p>
                
                <div class="cart-item-qty-editorial mt-2">
                  <button @click="updateQty(item.cartKey, -1)">−</button>
                  <span class="qty-num">{{ item.quantity }}</span>
                  <button @click="updateQty(item.cartKey, 1)">+</button>
                </div>
              </div>
              <button class="cart-item-remove-editorial" @click="removeFromCart(item.cartKey)">✕</button>
            </div>
          </div>
          <div v-else class="editorial-cart-empty">
            <span class="empty-sparkle">✦</span>
            <p class="empty-cart-message">TU BOLSA ESTÁ VACÍA</p>
          </div>

          <div class="editorial-cart-footer" v-if="cart.length">
            <div class="editorial-cart-summary">
              <span class="summary-label">SUBTOTAL</span>
              <span class="summary-value">{{ formatPrice(cartTotalPrice) }}</span>
            </div>
            <button class="editorial-btn-dark w-100 py-4" @click="cartDrawer = false; orderDialog = true">
              PROCEDER AL PAGO
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ====== QUICK VIEW EDITORIAL DIALOG ====== -->
    <transition name="modal-scale">
      <div v-if="quickViewDialog && selectedProduct" class="editorial-modal-overlay" @click.self="quickViewDialog = false">
        <div class="editorial-quickview-card">
          <button class="editorial-modal-close" @click="quickViewDialog = false">✕</button>

          <div class="editorial-qv-grid">
            <div class="editorial-qv-img-container">
              <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="editorial-qv-img" />
              <div v-else class="editorial-qv-fallback">TOVA</div>
            </div>
            <div class="editorial-qv-details">
              <span v-if="selectedProduct.brand" class="qv-brand-tag">{{ selectedProduct.brand }}</span>
              <h2 class="qv-title-serif">{{ selectedProduct.name.toUpperCase() }}</h2>
              <p class="qv-desc-light">{{ selectedProduct.description || 'Producto de alta gama formulado con los mejores ingredientes.' }}</p>
              <p class="qv-price-bold">{{ formatPrice(productPrice(selectedProduct, selectedVariant)) }}</p>

               <!-- Variantes de Tonos / Tamaños con Círculos de Color Hexadecimal -->
              <div v-if="selectedProduct.variants?.length" class="qv-variants-editorial">
                <p class="qv-variants-title">SELECCIONAR TONO / VARIANTE: <span class="font-weight-bold text-dark ml-1" style="text-transform: uppercase; font-size: 11px;">{{ selectedVariant?.attribute_value }}</span></p>
                <div class="qv-variants-editorial-flex" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px;">
                  <button
                    v-for="v in selectedProduct.variants"
                    :key="v.id"
                    class="qv-variant-color-circle"
                    :class="{ 'qv-circle-active': selectedVariant?.id === v.id }"
                    :style="{ backgroundColor: v.color_hex || '#E20074' }"
                    :title="v.attribute_value.toUpperCase()"
                    @click="selectedVariant = v"
                    style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #FFF; box-shadow: 0 0 0 1px #E5E5E5; cursor: pointer; transition: all 0.2s ease; position: relative;"
                  >
                    <span v-if="selectedVariant?.id === v.id" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #FFF; font-size: 12px; font-weight: bold; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">✓</span>
                  </button>
                </div>
              </div>

              <button
                class="editorial-btn-dark w-100 py-3 mt-4"
                :disabled="Number(selectedProduct.stock) <= 0"
                :style="Number(selectedProduct.stock) <= 0 ? 'opacity: 0.5; cursor: not-allowed; background: #888888;' : ''"
                @click="quickAddToCart"
              >
                {{ Number(selectedProduct.stock) <= 0 ? 'AGOTADO' : 'AÑADIR A LA BOLSA' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ====== CHECKOUT EDITORIAL DIALOG ====== -->
    <transition name="modal-scale">
      <div v-if="orderDialog" class="editorial-modal-overlay" @click.self="orderDialog = false">
        <div class="editorial-checkout-card">
          <button class="editorial-modal-close" @click="orderDialog = false">✕</button>
          <h2 class="checkout-title-serif">FINALIZAR COMPRA</h2>

          <div class="checkout-editorial-summary">
            <h3 class="checkout-section-title">RESUMEN DEL PEDIDO</h3>
            <div v-for="item in cart" :key="item.cartKey" class="checkout-summary-row">
              <span class="item-name">{{ item.product.name.toUpperCase() }} <span v-if="item.variant">({{ item.variant.attribute_value.toUpperCase() }})</span></span>
              <span class="item-qty">x{{ item.quantity }}</span>
              <span class="item-price">{{ formatPrice(productPrice(item.product, item.variant) * item.quantity) }}</span>
            </div>
            <div class="checkout-total-row">
              <span>TOTAL</span>
              <span>{{ formatPrice(cartTotalPrice) }}</span>
            </div>
          </div>

          <div class="checkout-editorial-form">
            <h3 class="checkout-section-title">INFORMACIÓN DE ENTREGA</h3>
            <div class="editorial-form-grid">
              <div class="form-input-group">
                <label>DOCUMENTO DE IDENTIDAD *</label>
                <div style="display: flex; gap: 8px;">
                  <select v-model="orderForm.customer_document_type" style="width: 80px; padding: 12px; border: 1px solid var(--editorial-border); background-color: var(--editorial-grey-bg); font-size: 13px; font-family: inherit; outline: none; border-radius: 0;">
                    <option value="V-">V</option>
                    <option value="E-">E</option>
                    <option value="J-">J</option>
                    <option value="G-">G</option>
                  </select>
                  <div style="flex: 1; position: relative;">
                    <input
                      v-model="orderForm.customer_document_number"
                      type="text"
                      placeholder="12345678"
                      style="width: 100%; box-sizing: border-box;"
                      required
                    />
                    <!-- Indicador de carga -->
                    <span v-if="clientLookupState === 'searching'" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #888;">⏳</span>
                    <span v-else-if="clientLookupState === 'found'" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #22c55e;">✓</span>
                    <span v-else-if="clientLookupState === 'new'" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #f59e0b;">★</span>
                  </div>
                </div>
                <!-- Mensaje de estado debajo del campo -->
                <p v-if="clientLookupMessage" :style="{
                  fontSize: '11px',
                  marginTop: '4px',
                  color: clientLookupState === 'found' ? '#22c55e' : clientLookupState === 'error' ? '#ef4444' : '#888'
                }">{{ clientLookupMessage }}</p>
              </div>
              <div class="form-input-group">
                <label>NOMBRE COMPLETO *</label>
                <input v-model="orderForm.customer_name" type="text" placeholder="María García" required />
              </div>
              <div class="form-input-group">
                <label>TELÉFONO DE CONTACTO *</label>
                <input v-model="orderForm.customer_phone" type="tel" placeholder="+58 412 000 0000" required />
              </div>
              <div class="form-input-group">
                <label>CORREO ELECTRÓNICO</label>
                <input v-model="orderForm.customer_email" type="email" placeholder="correo@ejemplo.com" />
              </div>
              <div class="form-input-group">
                <label>DIRECCIÓN DE ENTREGA</label>
                <input v-model="orderForm.shipping_address" type="text" placeholder="Calle, edificio, ciudad..." />
              </div>
              <div class="form-input-group full-width-input">
                <label>NOTAS ADICIONALES</label>
                <textarea v-model="orderForm.notes" placeholder="Instrucciones especiales para la entrega..." rows="2"></textarea>
              </div>
            </div>
          </div>

          <div class="checkout-payment-methods" style="margin-top: 30px;">
            <h3 class="checkout-section-title">MÉTODO DE PAGO *</h3>
            
            <!-- Selección de Moneda -->
            <div class="form-input-group" style="margin-bottom: 20px;">
              <label style="font-size: 11px; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; display: block;">MONEDA DE PAGO *</label>
              <select v-model="selectedCurrency" style="width: 100%; padding: 12px; border: 1px solid var(--editorial-border); background-color: var(--editorial-grey-bg); font-size: 13px; font-family: inherit; font-weight: 500; outline: none; border-radius: 0;" @change="orderForm.payment_method = ''">
                <option value="" disabled selected>Seleccione la moneda...</option>
                <option value="VES">VES - Bolívares</option>
                <option value="USD">USD - Dólares</option>
                <option value="COP">COP - Pesos Colombianos</option>
              </select>
            </div>

            <!-- Métodos según Moneda -->
            <div v-if="selectedCurrency" class="payment-methods-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
              
              <!-- VES Methods -->
              <template v-if="selectedCurrency === 'VES'">
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'mobile_payment' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="mobile_payment" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">📱</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">PAGO MÓVIL</span>
                </label>

                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'bank_transfer_bs' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="bank_transfer_bs" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🏦</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">TRANSFERENCIA</span>
                </label>
                
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'cash_bs' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="cash_bs" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🤝</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">CONTRAENTREGA</span>
                </label>
              </template>

              <!-- USD Methods -->
              <template v-else-if="selectedCurrency === 'USD'">
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'binance' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="binance" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🪙</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">BINANCE PAY</span>
                </label>

                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'paypal' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="paypal" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">💳</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">PAYPAL</span>
                </label>
                
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'cash_usd' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="cash_usd" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🤝</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">CONTRAENTREGA</span>
                </label>
              </template>

              <!-- COP Methods -->
              <template v-else-if="selectedCurrency === 'COP'">
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'bank_transfer' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="bank_transfer" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🏦</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">TRANSFERENCIA</span>
                </label>
                
                <label class="payment-method-card" :class="{ 'method-selected': orderForm.payment_method === 'cash_cop' }" style="border: 1px solid var(--editorial-border); padding: 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease;">
                  <input type="radio" v-model="orderForm.payment_method" value="cash_cop" style="display: none;" />
                  <span class="method-icon" style="font-size: 18px; margin-bottom: 6px;">🤝</span>
                  <span class="method-title" style="font-size: 11px; font-weight: 700; letter-spacing: 1px;">CONTRAENTREGA</span>
                </label>
              </template>

            </div>

            <!-- Detalles dinámicos según el método y moneda seleccionada -->
            <transition name="drawer-fade">
              <div v-if="selectedCurrency === 'VES' && orderForm.payment_method === 'mobile_payment'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 8px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">DATOS DE PAGO MÓVIL (VES) [EJEMPLO]:</p>
                <p><strong>Banco:</strong> Banesco (0134)</p>
                <p><strong>Teléfono:</strong> +58 412 000 0000</p>
                <p><strong>Cédula:</strong> V-12.345.678</p>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--editorial-border);">
                  <p><strong>Tasa de cambio Binance:</strong> {{ binanceRate.toFixed(2) }} VES/USD</p>
                  <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">Bs. {{ (cartTotalPrice * binanceRate).toFixed(2) }}</span></p>
                </div>
              </div>
              <div v-else-if="selectedCurrency === 'VES' && orderForm.payment_method === 'bank_transfer_bs'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 8px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">DATOS DE TRANSFERENCIA (VES) [EJEMPLO]:</p>
                <p><strong>Banco:</strong> Banesco (0134)</p>
                <p><strong>Cuenta Corriente:</strong> 0134-1234-56-1234567890</p>
                <p><strong>Rif:</strong> J-12345678-9</p>
                <p><strong>Titular:</strong> Tova Belleza y Joyería C.A.</p>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--editorial-border);">
                  <p><strong>Tasa de cambio Binance:</strong> {{ binanceRate.toFixed(2) }} VES/USD</p>
                  <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">Bs. {{ (cartTotalPrice * binanceRate).toFixed(2) }}</span></p>
                </div>
                <!-- Upload comprobante de transferencia -->
                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--editorial-border);">
                  <p style="font-weight: 700; letter-spacing: 1px; margin-bottom: 8px;">ADJUNTAR COMPROBANTE DE PAGO:</p>
                  <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; border: 1px dashed var(--editorial-border); padding: 10px; background: #fff;">
                    <span style="font-size: 18px;">📎</span>
                    <span style="font-size: 11px;">{{ paymentProof ? paymentProof.name : 'Seleccionar imagen o PDF...' }}</span>
                    <input type="file" accept="image/*,application/pdf" style="display:none;" @change="e => paymentProof = e.target.files[0] || null" />
                  </label>
                </div>
              </div>
              <div v-else-if="selectedCurrency === 'VES' && orderForm.payment_method === 'cash_bs'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 4px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">CONTRAENTREGA (EFECTIVO VES):</p>
                <p>Pague en efectivo Bolívares (VES) al recibir su pedido.</p>
                <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">Bs. {{ (cartTotalPrice * binanceRate).toFixed(2) }}</span></p>
              </div>
              <div v-else-if="selectedCurrency === 'USD' && orderForm.payment_method === 'paypal'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 8px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">DATOS DE PAGO PAYPAL (USD) [EJEMPLO]:</p>
                <p><strong>Correo electrónico PayPal:</strong> pagos@tova.com</p>
                <p><strong>Titular:</strong> Tova Beauty & Gems LLC</p>
                <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">$ {{ cartTotalPrice.toFixed(2) }}</span></p>
                <p style="margin-top: 8px; font-size: 10px; color: #666;">Por favor envíe el capture del pago con su nombre de referencia.</p>
              </div>
              <div v-else-if="selectedCurrency === 'USD' && orderForm.payment_method === 'binance'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 8px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">DATOS DE BINANCE PAY (USD) [EJEMPLO]:</p>
                <p><strong>Pay ID:</strong> 987654321</p>
                <p><strong>Titular:</strong> Tova Store Pay</p>
                <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">USDT {{ cartTotalPrice.toFixed(2) }}</span></p>
              </div>
              <div v-else-if="selectedCurrency === 'USD' && orderForm.payment_method === 'cash_usd'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 4px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">CONTRAENTREGA (EFECTIVO USD):</p>
                <p>Pague en efectivo Dólares (USD) al recibir su pedido.</p>
                <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">$ {{ cartTotalPrice.toFixed(2) }}</span></p>
              </div>
              <div v-else-if="selectedCurrency === 'COP' && orderForm.payment_method === 'bank_transfer'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 8px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">DATOS DE TRANSFERENCIA (COP) [EJEMPLO]:</p>
                <p><strong>Banco:</strong> Bancolombia</p>
                <p><strong>Cuenta de Ahorros:</strong> 123-456789-01</p>
                <p><strong>Titular:</strong> Inversiones Tova S.A.S</p>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--editorial-border);">
                  <p><strong>Tasa de cambio COP:</strong> {{ copRate.toFixed(2) }} COP/USD</p>
                  <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">COP {{ (cartTotalPrice * copRate).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></p>
                </div>
                <!-- Upload comprobante de transferencia -->
                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--editorial-border);">
                  <p style="font-weight: 700; letter-spacing: 1px; margin-bottom: 8px;">ADJUNTAR COMPROBANTE DE PAGO:</p>
                  <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; border: 1px dashed var(--editorial-border); padding: 10px; background: #fff;">
                    <span style="font-size: 18px;">📎</span>
                    <span style="font-size: 11px;">{{ paymentProof ? paymentProof.name : 'Seleccionar imagen o PDF...' }}</span>
                    <input type="file" accept="image/*,application/pdf" style="display:none;" @change="e => paymentProof = e.target.files[0] || null" />
                  </label>
                </div>
              </div>
              <div v-else-if="selectedCurrency === 'COP' && orderForm.payment_method === 'cash_cop'" class="payment-details-box" style="background-color: var(--editorial-grey-bg); border: 1px solid var(--editorial-border); padding: 20px; font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                <p style="margin-bottom: 4px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-black);">CONTRAENTREGA (EFECTIVO COP):</p>
                <p>Pague en efectivo Pesos Colombianos (COP) al recibir su pedido.</p>
                <p style="font-size: 14px; color: var(--editorial-black); margin-top: 4px;"><strong>Monto Total a Pagar:</strong> <span style="font-weight: 750;">COP {{ (cartTotalPrice * copRate).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></p>
              </div>
            </transition>
          </div>

          <button
            class="editorial-btn-dark w-100 py-4 mt-4"
            :disabled="!orderFormValid || orderSubmitting"
            @click="submitOrder"
          >
            <span v-if="orderSubmitting">PROCESANDO...</span>
            <span v-else>CONFIRMAR COMPRA · {{ formatPrice(cartTotalPrice) }}</span>
          </button>
        </div>
      </div>
    </transition>

    <!-- ====== SUCCESS MESSAGE (MODAL EDITORIAL) ====== -->
    <transition name="drawer-fade">
      <div v-if="orderSuccess" class="editorial-modal-overlay" style="z-index: 99999;" @click="orderSuccess = false">
        <div class="editorial-success-modal" @click.stop>
          <span class="modal-success-sparkle">✦</span>
          <h2 class="modal-success-title">¡PEDIDO CONFIRMADO!</h2>
          <div class="modal-success-line"></div>
          <p class="modal-success-desc">
            Tu orden <span style="font-weight: 750;">#{{ lastOrderId }}</span> ha sido procesada con éxito.
          </p>
          <p class="modal-success-subdesc">
            Pronto nos pondremos en contacto contigo para coordinar la entrega y los detalles del pago.
          </p>
          <button class="editorial-btn-dark py-3 px-6 mt-4 w-100" @click="orderSuccess = false">
            SEGUIR COMPRANDO
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<style>
/* ==========================================================================
   ESTÉTICA EDITORIAL DE ALTA GAMA — TOVA BEAUTY & GEMS (INSPIRADO EN FENTY)
   ========================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Cinzel:wght@400;700;900&family=Montserrat:wght@200;300;400;500;600;700&display=swap');

.tova-editorial-root {
  /* Variables de Color e Identidad */
  --editorial-black: #1E1614; /* Chocolate Expreso Profundo: cálido, ultra-premium */
  --editorial-white: #FAFAFA;
  --editorial-grey-bg: #FAFAFA;
  --editorial-border: #E8E8E8;
  --editorial-nude-dark: #E8C5C8;
  --editorial-nude-light: #FAFAFA;
  --editorial-terracotta-light: #E8C5C8;
  --editorial-text-muted: #666666;
  --editorial-font-serif: 'Abril Fatface', 'Cinzel', Georgia, serif;
  --editorial-font-sans: 'Abril Fatface', 'Montserrat', sans-serif;

  min-height: 100vh;
  background-color: var(--editorial-white);
  color: var(--editorial-black);
  font-family: var(--editorial-font-sans);
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}

.tova-editorial-root,
.tova-editorial-root *:not(.v-icon):not([class*="tabler-"]):not([class*="fa-"]):not(i) {
  font-family: 'Abril Fatface', cursive, serif !important;
}

/* ——— Tipografía General ——— */
.editorial-title-serif {
  font-family: var(--editorial-font-serif);
  font-weight: 700;
  letter-spacing: 4px;
  font-size: clamp(24px, 3vw, 36px);
  color: var(--editorial-black);
}

/* ——— Barra de Navegación Editorial ——— */
.editorial-nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 900;
  background-color: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--editorial-border);
}
.editorial-nav-inner {
  max-width: 1440px;
  margin: 0 auto;
  padding: 0 40px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
@media (max-width: 768px) {
  .editorial-nav-inner { padding: 0 20px; height: 64px; }
}

.brand-logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  user-select: none;
}
.logo-main {
  font-family: var(--editorial-font-serif);
  font-size: 28px;
  font-weight: 900;
  letter-spacing: 8px;
  line-height: 1;
}
.logo-sub {
  font-family: var(--editorial-font-sans);
  font-size: 8px;
  font-weight: 500;
  letter-spacing: 4px;
  margin-top: 4px;
  color: var(--editorial-text-muted);
}

.nav-links-center {
  display: flex;
  gap: 32px;
}
.nav-menu-wrapper-editorial {
  position: relative;
}
.nav-link-item {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 2px;
  cursor: pointer;
  position: relative;
  padding: 10px 0;
  display: inline-flex;
  align-items: center;
  transition: color 0.3s;
}
.nav-link-item::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; width: 100%; height: 1px;
  background-color: var(--editorial-black);
  transform: scaleX(0);
  transform-origin: right;
  transition: transform 0.35s ease;
}
.nav-link-item:hover::after {
  transform: scaleX(1);
  transform-origin: left;
}

/* Submenús */
.submenu-dropdown-editorial {
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(10px);
  background-color: var(--editorial-white);
  border: 1px solid var(--editorial-border);
  min-width: 180px;
  padding: 12px 0;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  z-index: 1000;
}
.nav-menu-wrapper-editorial:hover .submenu-dropdown-editorial {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}
.submenu-link-item {
  padding: 8px 24px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1.5px;
  color: var(--editorial-black);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}
.submenu-link-item:hover {
  background-color: rgba(0, 0, 0, 0.03);
  color: var(--editorial-nude-dark);
  padding-left: 28px;
}

.nav-actions-right {
  display: flex;
  align-items: center;
  gap: 24px;
}

.search-editorial-wrap {
  position: relative;
}
.search-editorial-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 2px;
  padding: 6px 0;
  width: 120px;
  transition: width 0.4s ease;
}
.search-editorial-input:focus {
  width: 180px;
}
.search-line-effect {
  position: absolute;
  bottom: 0; left: 0; width: 100%; height: 1px;
  background-color: var(--editorial-border);
}
.search-editorial-input:focus + .search-line-effect {
  background-color: var(--editorial-black);
}

.cart-btn-editorial {
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}
.cart-text-btn {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
}
.cart-badge-count {
  font-size: 11px;
  font-weight: 500;
}

/* ——— 1. Hero Banner Editorial ——— */
.editorial-hero {
  margin-top: 80px;
  border-bottom: 1px solid var(--editorial-border);
}
@media (max-width: 768px) {
  .editorial-hero { margin-top: 64px; }
}
.hero-split-layout {
  display: flex;
  min-height: 560px;
}
@media (max-width: 960px) {
  .hero-split-layout { flex-direction: column-reverse; }
}

.hero-text-block {
  flex: 1;
  background-color: var(--editorial-black); /* Dinámico: usa el Color Secundario elegido (#2B2B2B) */
  color: #FFFFFF; /* Blanco puro para legibilidad inmaculada */
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 40px;
}
.hero-text-content {
  max-width: 480px;
}
.hero-tagline {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 4px;
  color: var(--editorial-nude-dark); /* Dinámico: usa el Color Terciario / Acento elegido (#E8C5C8) */
  display: block;
  margin-bottom: 16px;
}
.hero-heading-serif {
  font-family: var(--editorial-font-serif);
  font-size: clamp(38px, 5vw, 64px);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: 2px;
  margin-bottom: 24px;
  color: #FFFFFF !important; /* Forzado absoluto a blanco brillante */
}
.hero-description-light {
  font-size: 14px;
  font-weight: 500;
  line-height: 1.8;
  color: rgba(255, 255, 255, 0.95) !important; /* Forzado absoluto a blanco de alta opacidad */
  margin-bottom: 36px;
}

.hero-image-block {
  flex: 1;
  position: relative;
  background-color: var(--editorial-grey-bg);
}
.hero-img-wrap {
  width: 100%;
  height: 100%;
  min-height: 400px;
}
.hero-campaign-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* ——— Botón Editorial Premium ——— */
.editorial-btn-dark {
  background-color: var(--editorial-nude-dark); /* Dinámico: usa el Color Terciario / Acento elegido (#E8C5C8) */
  color: var(--editorial-black); /* Dinámico: usa el Color Secundario de contraste (#2B2B2B) */
  border: none;
  padding: 16px 40px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 3px;
  cursor: pointer;
  transition: all 0.3s ease;
}
.editorial-btn-dark:hover:not(:disabled) {
  background-color: #FFFFFF; /* Brilla a blanco puro en hover */
  color: #000000;
  transform: translateY(-2px);
}
.editorial-btn-dark:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.editorial-btn-dark-outline {
  background-color: var(--editorial-black);
  color: var(--editorial-white);
  border: 1px solid var(--editorial-black);
  padding: 16px 40px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 3px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
}
.editorial-btn-dark-outline:hover {
  background-color: transparent;
  color: var(--editorial-black);
  border-color: var(--editorial-black);
}

/* ——— Categorías Editorial ——— */
.editorial-categories-section {
  padding: 60px 40px 40px;
  text-align: center;
}
@media (max-width: 768px) {
  .editorial-categories-section { padding: 40px 20px 20px; }
}
.section-title-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 30px;
}
.title-decor-line {
  width: 60px;
  height: 1px;
  background-color: var(--editorial-black);
  margin-top: 12px;
}
.categories-editorial-flex {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 16px;
}
.category-editorial-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background-color: #F5F3F2; /* Fondo beige interactivo muy sutil */
  border: 1px solid #E5E2E0; /* Borde de contraste suave */
  border-radius: 20px; /* Cápsula elegante y ergonómica */
  color: var(--editorial-black);
  padding: 10px 24px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1.5px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
  user-select: none;
}
.category-editorial-chip:hover:not(:disabled) {
  background-color: #EAE5E2;
  border-color: var(--editorial-black);
}
.category-editorial-chip:disabled {
  opacity: 0.65;
  cursor: wait !important;
}
.category-editorial-chip.chip-active {
  border-color: var(--editorial-black);
  background-color: var(--editorial-black) !important;
  color: var(--editorial-white) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.chip-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: chipSpin 0.6s linear infinite;
  display: inline-block;
}
@keyframes chipSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ——— 2. Híbrido 50/50: Split Row ——— */
.editorial-campaign-split {
  border-top: 1px solid var(--editorial-border);
  border-bottom: 1px solid var(--editorial-border);
}
.split-row {
  display: flex;
}
@media (max-width: 960px) {
  .split-row { flex-direction: column; }
}
.split-col {
  flex: 1;
}
.split-col.image-col {
  background-color: var(--editorial-grey-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 480px;
}
.split-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.split-col.text-col {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 80px 60px;
}
@media (max-width: 768px) {
  .split-col.text-col { padding: 40px 20px; }
}
.split-text-inner {
  max-width: 440px;
}
.split-eyebrow {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--editorial-black, #1a1a1a);
  background-color: rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(0, 0, 0, 0.12);
  padding: 4px 10px;
  border-radius: 4px;
  margin-bottom: 16px;
}
.split-heading-serif {
  font-family: var(--editorial-font-serif);
  font-size: clamp(28px, 4vw, 42px);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: 1px;
  margin-bottom: 20px;
  color: var(--editorial-black);
}
.split-paragraph {
  font-size: 14px;
  font-weight: 500;
  line-height: 1.8;
  color: #3E3533;
  margin-bottom: 32px;
}

/* Tonos Nude/Tierra Especiales */
.bg-nude-light {
  background-color: var(--editorial-nude-light);
}
.bg-terracotta-light {
  background-color: var(--editorial-terracotta-light);
}

/* Inversión del bloque 50/50 */
.reverse-split .split-row {
  flex-direction: row-reverse;
}
@media (max-width: 960px) {
  .reverse-split .split-row { flex-direction: column-reverse; }
}

/* ——— 3. Grilla de Productos Editorial (Product Grid) ——— */
.editorial-products-section {
  max-width: 1440px;
  margin: 0 auto;
  padding: 80px 40px;
}
@media (max-width: 768px) {
  .editorial-products-section { padding: 40px 20px; }
}
.catalog-header-editorial {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  border-bottom: 1px solid var(--editorial-black);
  padding-bottom: 12px;
  margin-bottom: 40px;
}
.editorial-clear-filter {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
  cursor: pointer;
  color: var(--editorial-black);
  text-decoration: underline;
}

.tova-editorial-root {
  overflow-x: hidden;
  width: 100%;
  position: relative;
}

/* Buscador desplegable móvil */
.mobile-search-bar-wrap {
  background: var(--editorial-white);
  border-bottom: 1px solid var(--editorial-border);
  padding: 10px 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.mobile-search-bar-inner {
  display: flex;
  align-items: center;
  background: #F4F4F4;
  border-radius: 20px;
  padding: 8px 14px;
}
.mobile-search-input {
  border: none;
  background: transparent;
  outline: none;
  width: 100%;
  font-size: 13px;
  font-weight: 500;
  color: var(--editorial-black);
}

/* ——— 3. Grilla de Productos Editorial (Product Grid) ——— */
.editorial-products-section {
  max-width: 1440px;
  margin: 0 auto;
  padding: 80px 40px;
  box-sizing: border-box;
}
@media (max-width: 768px) {
  .editorial-products-section { padding: 40px 16px !important; }
}

.favorites-header-editorial {
  padding: 0 40px;
  margin-bottom: 25px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--editorial-border);
  padding-bottom: 15px;
}
@media (max-width: 768px) {
  .favorites-header-editorial {
    padding: 0 16px !important;
    margin-bottom: 16px !important;
  }
  .favorites-header-editorial .editorial-title-serif {
    font-size: 18px !important;
    letter-spacing: 1px !important;
  }
}

.catalog-header-editorial {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  border-bottom: 1px solid var(--editorial-black);
  padding-bottom: 12px;
  margin-bottom: 40px;
}
.editorial-clear-filter {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
  cursor: pointer;
  color: var(--editorial-black);
  text-decoration: underline;
}

.editorial-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 30px;
  transition: opacity 0.3s ease-in-out;
}
.editorial-grid.grid-loading {
  opacity: 0.45;
  pointer-events: none;
}
@media (max-width: 1200px) {
  .editorial-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 24px !important; }
}
@media (max-width: 768px) {
  .editorial-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 16px !important; }
}
@media (max-width: 480px) {
  .editorial-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
}

/* Carrusel Horizontal de Favoritos Habilitado Táctil para Móvil */
.fenty-horizontal-row {
  display: flex;
  gap: 24px;
  padding: 0 40px 20px 40px;
  overflow-x: auto;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x pan-y;
  overscroll-behavior-x: contain;
  scroll-snap-type: x mandatory;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE y Edge */
}
.fenty-horizontal-row::-webkit-scrollbar {
  display: none; /* Chrome, Safari y Opera */
}
@media (max-width: 768px) {
  .fenty-horizontal-row {
    padding: 0 16px 16px 16px !important;
    gap: 14px !important;
  }
}

.fav-carousel-card {
  flex: 0 0 280px;
  min-width: 280px;
  scroll-snap-align: start;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
}
@media (max-width: 768px) {
  .fav-carousel-card {
    flex: 0 0 220px !important;
    min-width: 220px !important;
  }
}
@media (max-width: 480px) {
  .fav-carousel-card {
    flex: 0 0 72vw !important;
    min-width: 210px !important;
  }
}

/* Fila Horizontal de Favoritos Estilo Fenty */
.editorial-grid.fenty-horizontal-row {
  display: flex;
  flex-direction: row;
  overflow-x: auto;
  scroll-behavior: smooth;
  gap: 20px;
  padding-bottom: 20px;
  scrollbar-width: none; /* Firefox */
}
.editorial-grid.fenty-horizontal-row::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Opera */
}
.editorial-grid.fenty-horizontal-row .editorial-product-card {
  flex: 0 0 280px; /* Ancho fijo para cada tarjeta en el carrusel */
}

/* Controles de Carrusel */
.fenty-carousel-controls {
  display: flex;
  gap: 12px;
  align-items: center;
}
.carousel-control-circle-btn {
  background-color: var(--editorial-white);
  border: 1px solid var(--editorial-border);
  border-radius: 50%;
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--editorial-black);
  transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.carousel-control-circle-btn:hover {
  background-color: var(--editorial-black);
  color: var(--editorial-white);
  border-color: var(--editorial-black);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}
.carousel-control-circle-btn:active {
  transform: translateY(0) scale(0.95);
}

/* Consistencia de hover en tarjetas de carrusel de favoritos */
.fav-carousel-card .editorial-add-bag-btn {
  opacity: 1;
  transform: none;
  transition: all 0.3s ease;
}
.fenty-horizontal-row {
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE y Edge */
}
.fenty-horizontal-row::-webkit-scrollbar {
  display: none; /* Chrome, Safari y Opera */
}

/* Tarjeta de Producto Totalmente Plana (Inspiración Fenty) */
.editorial-product-card {
  display: flex;
  flex-direction: column;
  position: relative;
}
.editorial-product-img-wrap {
  position: relative;
  aspect-ratio: 1;
  background-color: #F5F5F5; /* Fondo gris plano y claro */
  overflow: hidden;
  border: 1px solid #EAEAEA;
  cursor: pointer;
}
.editorial-product-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}
.editorial-product-card:hover .editorial-product-image {
  transform: scale(1.04);
}

.editorial-product-image-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background-color: #ECECEC;
  color: #888888;
  font-family: var(--editorial-font-serif);
  gap: 6px;
}
.fallback-logo {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 4px;
}
.fallback-sub {
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 2px;
  opacity: 0.7;
}

/* Insignia de Favorito */
.product-badge-editorial {
  position: absolute;
  top: 12px;
  left: 12px;
  background-color: #000000;
  color: #FFFFFF;
  padding: 4px 8px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 1.5px;
  z-index: 5;
  text-transform: uppercase;
}

/* Botón interactivo de Favorito (Corazón) */
.favorite-toggle-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  background: #FFFFFF;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 6;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  transition: transform 0.25s ease, color 0.25s ease, background-color 0.25s ease;
  color: #CCCCCC;
}
.favorite-toggle-btn:hover {
  transform: scale(1.12);
  color: #FF5A5F;
}
.favorite-toggle-btn.is-active-fav {
  color: #FF5A5F;
  background-color: #FFFFFF;
}
.heart-icon {
  width: 15px;
  height: 15px;
  transition: transform 0.2s ease;
}
.favorite-toggle-btn:active .heart-icon {
  transform: scale(0.85);
}

/* Valoración por estrellas */
.product-rating-stars {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  margin-top: 2px;
  margin-bottom: 6px;
}
.star-filled {
  color: #000000;
}
.star-half {
  color: #000000;
  opacity: 0.4;
}
.rating-count {
  font-size: 10px;
  color: #888888;
  margin-left: 4px;
  font-weight: 500;
}

/* Hover Editorial tipo Revista */
.editorial-card-hover-action {
  position: absolute;
  inset: 0;
  background-color: rgba(255, 255, 255, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s;
}
.editorial-product-card:hover .editorial-card-hover-action {
  opacity: 1;
}
.hover-action-label {
  background-color: var(--editorial-black);
  color: var(--editorial-white);
  padding: 12px 24px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
}

/* Tipografía de la Tarjeta */
.editorial-product-info {
  padding: 20px 0 0;
}
.editorial-product-brand {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 2px;
  color: var(--editorial-nude-dark);
  text-transform: uppercase;
  display: block;
  margin-bottom: 6px;
}
.editorial-product-name {
  font-family: var(--editorial-font-serif);
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 1px;
  margin-bottom: 8px;
  line-height: 1.3;
}
.editorial-product-footer {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}
.editorial-product-price {
  font-size: 16px;
  font-weight: 750;
  color: var(--editorial-black);
  letter-spacing: 0.5px;
}
.editorial-variants-indicator {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1px;
  color: var(--editorial-text-muted);
}

/* Skeletons Planos */
.editorial-product-skeleton {
  display: flex;
  flex-direction: column;
}
.skeleton-img-flat {
  aspect-ratio: 1;
  background-color: var(--editorial-grey-bg);
  animation: pulse-editorial 1.5s infinite;
}
.skeleton-text-flat {
  height: 12px;
  background-color: var(--editorial-grey-bg);
  margin-top: 12px;
  animation: pulse-editorial 1.5s infinite;
}
.skeleton-text-flat.line-1 { width: 40%; }
.skeleton-text-flat.line-2 { width: 70%; }
@keyframes pulse-editorial {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

.editorial-empty-state {
  text-align: center;
  padding: 80px 20px;
}
.empty-message-serif {
  font-family: var(--editorial-font-serif);
  font-size: 20px;
  letter-spacing: 2px;
  margin-bottom: 24px;
}

/* ——— Pie de Página de Lujo ——— */
.editorial-footer {
  background-color: var(--editorial-black);
  color: var(--editorial-white);
  padding: 80px 40px 40px;
  margin-top: auto;
}
@media (max-width: 768px) {
  .editorial-footer { padding: 60px 20px 30px; }
}
.footer-container {
  max-width: 1440px;
  margin: 0 auto;
}
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 60px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 60px;
}
@media (max-width: 768px) {
  .footer-grid { grid-template-columns: 1fr; gap: 40px; }
}

.footer-brand-section {
  max-width: 400px;
}
.footer-brand-logo {
  font-family: var(--editorial-font-serif);
  font-size: 36px;
  font-weight: 900;
  letter-spacing: 12px;
  line-height: 1;
}
.footer-brand-tagline {
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 6px;
  color: #888;
  margin-top: 6px;
  margin-bottom: 24px;
}
.footer-brand-desc {
  font-size: 13px;
  line-height: 1.8;
  color: #888;
}

.footer-links-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.footer-section-title {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
  margin-bottom: 8px;
}
.footer-link {
  font-size: 12px;
  color: #888;
  cursor: pointer;
  transition: color 0.2s;
}
.footer-link:hover {
  color: var(--editorial-white);
}

.footer-bottom-bar {
  padding-top: 40px;
  display: flex;
  justify-content: space-between;
}
.copyright-text {
  font-size: 11px;
  color: #666;
  letter-spacing: 1px;
}

/* ——— ====== CARRITO DRAWER EDITORIAL ====== ——— */
.editorial-cart-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(8px);
}
.editorial-cart-drawer {
  position: absolute; right: 0; top: 0; bottom: 0;
  width: min(460px, 95vw);
  background-color: var(--editorial-white);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: -20px 0 60px rgba(0,0,0,0.15);
}
.editorial-cart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 30px;
  border-bottom: 1px solid var(--editorial-border);
}
.editorial-cart-title {
  font-family: var(--editorial-font-serif);
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 3px;
}
.editorial-cart-close {
  background: none; border: none; font-size: 20px; cursor: pointer;
}

.editorial-cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 30px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.editorial-cart-item {
  display: flex;
  gap: 16px;
  position: relative;
}
.cart-item-img-wrap {
  width: 90px;
  aspect-ratio: 1;
  background-color: var(--editorial-grey-bg);
  border: 1px solid var(--editorial-border);
}
.cart-item-img-wrap img {
  width: 100%; height: 100%; object-fit: cover;
}
.cart-fallback-img {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--editorial-nude-dark);
}

.cart-item-details {
  flex: 1;
}
.cart-item-name-editorial {
  font-family: var(--editorial-font-serif);
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 1px;
  margin-bottom: 4px;
}
.cart-item-variant-editorial {
  font-size: 10px;
  font-weight: 600;
  color: var(--editorial-text-muted);
  letter-spacing: 1px;
  margin-bottom: 6px;
}
.cart-item-price-editorial {
  font-size: 13px;
  font-weight: 700;
}

.cart-item-qty-editorial {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--editorial-border);
  background-color: var(--editorial-white);
}
.cart-item-qty-editorial button {
  background: none; border: none; width: 32px; height: 32px; font-size: 16px; cursor: pointer;
}
.qty-num {
  font-size: 12px; font-weight: 700; width: 32px; text-align: center;
}
.cart-item-remove-editorial {
  background: none; border: none; cursor: pointer; font-size: 14px; color: #888; align-self: flex-start;
}

.editorial-cart-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
}
.empty-sparkle {
  font-size: 32px; color: var(--editorial-nude-dark);
}
.empty-cart-message {
  font-size: 12px; font-weight: 700; letter-spacing: 3px; color: #888;
}

.editorial-cart-footer {
  padding: 30px;
  border-top: 1px solid var(--editorial-border);
  background-color: var(--editorial-white);
}
.editorial-cart-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.summary-label {
  font-size: 11px; font-weight: 700; letter-spacing: 2px;
}
.summary-value {
  font-size: 20px; font-weight: 700;
}

/* ——— ====== DIALOGS EDITORIALES ====== ——— */
.editorial-modal-overlay {
  position: fixed; inset: 0; z-index: 1100;
  background-color: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.editorial-quickview-card {
  background-color: var(--editorial-white);
  max-width: 900px;
  width: 100%;
  position: relative;
  box-shadow: 0 40px 100px rgba(0,0,0,0.25);
  max-height: 90vh;
  overflow-y: auto;
}
.editorial-modal-close {
  position: absolute; top: 24px; right: 24px; z-index: 10;
  background: none; border: none; font-size: 20px; cursor: pointer;
}

.editorial-qv-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
}
@media (max-width: 768px) {
  .editorial-qv-grid { grid-template-columns: 1fr; }
}
.editorial-qv-img-container {
  background-color: var(--editorial-grey-bg);
  max-width: 450px;
  max-height: 450px;
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  overflow: hidden;
}
.editorial-qv-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
}
.editorial-qv-fallback {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  font-family: var(--editorial-font-serif); font-size: 40px; letter-spacing: 8px; opacity: 0.1;
}

.editorial-qv-details {
  padding: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
@media (max-width: 768px) {
  .editorial-qv-details { padding: 30px 20px; }
}
.qv-brand-tag {
  font-size: 10px; font-weight: 700; letter-spacing: 3px; color: var(--editorial-nude-dark); text-transform: uppercase; margin-bottom: 8px;
}
.qv-title-serif {
  font-family: var(--editorial-font-serif); font-size: 28px; font-weight: 700; letter-spacing: 1px; margin-bottom: 16px;
}
.qv-desc-light {
  font-size: 14px; line-height: 1.8; color: var(--editorial-text-muted); margin-bottom: 20px;
}
.qv-price-bold {
  font-size: 24px; font-weight: 700; margin-bottom: 24px;
}

.qv-variants-editorial-flex {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}
/* Variaciones en forma de círculos de color */
.qv-variant-color-circle:hover {
  transform: scale(1.15);
  box-shadow: 0 0 0 1px var(--editorial-black), 0 4px 10px rgba(0,0,0,0.15) !important;
  z-index: 2;
}
.qv-variant-color-circle.qv-circle-active {
  box-shadow: 0 0 0 2px var(--editorial-black) !important;
  transform: scale(1.1);
}

/* Checkout Modal */
.editorial-checkout-card {
  background-color: var(--editorial-white);
  max-width: 680px;
  width: 100%;
  position: relative;
  box-shadow: 0 40px 100px rgba(0,0,0,0.25);
  max-height: 90vh;
  overflow-y: auto;
  padding: 50px;
}
@media (max-width: 768px) {
  .editorial-checkout-card { padding: 30px 20px; }
}
.checkout-title-serif {
  font-family: var(--editorial-font-serif); font-size: 24px; font-weight: 700; letter-spacing: 3px; margin-bottom: 30px; text-align: center;
}

.checkout-editorial-summary {
  background-color: var(--editorial-grey-bg);
  padding: 24px;
  margin-bottom: 30px;
}
.checkout-section-title {
  font-size: 11px; font-weight: 700; letter-spacing: 2px; color: var(--editorial-nude-dark); margin-bottom: 16px; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 8px;
}
.checkout-summary-row {
  display: flex; justify-content: space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.03);
}
.checkout-summary-row .item-name { flex: 1; }
.checkout-summary-row .item-qty { margin-right: 20px; font-weight: 700; }
.checkout-summary-row .item-price { font-weight: 700; }
.checkout-total-row {
  display: flex; justify-content: space-between; font-weight: 700; font-size: 16px; padding-top: 16px;
}

.editorial-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 580px) {
  .editorial-form-grid { grid-template-columns: 1fr; }
}
.form-input-group {
  display: flex; flex-direction: column; gap: 6px;
}
.form-input-group.full-width-input {
  grid-column: 1 / -1;
}
.form-input-group label {
  font-size: 10px; font-weight: 700; letter-spacing: 1px; color: var(--editorial-text-muted);
}
.form-input-group input,
.form-input-group textarea {
  border: 1px solid var(--editorial-border);
  padding: 14px;
  font-size: 13px;
  font-weight: 500;
  outline: none;
  background-color: var(--editorial-white);
  transition: border-color 0.3s;
}
.form-input-group input:focus,
.form-input-group textarea:focus {
  border-color: var(--editorial-black);
}

/* Selector interactivo de Métodos de Pago */
.payment-method-card {
  background-color: var(--editorial-white);
  border: 1px solid var(--editorial-border);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
}
.payment-method-card:hover {
  border-color: var(--editorial-black);
  background-color: rgba(0, 0, 0, 0.01);
}
.payment-method-card.method-selected {
  border-color: var(--editorial-black) !important;
  background-color: var(--editorial-black) !important;
  color: var(--editorial-white) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}


/* ——— Success Modal ——— */
.editorial-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(30, 22, 20, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.editorial-success-modal {
  background-color: var(--editorial-white);
  color: var(--editorial-black);
  border: 1px solid var(--editorial-border);
  padding: 40px;
  max-width: 480px;
  width: 100%;
  text-align: center;
  box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25);
  position: relative;
  animation: modalScaleUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.modal-success-sparkle {
  font-size: 32px;
  color: var(--editorial-nude-dark);
  display: block;
  margin-bottom: 12px;
}

.modal-success-title {
  font-family: var(--editorial-font-serif);
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 4px;
  margin-bottom: 16px;
  text-transform: uppercase;
}

.modal-success-line {
  width: 40px;
  height: 1px;
  background-color: var(--editorial-black);
  margin: 0 auto 20px auto;
}

.modal-success-desc {
  font-size: 15px;
  margin-bottom: 12px;
  line-height: 1.6;
}

.modal-success-subdesc {
  font-size: 12px;
  color: var(--editorial-text-muted);
  line-height: 1.6;
  margin-bottom: 24px;
}

@keyframes modalScaleUp {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* ——— TRANSITIONS ——— */
.drawer-fade-enter-active, .drawer-fade-leave-active { transition: opacity 0.4s; }
.drawer-fade-enter-from, .drawer-fade-leave-to { opacity: 0; }
.drawer-fade-enter-active .editorial-cart-drawer, .drawer-fade-leave-active .editorial-cart-drawer { transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); }
.drawer-fade-enter-from .editorial-cart-drawer, .drawer-fade-leave-to .editorial-cart-drawer { transform: translateX(100%); }

.modal-scale-enter-active, .modal-scale-leave-active { transition: all 0.4s; }
.modal-scale-enter-from, .modal-scale-leave-to { opacity: 0; }
.modal-scale-enter-active .editorial-quickview-card, .modal-scale-leave-active .editorial-quickview-card,
.modal-scale-enter-active .editorial-checkout-card, .modal-scale-leave-active .editorial-checkout-card { transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-scale-enter-from .editorial-quickview-card, .modal-scale-leave-to .editorial-quickview-card,
.modal-scale-enter-from .editorial-checkout-card, .modal-scale-leave-to .editorial-checkout-card { transform: scale(0.95); }

.toast-fade-enter-active, .toast-fade-leave-active { transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.toast-fade-enter-from, .toast-fade-leave-to { opacity: 0; transform: translate(-50%, 40px); }

/* ——— ANIMACIONES DE ENTRADA PREMIUM ——— */
.animate-fade-in-left {
  animation: fadeInLeft 1.2s cubic-bezier(0.25, 1, 0.5, 1) both;
}
.animate-fade-in-right {
  animation: fadeInRight 1.2s cubic-bezier(0.25, 1, 0.5, 1) both;
}
.animate-fade-in-up {
  animation: fadeInUp 1.2s cubic-bezier(0.25, 1, 0.5, 1) both;
}
.animate-fade-in {
  animation: fadeIn 1s ease both;
}

@keyframes fadeInLeft {
  from {
    opacity: 0;
    transform: translateX(-60px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInRight {
  from {
    opacity: 0;
    transform: translateX(60px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* ——— EFECTO DE CARGA SUAVE DE IMÁGENES (PREVIENE ENTRADA FORZADA) ——— */
.fade-image {
  opacity: 0;
  transition: opacity 0.8s ease-in-out;
}
.fade-image.image-visible {
  opacity: 1 !important;
}

/* ——— REVEAL AL HACER SCROLL (CINEMATIC SCROLL REVEAL) ——— */
.reveal-on-scroll {
  opacity: 0;
  transform: translateY(40px);
  transition: opacity 1.2s cubic-bezier(0.25, 1, 0.5, 1), transform 1.2s cubic-bezier(0.25, 1, 0.5, 1);
}
.reveal-on-scroll.section-revealed {
  opacity: 1;
  transform: translateY(0);
}
</style>
