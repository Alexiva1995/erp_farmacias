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
const mobileMenuOpen = ref(false)
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
  fetchCategories()
  fetchProducts()
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

        <!-- Enlaces minimalistas de navegación central -->
        <div class="nav-links-center d-none d-md-flex">
          <span class="nav-link-item" @click="scrollToCatalog">COMPRAR</span>
          <span class="nav-link-item" @click="selectedCategory = 'maquillaje'; fetchProducts(); scrollToCatalog()">MAQUILLAJE</span>
          <span class="nav-link-item" @click="selectedCategory = 'skin-care'; fetchProducts(); scrollToCatalog()">SKIN CARE</span>
          <span class="nav-link-item" @click="selectedCategory = 'anillos'; fetchProducts(); scrollToCatalog()">JOYERÍA</span>
        </div>

        <!-- Elementos del lado derecho -->
        <div class="nav-actions-right">
          <!-- Input de búsqueda súper minimalista tipo Fenty -->
          <div class="search-editorial-wrap d-none d-sm-block">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="BUSCAR..."
              class="search-editorial-input"
            />
            <span class="search-line-effect"></span>
          </div>

          <button class="cart-btn-editorial" @click="cartDrawer = true">
            <span class="cart-text-btn">BOLSA</span>
            <span v-if="cartTotalItems" class="cart-badge-count">({{ cartTotalItems }})</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- 1. HERO BANNER EDITORIAL (CAMPAÑA INSPIRADA EN FENTY BEAUTY) -->
    <section class="editorial-hero">
      <div class="hero-split-layout">
        <!-- Bloque de Texto de Campaña -->
        <div class="hero-text-block">
          <div class="hero-text-content">
            <span class="hero-tagline">NUEVA COLECCIÓN</span>
            <h1 class="hero-heading-serif">YOUR NEW BOMB NUDES</h1>
            <p class="hero-description-light">
              Tonos sofisticados, texturas sedosas y fórmulas de alta gama diseñadas para realzar tu belleza natural con un acabado impecable de pasarela.
            </p>
            <button class="editorial-btn-dark" @click="scrollToCatalog">
              COMPRAR AHORA
            </button>
          </div>
        </div>
        <!-- Bloque de Imagen de Campaña -->
        <div class="hero-image-block">
          <div class="hero-img-wrap">
            <img 
              src="/resources/js/pages/tova_editorial_campaign_1782228591006.png" 
              alt="TOVA Campaign Model" 
              class="hero-campaign-image"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- SECCIÓN DE CATEGORÍAS EDITORIALES -->
    <section class="editorial-categories-section">
      <div class="section-title-wrap">
        <h2 class="editorial-title-serif">EXPLORAR TOVA</h2>
        <div class="title-decor-line"></div>
      </div>
      <div class="categories-editorial-flex">
        <button
          v-for="cat in categories"
          :key="cat.id"
          class="category-editorial-chip"
          :class="{ 'chip-active': selectedCategory === cat.slug }"
          @click="selectCategory(cat.slug)"
        >
          {{ cat.name.toUpperCase() }}
        </button>
      </div>
    </section>

    <!-- 2. HÍBRIDO 50/50: MEET YOUR MATCH (TINTED MOISTURIZER) -->
    <section class="editorial-campaign-split">
      <div class="split-row">
        <div class="split-col image-col">
          <img 
            src="/resources/js/pages/tova_product_tint_1782228603853.png" 
            alt="TOVA Tinted Moisturizer" 
            class="split-image"
          />
        </div>
        <div class="split-col text-col bg-nude-light">
          <div class="split-text-inner">
            <span class="split-eyebrow">PIEL RADIANTE</span>
            <h2 class="split-heading-serif">MEET YOUR DONE-IN-ONE TINTED MOISTURIZER</h2>
            <p class="split-paragraph">
              Nuestra fórmula ultraligera que unifica el tono de la piel, hidrata profundamente y aporta una luminosidad natural y fresca durante todo el día. Disponible en 25 tonos flexibles.
            </p>
            <button class="editorial-btn-dark-outline" @click="selectedCategory = 'skin-care'; fetchProducts(); scrollToCatalog()">
              DESCUBRIR TONOS
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. PRODUCT GRID: ESTÉTICA TOTALMENTE PLANA (REPLICANDO FENTY) -->
    <section id="catalog" class="editorial-products-section">
      <div class="catalog-header-editorial">
        <h2 class="editorial-title-serif">
          {{ selectedCategory ? categories.find(c => c.slug === selectedCategory)?.name.toUpperCase() || 'COLECCIÓN' : 'NUESTROS FAVORITOS' }}
        </h2>
        <span v-if="selectedCategory" class="editorial-clear-filter" @click="selectedCategory = null; fetchProducts()">
          VER TODOS
        </span>
      </div>

      <!-- Grid de Productos Estilo Editorial -->
      <div v-if="loading" class="editorial-grid">
        <div v-for="n in 4" :key="n" class="editorial-product-skeleton">
          <div class="skeleton-img-flat"></div>
          <div class="skeleton-text-flat line-1"></div>
          <div class="skeleton-text-flat line-2"></div>
        </div>
      </div>

      <div v-else-if="products.length" class="editorial-grid">
        <div
          v-for="product in products"
          :key="product.id"
          class="editorial-product-card"
          @click="openQuickView(product)"
        >
          <!-- Contenedor de Imagen de Producto con fondo grisáceo suave y sin bordes redondos -->
          <div class="editorial-product-img-wrap">
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="editorial-product-image"
              loading="lazy"
            />
            <div v-else class="editorial-product-image-fallback">
              <span class="fallback-logo">TOVA</span>
            </div>
            
            <div class="editorial-card-hover-action">
              <span class="hover-action-label">VISTA RÁPIDA</span>
            </div>
          </div>

          <!-- Información del Producto (Tipografías Refinadas y Alineación Limpia) -->
          <div class="editorial-product-info">
            <span class="editorial-product-brand">{{ product.brand || 'TOVA' }}</span>
            <h3 class="editorial-product-name">{{ product.name.toUpperCase() }}</h3>
            
            <div class="editorial-product-footer">
              <span class="editorial-product-price">{{ formatPrice(product.sale_price) }}</span>
              <span v-if="product.variants?.length" class="editorial-variants-indicator">
                {{ product.variants.length }} TONOS
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Estado Vacío -->
      <div v-else class="editorial-empty-state">
        <p class="empty-message-serif">No se encontraron productos en esta categoría.</p>
        <button class="editorial-btn-dark-outline" @click="selectedCategory = null; fetchProducts()">
          VER TODA LA TIENDA
        </button>
      </div>
    </section>

    <!-- 4. HÍBRIDO 50/50: SUN STALKER BRONZER (INVERSIÓN DE BLOQUES) -->
    <section class="editorial-campaign-split reverse-split">
      <div class="split-row">
        <div class="split-col text-col bg-terracotta-light">
          <div class="split-text-inner">
            <span class="split-eyebrow">EFECTO SOL</span>
            <h2 class="split-heading-serif">SUN STALK'R SOUFFLÉ PRESSED MOUSSE BRONZER</h2>
            <p class="split-paragraph">
              El bronceador definitivo que aporta calidez instantánea a tu rostro con un acabado sedoso y de larga duración. Su textura mousse prensada se funde perfectamente sobre la piel sin esfuerzo.
            </p>
            <button class="editorial-btn-dark-outline" @click="selectedCategory = 'maquillaje'; fetchProducts(); scrollToCatalog()">
              COMPRAR BRONCEADOR
            </button>
          </div>
        </div>
        <div class="split-col image-col">
          <img 
            src="/resources/js/pages/tova_product_bronzer_1782228617577.png" 
            alt="TOVA Bronzer Compact" 
            class="split-image"
          />
        </div>
      </div>
    </section>

    <!-- PIE DE PÁGINA EDITORIAL SOFISTICADO -->
    <footer class="editorial-footer">
      <div class="footer-container">
        <div class="footer-grid">
          <div class="footer-brand-section">
            <h2 class="footer-brand-logo">{{ brandingStore.settings.app_name ? brandingStore.settings.app_name.toUpperCase() : 'TOVA' }}</h2>
            <p class="footer-brand-tagline">BEAUTY & GEMS</p>
            <p class="footer-brand-desc">
              Una estética editorial y fórmulas de lujo pensadas para redefinir el estándar de la cosmética moderna y la joyería de autor.
            </p>
          </div>
          <div class="footer-links-section">
            <h4 class="footer-section-title">SERVICIOS</h4>
            <span class="footer-link">AYUDA Y SOPORTE</span>
            <span class="footer-link">ENVÍOS Y DEVOLUCIONES</span>
            <span class="footer-link">ENCUENTRA TU TONO</span>
          </div>
          <div class="footer-links-section">
            <h4 class="footer-section-title">COMPAÑÍA</h4>
            <span class="footer-link">SOBRE TOVA</span>
            <span class="footer-link">NUESTRA FILOSOFÍA</span>
            <span class="footer-link">CONTACTO</span>
          </div>
        </div>
        <div class="footer-bottom-bar">
          <p class="copyright-text">
            © {{ new Date().getFullYear() }} {{ brandingStore.settings.app_name ? brandingStore.settings.app_name.toUpperCase() : 'TOVA' }}. Todos los derechos reservados | Diseñado y Desarrollado por 
            <a href="https://tovaerp.com/" target="_blank" style="color: var(--editorial-nude-dark); text-decoration: none; font-weight: 600;">Tova tu Cerebro Operativo</a>
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
              <span class="qv-brand-tag">{{ selectedProduct.brand || 'TOVA' }}</span>
              <h2 class="qv-title-serif">{{ selectedProduct.name.toUpperCase() }}</h2>
              <p class="qv-desc-light">{{ selectedProduct.description || 'Producto de alta gama formulado con los mejores ingredientes de la colección TOVA.' }}</p>
              <p class="qv-price-bold">{{ formatPrice(productPrice(selectedProduct, selectedVariant)) }}</p>

              <!-- Variantes de Tonos / Tamaños -->
              <div v-if="selectedProduct.variants?.length" class="qv-variants-editorial">
                <p class="qv-variants-title">SELECCIONAR TONO / VARIANTE:</p>
                <div class="qv-variants-editorial-flex">
                  <button
                    v-for="v in selectedProduct.variants"
                    :key="v.id"
                    class="qv-variant-editorial-chip"
                    :class="{ 'qv-chip-active': selectedVariant?.id === v.id }"
                    @click="selectedVariant = v"
                  >
                    {{ v.attribute_value.toUpperCase() }}
                  </button>
                </div>
              </div>

              <button class="editorial-btn-dark w-100 py-3 mt-4" @click="quickAddToCart">
                AÑADIR A LA BOLSA
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

    <!-- ====== SUCCESS MESSAGE (TOAST EDITORIAL) ====== -->
    <transition name="toast-fade">
      <div v-if="orderSuccess" class="editorial-success-toast">
        <span class="toast-sparkle">✦</span>
        <div class="toast-content">
          <p class="toast-title">¡PEDIDO CONFIRMADO!</p>
          <p class="toast-desc">La orden #{{ lastOrderId }} ha sido procesada con éxito.</p>
        </div>
        <button class="toast-close" @click="orderSuccess = false">✕</button>
      </div>
    </transition>
  </div>
</template>

<style>
/* ==========================================================================
   ESTÉTICA EDITORIAL DE ALTA GAMA — TOVA BEAUTY & GEMS (INSPIRADO EN FENTY)
   ========================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Montserrat:wght@200;300;400;500;600;700&display=swap');

.tova-editorial-root {
  /* Variables de Color e Identidad */
  --editorial-black: #0F0E0E;
  --editorial-white: #FFFFFF;
  --editorial-grey-bg: #F6F6F6;
  --editorial-border: #E8E8E8;
  --editorial-nude-dark: #A38A78;
  --editorial-nude-light: #F2ECE7;
  --editorial-terracotta-light: #EADCD0;
  --editorial-text-muted: #666666;
  --editorial-font-serif: 'Cinzel', Georgia, serif;
  --editorial-font-sans: 'Montserrat', sans-serif;

  min-height: 100vh;
  background-color: var(--editorial-white);
  color: var(--editorial-black);
  font-family: var(--editorial-font-sans);
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
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
.nav-link-item {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 2px;
  cursor: pointer;
  position: relative;
  padding: 4px 0;
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
  background-color: #2F241E; /* Sofisticado tono cacao profundo */
  color: var(--editorial-white);
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
  font-weight: 600;
  letter-spacing: 4px;
  color: var(--editorial-nude-dark);
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
}
.hero-description-light {
  font-size: 14px;
  font-weight: 300;
  line-height: 1.8;
  opacity: 0.85;
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
  background-color: var(--editorial-black);
  color: var(--editorial-white);
  border: none;
  padding: 16px 40px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 3px;
  cursor: pointer;
  transition: background-color 0.3s;
}
.editorial-btn-dark:hover:not(:disabled) {
  background-color: #333333;
}
.editorial-btn-dark:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.editorial-btn-dark-outline {
  background-color: transparent;
  color: var(--editorial-black);
  border: 1px solid var(--editorial-black);
  padding: 16px 40px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 3px;
  cursor: pointer;
  transition: all 0.3s;
}
.editorial-btn-dark-outline:hover {
  background-color: var(--editorial-black);
  color: var(--editorial-white);
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
  background: transparent;
  border: 1px solid var(--editorial-border);
  color: var(--editorial-black);
  padding: 10px 24px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 2px;
  cursor: pointer;
  transition: all 0.3s;
}
.category-editorial-chip:hover,
.category-editorial-chip.chip-active {
  border-color: var(--editorial-black);
  background-color: var(--editorial-black);
  color: var(--editorial-white);
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
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 4px;
  color: var(--editorial-nude-dark);
  display: block;
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
  line-height: 1.8;
  color: var(--editorial-text-muted);
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

.editorial-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 40px 24px;
}

/* Tarjeta de Producto Totalmente Plana (Inspiración Fenty) */
.editorial-product-card {
  cursor: pointer;
  display: flex;
  flex-direction: column;
}
.editorial-product-img-wrap {
  position: relative;
  aspect-ratio: 1;
  background-color: var(--editorial-grey-bg);
  overflow: hidden;
  border: 1px solid #EDEDED; /* Borde imperceptible súper sutil */
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
  align-items: center;
  justify-content: center;
  background-color: var(--editorial-grey-bg);
}
.fallback-logo {
  font-family: var(--editorial-font-serif);
  font-size: 24px;
  letter-spacing: 6px;
  opacity: 0.15;
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
  font-size: 14px;
  font-weight: 600;
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
  aspect-ratio: 1;
}
.editorial-qv-img {
  width: 100%; height: 100%; object-fit: cover; display: block;
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
.qv-variant-editorial-chip {
  background-color: transparent;
  border: 1px solid var(--editorial-border);
  color: var(--editorial-black);
  padding: 8px 18px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1px;
  cursor: pointer;
  transition: all 0.3s;
}
.qv-variant-editorial-chip:hover,
.qv-variant-editorial-chip.qv-chip-active {
  border-color: var(--editorial-black);
  background-color: var(--editorial-black);
  color: var(--editorial-white);
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

/* ——— Success Toast ——— */
.editorial-success-toast {
  position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 2000;
  background-color: var(--editorial-black); color: var(--editorial-white);
  padding: 20px 30px; display: flex; align-items: center; gap: 20px;
  box-shadow: 0 30px 60px rgba(0,0,0,0.3); min-width: 320px; max-width: 520px;
}
.toast-sparkle {
  font-size: 24px; color: var(--editorial-nude-dark);
}
.toast-title {
  font-size: 12px; font-weight: 700; letter-spacing: 2px; margin-bottom: 4px;
}
.toast-desc {
  font-size: 12px; opacity: 0.8;
}
.toast-close {
  background: none; border: none; color: #888; font-size: 16px; cursor: pointer; margin-left: auto;
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
</style>
