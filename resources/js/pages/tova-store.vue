<script setup>
definePage({
  meta: {
    layout: 'blank',
    public: true,
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

// Busqueda con debounce
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
  // Mini feedback visual
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

const scrollToCatalog = () => {
  const el = document.getElementById('catalog')
  if (el) el.scrollIntoView({ behavior: 'smooth' })
}

onMounted(() => {
  fetchCategories()
  fetchProducts()
})
</script>

<template>
  <div class="tova-root">
    <!-- NAV -->
    <nav class="tova-nav">
      <div class="tova-nav-inner">
        <!-- Logo -->
        <div class="tova-logo">
          <span class="tova-logo-icon">✦</span>
          <span class="tova-logo-text">TOVA</span>
          <span class="tova-logo-sub">Beauty & Gems</span>
        </div>

        <!-- Search (desktop) -->
        <div class="tova-search-wrap">
          <span class="search-icon">⊕</span>
          <input
            v-model="searchQuery"
            type="text"
            class="tova-search"
            placeholder="Buscar productos..."
          />
        </div>

        <!-- Cart -->
        <button class="tova-cart-btn" @click="cartDrawer = true">
          <span class="cart-icon">🛍</span>
          <span v-if="cartTotalItems" class="cart-badge">{{ cartTotalItems }}</span>
        </button>
      </div>
    </nav>

    <!-- HERO BANNER -->
    <section class="tova-hero">
      <div class="hero-particles">
        <div v-for="n in 12" :key="n" class="hero-particle" :style="`--i: ${n}`"></div>
      </div>
      <div class="hero-content">
        <p class="hero-eyebrow">Nueva colección</p>
        <h1 class="hero-title">
          Belleza que<br>
          <span class="hero-title-accent">te define</span>
        </h1>
        <p class="hero-subtitle">Joyería artesanal, cosméticos premium y cuidado personal para tu rutina perfecta.</p>
        <div class="hero-actions">
          <button class="btn-primary" @click="scrollToCatalog">
            Ver Colección
          </button>
          <button class="btn-ghost">Conoce TOVA</button>
        </div>
      </div>
      <div class="hero-decoration">
        <div class="hero-ring ring-1"></div>
        <div class="hero-ring ring-2"></div>
        <div class="hero-ring ring-3"></div>
        <div class="hero-glow"></div>
      </div>
    </section>

    <!-- CATEGORÍAS -->
    <section class="tova-categories">
      <div class="section-header">
        <h2 class="section-title">Explora por categoría</h2>
        <p class="section-sub">Encuentra exactamente lo que buscas</p>
      </div>
      <div class="categories-grid">
        <button
          v-for="(cat, i) in categories"
          :key="cat.id"
          class="category-card"
          :class="{ active: selectedCategory === cat.slug }"
          :style="`--gradient: ${categoryGradients[i % categoryGradients.length]}`"
          @click="selectCategory(cat.slug)"
        >
          <div class="category-icon-wrap">
            <i :class="getCategoryIcon(cat.slug)" class="category-icon-svg"></i>
          </div>
          <span class="category-name">{{ cat.name }}</span>
          <span v-if="cat.products_count" class="category-count">{{ cat.products_count }} items</span>
        </button>
        <!-- Skeleton si no hay categorías -->
        <template v-if="!categories.length">
          <div v-for="n in 6" :key="n" class="category-card skeleton-card"></div>
        </template>
      </div>
    </section>

    <!-- CATÁLOGO -->
    <section id="catalog" class="tova-catalog">
      <div class="catalog-header">
        <h2 class="section-title">
          {{ selectedCategory ? categories.find(c => c.slug === selectedCategory)?.name || 'Colección' : 'Todos los productos' }}
          <span v-if="selectedCategory" class="clear-filter" @click="selectedCategory = null; fetchProducts()">✕ Limpiar</span>
        </h2>
        <p class="catalog-count">{{ products.length }} productos encontrados</p>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="products-grid">
        <div v-for="n in 8" :key="n" class="product-card skeleton-card">
          <div class="skeleton-img"></div>
          <div class="skeleton-body">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line medium"></div>
          </div>
        </div>
      </div>

      <!-- Productos -->
      <div v-else-if="products.length" class="products-grid">
        <div
          v-for="product in products"
          :key="product.id"
          class="product-card"
          @click="openQuickView(product)"
        >
          <!-- Imagen -->
          <div class="product-img-wrap">
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="product-img"
              loading="lazy"
            />
            <div v-else class="product-img-placeholder">
              <i :class="getCategoryIcon(product.category?.slug)" class="placeholder-icon"></i>
            </div>

            <!-- Badge -->
            <span v-if="product.category?.name" class="product-badge">{{ product.category.name }}</span>

            <!-- Hover overlay -->
            <div class="product-hover-overlay">
              <button class="quick-view-btn" @click.stop="openQuickView(product)">Vista rápida</button>
              <button
                class="add-cart-btn"
                @click.stop="addToCart(product)"
              >
                <span>+</span> Añadir
              </button>
            </div>
          </div>

          <!-- Info -->
          <div class="product-info">
            <p class="product-brand">{{ product.brand || 'TOVA Collection' }}</p>
            <h3 class="product-name">{{ product.name }}</h3>
            <div class="product-footer">
              <span class="product-price">{{ formatPrice(product.sale_price) }}</span>
              <div class="product-variants-count" v-if="product.variants?.length">
                {{ product.variants.length }} variante{{ product.variants.length > 1 ? 's' : '' }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="catalog-empty">
        <div class="empty-icon">🔍</div>
        <h3 class="empty-title">Sin resultados</h3>
        <p class="empty-sub">Intenta con otra búsqueda o categoría.</p>
        <button class="btn-primary" @click="searchQuery = ''; selectedCategory = null; fetchProducts()">
          Ver todo
        </button>
      </div>
    </section>

    <!-- FEATURES STRIP -->
    <section class="tova-features">
      <div class="feature-item">
        <span class="feature-icon">🚚</span>
        <div>
          <p class="feature-title">Envío rápido</p>
          <p class="feature-sub">A todo el país</p>
        </div>
      </div>
      <div class="feature-item">
        <span class="feature-icon">✅</span>
        <div>
          <p class="feature-title">Calidad garantizada</p>
          <p class="feature-sub">Productos auténticos</p>
        </div>
      </div>
      <div class="feature-item">
        <span class="feature-icon">🔒</span>
        <div>
          <p class="feature-title">Pago seguro</p>
          <p class="feature-sub">Transacciones protegidas</p>
        </div>
      </div>
      <div class="feature-item">
        <span class="feature-icon">💬</span>
        <div>
          <p class="feature-title">Soporte 24/7</p>
          <p class="feature-sub">Siempre para ti</p>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="tova-footer">
      <div class="tova-logo" style="justify-content:center; margin-bottom: 12px;">
        <span class="tova-logo-icon">✦</span>
        <span class="tova-logo-text">TOVA</span>
      </div>
      <p class="footer-copy">© {{ new Date().getFullYear() }} TOVA Beauty & Gems. Todos los derechos reservados.</p>
    </footer>

    <!-- ====== CARRITO LATERAL (DRAWER) ====== -->
    <transition name="drawer">
      <div v-if="cartDrawer" class="cart-drawer-overlay" @click.self="cartDrawer = false">
        <div class="cart-drawer">
          <div class="cart-drawer-header">
            <h2 class="cart-title">Tu carrito <span class="cart-badge-lg">{{ cartTotalItems }}</span></h2>
            <button class="close-btn" @click="cartDrawer = false">✕</button>
          </div>

          <!-- Items -->
          <div class="cart-items" v-if="cart.length">
            <div v-for="item in cart" :key="item.cartKey" class="cart-item">
              <div class="cart-item-img">
                <img v-if="item.product.image_url" :src="item.product.image_url" :alt="item.product.name" />
                <span v-else class="cart-item-placeholder">✦</span>
              </div>
              <div class="cart-item-info">
                <p class="cart-item-name">{{ item.product.name }}</p>
                <p v-if="item.variant" class="cart-item-variant">{{ item.variant.attribute_value }}</p>
                <p class="cart-item-price">{{ formatPrice(productPrice(item.product, item.variant)) }}</p>
              </div>
              <div class="cart-item-qty">
                <button @click="updateQty(item.cartKey, -1)">−</button>
                <span>{{ item.quantity }}</span>
                <button @click="updateQty(item.cartKey, 1)">+</button>
              </div>
              <button class="cart-item-remove" @click="removeFromCart(item.cartKey)">✕</button>
            </div>
          </div>
          <div v-else class="cart-empty">
            <div class="cart-empty-icon">🛍</div>
            <p>Tu carrito está vacío</p>
          </div>

          <!-- Totales + CTA -->
          <div class="cart-footer" v-if="cart.length">
            <div class="cart-summary">
              <span>Subtotal</span>
              <span class="cart-total-price">{{ formatPrice(cartTotalPrice) }}</span>
            </div>
            <button class="btn-checkout" @click="cartDrawer = false; orderDialog = true">
              Proceder al pago →
            </button>
            <button class="btn-clear" @click="clearCart">Vaciar carrito</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ====== QUICK VIEW DIALOG ====== -->
    <transition name="modal">
      <div v-if="quickViewDialog && selectedProduct" class="modal-overlay" @click.self="quickViewDialog = false">
        <div class="modal-card quick-view-modal">
          <button class="modal-close" @click="quickViewDialog = false">✕</button>

          <div class="qv-layout">
            <!-- Imagen -->
            <div class="qv-img-wrap">
              <img v-if="selectedProduct.image_url" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="qv-img" />
              <div v-else class="qv-img-placeholder">✦</div>
            </div>

            <!-- Detalles -->
            <div class="qv-info">
              <p class="qv-category">{{ selectedProduct.category?.name || 'TOVA Collection' }}</p>
              <h2 class="qv-title">{{ selectedProduct.name }}</h2>
              <p class="qv-description">{{ selectedProduct.description || 'Producto de alta calidad de la colección TOVA.' }}</p>
              <p class="qv-price">{{ formatPrice(productPrice(selectedProduct, selectedVariant)) }}</p>

              <!-- Variantes -->
              <div v-if="selectedProduct.variants?.length" class="qv-variants">
                <p class="qv-variants-label">Selecciona variante:</p>
                <div class="qv-variants-list">
                  <button
                    v-for="v in selectedProduct.variants"
                    :key="v.id"
                    class="variant-chip"
                    :class="{ 'variant-chip--active': selectedVariant?.id === v.id }"
                    @click="selectedVariant = v"
                  >
                    {{ v.attribute_value }}
                    <span v-if="Number(v.price_modifier) > 0" class="variant-mod">+{{ formatPrice(v.price_modifier) }}</span>
                  </button>
                </div>
              </div>

              <button class="btn-primary w-full" @click="quickAddToCart">
                🛍 Añadir al carrito
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ====== CHECKOUT DIALOG ====== -->
    <transition name="modal">
      <div v-if="orderDialog" class="modal-overlay" @click.self="orderDialog = false">
        <div class="modal-card checkout-modal">
          <button class="modal-close" @click="orderDialog = false">✕</button>
          <h2 class="modal-title">Finalizar compra</h2>

          <!-- Resumen del pedido -->
          <div class="order-summary">
            <h3 class="summary-title">Resumen del pedido</h3>
            <div v-for="item in cart" :key="item.cartKey" class="summary-item">
              <span class="summary-item-name">{{ item.product.name }}<span v-if="item.variant"> ({{ item.variant.attribute_value }})</span></span>
              <span>x{{ item.quantity }}</span>
              <span class="summary-item-price">{{ formatPrice(productPrice(item.product, item.variant) * item.quantity) }}</span>
            </div>
            <div class="summary-total">
              <span>Total</span>
              <span class="total-price">{{ formatPrice(cartTotalPrice) }}</span>
            </div>
          </div>

          <!-- Formulario -->
          <div class="checkout-form">
            <h3 class="summary-title">Tus datos</h3>
            <div class="form-grid">
              <div class="form-group">
                <label>Nombre completo *</label>
                <input v-model="orderForm.customer_name" type="text" placeholder="María García" required />
              </div>
              <div class="form-group">
                <label>Teléfono *</label>
                <input v-model="orderForm.customer_phone" type="tel" placeholder="+58 412 000 0000" required />
              </div>
              <div class="form-group">
                <label>Email</label>
                <input v-model="orderForm.customer_email" type="email" placeholder="correo@ejemplo.com" />
              </div>
              <div class="form-group">
                <label>Dirección de entrega</label>
                <input v-model="orderForm.shipping_address" type="text" placeholder="Calle, edificio, ciudad..." />
              </div>
              <div class="form-group form-group--full">
                <label>Notas adicionales</label>
                <textarea v-model="orderForm.notes" placeholder="Instrucciones especiales..." rows="2"></textarea>
              </div>
            </div>
          </div>

          <button
            class="btn-checkout"
            :disabled="!orderFormValid || orderSubmitting"
            @click="submitOrder"
          >
            <span v-if="orderSubmitting">Procesando...</span>
            <span v-else>Confirmar pedido · {{ formatPrice(cartTotalPrice) }}</span>
          </button>
        </div>
      </div>
    </transition>

    <!-- ====== SUCCESS SNACK ====== -->
    <transition name="snack">
      <div v-if="orderSuccess" class="order-success-snack">
        <span class="snack-icon">🎉</span>
        <div>
          <p class="snack-title">¡Pedido confirmado!</p>
          <p class="snack-sub">Orden #{{ lastOrderId }} registrada correctamente.</p>
        </div>
        <button class="snack-close" @click="orderSuccess = false">✕</button>
      </div>
    </transition>
  </div>
</template>

<style>
/* ============================================
   TOVA STORE — DISEÑO PREMIUM
   ============================================ */

.tova-root {
  --tova-pink: #e91e8c;
  --tova-rose: #ff6bb5;
  --tova-deep: #8b005e;
  --tova-gold: #f5c842;
  --tova-dark: #0d0d1a;
  --tova-dark2: #13131f;
  --tova-surface: #1a1a2e;
  --tova-surface2: #16213e;
  --tova-border: rgba(233, 30, 140, 0.15);
  --tova-text: #f0e6f6;
  --tova-muted: rgba(240, 230, 246, 0.5);
  --tova-radius: 18px;
  --tova-radius-sm: 10px;
  min-height: 100vh;
  min-height: 100dvh;
  width: 100%;
  background: var(--tova-dark) !important;
  color: var(--tova-text) !important;
  font-family: 'Inter', system-ui, sans-serif;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}

/* ——— NAV ——— */
.tova-nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  background: rgba(13, 13, 26, 0.85);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--tova-border);
}
.tova-nav-inner {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
  height: 64px;
  display: flex;
  align-items: center;
  gap: 24px;
}

.tova-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex-shrink: 0;
}
.tova-logo-icon {
  font-size: 22px;
  color: var(--tova-pink);
  animation: spin-slow 6s linear infinite;
}
.tova-logo-text {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: 3px;
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.tova-logo-sub {
  font-size: 10px;
  color: var(--tova-muted);
  letter-spacing: 1px;
  margin-top: 2px;
}

.tova-search-wrap {
  flex: 1;
  max-width: 480px;
  position: relative;
}
.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--tova-muted);
  font-size: 18px;
}
.tova-search {
  width: 100%;
  background: rgba(255,255,255,0.05);
  border: 1px solid var(--tova-border);
  border-radius: 40px;
  padding: 10px 18px 10px 42px;
  color: var(--tova-text);
  font-size: 14px;
  outline: none;
  transition: border-color 0.3s;
}
.tova-search::placeholder { color: var(--tova-muted); }
.tova-search:focus { border-color: var(--tova-pink); }

.tova-cart-btn {
  position: relative;
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  border: none;
  border-radius: 50%;
  width: 44px; height: 44px;
  font-size: 20px;
  cursor: pointer;
  transition: transform 0.2s;
  flex-shrink: 0;
}
.tova-cart-btn:hover { transform: scale(1.08); }
.cart-icon { line-height: 1; }
.cart-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: var(--tova-gold);
  color: #000;
  font-size: 11px;
  font-weight: 700;
  border-radius: 50%;
  min-width: 20px; height: 20px;
  display: flex; align-items: center; justify-content: center;
  padding: 2px;
}

/* ——— HERO ——— */
.tova-hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  padding: 80px 24px 60px;
  overflow: hidden;
  background: radial-gradient(ellipse at 70% 50%, rgba(233,30,140,0.12) 0%, transparent 60%),
              radial-gradient(ellipse at 30% 80%, rgba(139,0,94,0.1) 0%, transparent 50%);
}

.hero-content {
  max-width: 1280px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.hero-eyebrow {
  font-size: 12px;
  letter-spacing: 4px;
  text-transform: uppercase;
  color: var(--tova-pink);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.hero-eyebrow::before {
  content: '';
  display: inline-block;
  width: 40px; height: 1px;
  background: var(--tova-pink);
}

.hero-title {
  font-size: clamp(48px, 8vw, 90px);
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -2px;
  margin-bottom: 24px;
}
.hero-title-accent {
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose), var(--tova-gold));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-subtitle {
  font-size: 18px;
  color: var(--tova-muted);
  max-width: 520px;
  line-height: 1.7;
  margin-bottom: 40px;
}

.hero-actions {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

/* Botones globales */
.btn-primary {
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  color: #fff;
  border: none;
  padding: 14px 32px;
  border-radius: 40px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 8px 24px rgba(233,30,140,0.3);
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 32px rgba(233,30,140,0.45);
}
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary.w-full { width: 100%; }

.btn-ghost {
  background: transparent;
  color: var(--tova-text);
  border: 1px solid rgba(255,255,255,0.2);
  padding: 14px 32px;
  border-radius: 40px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-ghost:hover { border-color: var(--tova-pink); color: var(--tova-pink); }

/* Hero decoracion */
.hero-decoration {
  position: absolute;
  right: -100px;
  top: 50%;
  transform: translateY(-50%);
  width: 600px; height: 600px;
  pointer-events: none;
}
.hero-ring {
  position: absolute;
  border-radius: 50%;
  border: 1px solid var(--tova-border);
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
}
.ring-1 { width: 200px; height: 200px; border-color: rgba(233,30,140,0.3); animation: ring-pulse 3s ease-in-out infinite; }
.ring-2 { width: 350px; height: 350px; border-color: rgba(233,30,140,0.2); animation: ring-pulse 3s ease-in-out infinite 1s; }
.ring-3 { width: 500px; height: 500px; border-color: rgba(233,30,140,0.1); animation: ring-pulse 3s ease-in-out infinite 2s; }
.hero-glow {
  position: absolute;
  width: 180px; height: 180px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(233,30,140,0.4), transparent);
  top: 50%; left: 50%;
  transform: translate(-50%,-50%);
  animation: glow-pulse 2s ease-in-out infinite;
}

/* Hero particles */
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
  position: absolute;
  width: 3px; height: 3px;
  border-radius: 50%;
  background: var(--tova-pink);
  opacity: 0;
  left: calc(var(--i) * 8.33%);
  top: calc(var(--i) * 7%);
  animation: particle-float 4s ease-in-out infinite calc(var(--i) * 0.3s);
}

/* ——— CATEGORÍAS ——— */
.tova-categories {
  max-width: 1280px;
  margin: 0 auto;
  padding: 80px 24px 40px;
}

.section-header { margin-bottom: 40px; }
.section-title {
  font-size: clamp(28px, 4vw, 42px);
  font-weight: 800;
  letter-spacing: -1px;
  margin-bottom: 8px;
}
.section-sub { color: var(--tova-muted); font-size: 16px; }

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 16px;
}

.category-card {
  background: var(--tova-surface);
  border: 1px solid var(--tova-border);
  border-radius: var(--tova-radius);
  padding: 28px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
  overflow: hidden;
}
.category-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--gradient);
  opacity: 0;
  transition: opacity 0.3s;
}
.category-card:hover::before,
.category-card.active::before { opacity: 0.12; }
.category-card:hover, .category-card.active {
  border-color: var(--tova-pink);
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(233,30,140,0.2);
}
.category-icon-wrap {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: var(--gradient, linear-gradient(135deg, var(--tova-pink), var(--tova-rose)));
  display: flex; align-items: center; justify-content: center;
  font-size: 24px;
}
.category-icon-svg { font-size: 24px; }
.category-name { font-size: 13px; font-weight: 600; letter-spacing: 0.5px; }
.category-count { font-size: 11px; color: var(--tova-muted); }

/* ——— CATÁLOGO ——— */
.tova-catalog {
  max-width: 1280px;
  margin: 0 auto;
  padding: 40px 24px 80px;
}

.catalog-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 32px;
}
.catalog-count { font-size: 14px; color: var(--tova-muted); }
.clear-filter {
  font-size: 13px;
  color: var(--tova-pink);
  cursor: pointer;
  margin-left: 12px;
  font-weight: 500;
}
.clear-filter:hover { text-decoration: underline; }

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 24px;
}

/* Product card */
.product-card {
  background: var(--tova-surface);
  border: 1px solid var(--tova-border);
  border-radius: var(--tova-radius);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.product-card:hover {
  transform: translateY(-8px);
  border-color: rgba(233,30,140,0.4);
  box-shadow: 0 20px 50px rgba(233,30,140,0.15);
}

.product-img-wrap {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--tova-surface2);
}
.product-img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.product-card:hover .product-img { transform: scale(1.08); }

.product-img-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--tova-surface), var(--tova-surface2));
}
.placeholder-icon { font-size: 48px; opacity: 0.3; color: var(--tova-pink); }

.product-badge {
  position: absolute;
  top: 12px; left: 12px;
  background: rgba(233,30,140,0.9);
  backdrop-filter: blur(8px);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 20px;
}

.product-hover-overlay {
  position: absolute;
  inset: 0;
  background: rgba(13,13,26,0.8);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  opacity: 0;
  transition: opacity 0.3s;
}
.product-card:hover .product-hover-overlay { opacity: 1; }

.quick-view-btn {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  padding: 8px 20px;
  border-radius: 30px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}
.quick-view-btn:hover { background: rgba(255,255,255,0.2); }

.add-cart-btn {
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  border: none;
  color: #fff;
  padding: 10px 24px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.add-cart-btn:hover { transform: scale(1.05); }

.product-info { padding: 16px 20px 20px; }
.product-brand { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--tova-pink); margin-bottom: 6px; }
.product-name {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.product-footer { display: flex; align-items: center; justify-content: space-between; }
.product-price {
  font-size: 20px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.product-variants-count { font-size: 11px; color: var(--tova-muted); }

/* Empty state */
.catalog-empty {
  text-align: center;
  padding: 80px 24px;
}
.empty-icon { font-size: 60px; margin-bottom: 20px; }
.empty-title { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
.empty-sub { color: var(--tova-muted); margin-bottom: 24px; }

/* ——— FEATURES ——— */
.tova-features {
  background: var(--tova-surface);
  border-top: 1px solid var(--tova-border);
  border-bottom: 1px solid var(--tova-border);
  display: flex;
  justify-content: center;
  gap: 0;
  flex-wrap: wrap;
}
.feature-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 32px 48px;
  border-right: 1px solid var(--tova-border);
  flex: 1;
  min-width: 200px;
}
.feature-item:last-child { border-right: none; }
.feature-icon { font-size: 32px; }
.feature-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
.feature-sub { font-size: 13px; color: var(--tova-muted); }

/* ——— FOOTER ——— */
.tova-footer {
  text-align: center;
  padding: 40px 24px;
  background: var(--tova-dark);
}
.footer-copy { font-size: 13px; color: var(--tova-muted); }

/* ——— SKELETONS ——— */
.skeleton-card { pointer-events: none; }
.skeleton-img {
  width: 100%; aspect-ratio: 1;
  background: linear-gradient(90deg, var(--tova-surface) 25%, rgba(255,255,255,0.04) 50%, var(--tova-surface) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.skeleton-body { padding: 16px; }
.skeleton-line {
  height: 12px; border-radius: 6px; margin-bottom: 10px;
  background: linear-gradient(90deg, var(--tova-surface) 25%, rgba(255,255,255,0.04) 50%, var(--tova-surface) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.skeleton-line.short { width: 40%; }
.skeleton-line.medium { width: 60%; }

/* ——— CARRITO DRAWER ——— */
.cart-drawer-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
}
.cart-drawer {
  position: absolute;
  right: 0; top: 0; bottom: 0;
  width: min(420px, 95vw);
  background: var(--tova-surface);
  border-left: 1px solid var(--tova-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.cart-drawer-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 24px;
  border-bottom: 1px solid var(--tova-border);
}
.cart-title { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
.cart-badge-lg {
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  color: #fff;
  border-radius: 20px;
  padding: 2px 10px;
  font-size: 13px;
}
.close-btn {
  background: none; border: none;
  color: var(--tova-muted);
  font-size: 20px;
  cursor: pointer;
  width: 36px; height: 36px;
  border-radius: 50%;
  transition: all 0.2s;
}
.close-btn:hover { background: rgba(255,255,255,0.08); color: #fff; }

.cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.cart-item {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--tova-surface2);
  border-radius: var(--tova-radius-sm);
  padding: 12px;
}
.cart-item-img {
  width: 60px; height: 60px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
  background: var(--tova-dark);
  display: flex; align-items: center; justify-content: center;
}
.cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
.cart-item-placeholder { font-size: 24px; color: var(--tova-pink); }
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-item-variant { font-size: 11px; color: var(--tova-muted); }
.cart-item-price { font-size: 14px; font-weight: 700; color: var(--tova-pink); margin-top: 2px; }

.cart-item-qty {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--tova-dark);
  border-radius: 20px;
  padding: 4px 8px;
}
.cart-item-qty button {
  background: none; border: none;
  color: var(--tova-text);
  font-size: 18px;
  cursor: pointer;
  width: 24px; height: 24px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
.cart-item-qty button:hover { background: rgba(255,255,255,0.1); }
.cart-item-qty span { font-size: 14px; font-weight: 700; min-width: 20px; text-align: center; }

.cart-item-remove {
  background: none; border: none;
  color: var(--tova-muted);
  font-size: 14px;
  cursor: pointer;
  transition: color 0.2s;
}
.cart-item-remove:hover { color: #ff6b6b; }

.cart-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
.cart-empty-icon { font-size: 60px; opacity: 0.5; }
.cart-empty p { color: var(--tova-muted); }

.cart-footer {
  padding: 20px;
  border-top: 1px solid var(--tova-border);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.cart-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 15px;
}
.cart-total-price { font-size: 24px; font-weight: 800; color: var(--tova-pink); }
.btn-checkout {
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  color: #fff;
  border: none;
  padding: 16px;
  border-radius: 40px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 8px 24px rgba(233,30,140,0.35);
}
.btn-checkout:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(233,30,140,0.5); }
.btn-checkout:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-clear {
  background: none;
  border: 1px solid var(--tova-border);
  color: var(--tova-muted);
  padding: 10px;
  border-radius: 40px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-clear:hover { border-color: #ff6b6b; color: #ff6b6b; }

/* ——— MODALES ——— */
.modal-overlay {
  position: fixed; inset: 0; z-index: 1001;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal-card {
  background: var(--tova-surface);
  border: 1px solid var(--tova-border);
  border-radius: 24px;
  position: relative;
  box-shadow: 0 40px 80px rgba(0,0,0,0.5);
  max-height: 90vh;
  overflow-y: auto;
  width: 100%;
}
.modal-close {
  position: sticky;
  top: 16px; right: 16px;
  float: right;
  background: rgba(255,255,255,0.1);
  border: none;
  color: #fff;
  width: 36px; height: 36px;
  border-radius: 50%;
  font-size: 16px;
  cursor: pointer;
  z-index: 10;
  transition: background 0.2s;
}
.modal-close:hover { background: rgba(255,255,255,0.2); }
.modal-title {
  font-size: 22px;
  font-weight: 800;
  padding: 24px 24px 0;
  margin-bottom: 20px;
}

/* Quick View Modal */
.quick-view-modal { max-width: 800px; }
.qv-layout { display: grid; grid-template-columns: 1fr 1fr; min-height: 400px; }
@media (max-width: 640px) { .qv-layout { grid-template-columns: 1fr; } }
.qv-img-wrap { background: var(--tova-surface2); border-radius: 24px 0 0 24px; overflow: hidden; }
@media (max-width: 640px) { .qv-img-wrap { border-radius: 24px 24px 0 0; min-height: 260px; } }
.qv-img { width: 100%; height: 100%; object-fit: cover; }
.qv-img-placeholder {
  width: 100%; height: 100%; min-height: 400px;
  display: flex; align-items: center; justify-content: center;
  font-size: 80px; color: var(--tova-pink); opacity: 0.3;
}
.qv-info { padding: 32px; display: flex; flex-direction: column; gap: 16px; }
.qv-category { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--tova-pink); }
.qv-title { font-size: 24px; font-weight: 800; line-height: 1.3; }
.qv-description { font-size: 14px; color: var(--tova-muted); line-height: 1.7; }
.qv-price {
  font-size: 32px; font-weight: 900;
  background: linear-gradient(135deg, var(--tova-pink), var(--tova-rose));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.qv-variants-label { font-size: 13px; font-weight: 600; margin-bottom: 10px; }
.qv-variants-list { display: flex; flex-wrap: wrap; gap: 8px; }
.variant-chip {
  background: var(--tova-surface2);
  border: 1px solid var(--tova-border);
  color: var(--tova-text);
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}
.variant-chip:hover { border-color: var(--tova-pink); }
.variant-chip--active { background: rgba(233,30,140,0.15); border-color: var(--tova-pink); color: var(--tova-pink); }
.variant-mod { font-size: 11px; color: var(--tova-muted); margin-left: 4px; }

/* Checkout Modal */
.checkout-modal { max-width: 640px; padding: 24px; }
.order-summary, .checkout-form { margin-bottom: 24px; }
.summary-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: var(--tova-muted); text-transform: uppercase; letter-spacing: 1px; font-size: 12px; }
.summary-item { display: flex; gap: 12px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--tova-border); font-size: 14px; }
.summary-item-name { flex: 1; }
.summary-item-price { font-weight: 700; color: var(--tova-pink); }
.summary-total { display: flex; justify-content: space-between; padding-top: 16px; font-weight: 700; font-size: 16px; }
.total-price { font-size: 24px; color: var(--tova-pink); }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 480px) { .form-grid { grid-template-columns: 1fr; } }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group--full { grid-column: 1 / -1; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--tova-muted); letter-spacing: 0.5px; }
.form-group input,
.form-group textarea {
  background: var(--tova-surface2);
  border: 1px solid var(--tova-border);
  border-radius: var(--tova-radius-sm);
  color: var(--tova-text);
  padding: 12px 14px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  resize: none;
}
.form-group input:focus,
.form-group textarea:focus { border-color: var(--tova-pink); }

/* ——— SUCCESS SNACK ——— */
.order-success-snack {
  position: fixed;
  bottom: 24px; left: 50%;
  transform: translateX(-50%);
  z-index: 2000;
  background: var(--tova-surface);
  border: 1px solid rgba(233,30,140,0.4);
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 20px 60px rgba(233,30,140,0.3);
  min-width: 300px;
  max-width: 480px;
}
.snack-icon { font-size: 32px; }
.snack-title { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
.snack-sub { font-size: 13px; color: var(--tova-muted); }
.snack-close { background: none; border: none; color: var(--tova-muted); font-size: 18px; cursor: pointer; margin-left: auto; }

/* ——— ANIMACIONES ——— */
@keyframes spin-slow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@keyframes ring-pulse {
  0%, 100% { transform: translate(-50%,-50%) scale(1); opacity: 1; }
  50% { transform: translate(-50%,-50%) scale(1.06); opacity: 0.7; }
}
@keyframes glow-pulse {
  0%, 100% { opacity: 0.6; transform: translate(-50%,-50%) scale(1); }
  50% { opacity: 1; transform: translate(-50%,-50%) scale(1.2); }
}
@keyframes particle-float {
  0%, 100% { opacity: 0; transform: translateY(0); }
  50% { opacity: 0.6; transform: translateY(-30px); }
}
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Transiciones de drawer/modal */
.drawer-enter-active, .drawer-leave-active { transition: opacity 0.3s; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-active .cart-drawer, .drawer-leave-active .cart-drawer { transition: transform 0.35s cubic-bezier(0.25, 0.8, 0.25, 1); }
.drawer-enter-from .cart-drawer, .drawer-leave-to .cart-drawer { transform: translateX(100%); }

.modal-enter-active, .modal-leave-active { transition: all 0.3s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-card, .modal-leave-active .modal-card { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-enter-from .modal-card, .modal-leave-to .modal-card { transform: scale(0.92) translateY(20px); }

.snack-enter-active, .snack-leave-active { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.snack-enter-from, .snack-leave-to { opacity: 0; transform: translateX(-50%) translateY(30px); }

/* ——— RESPONSIVE ——— */
@media (max-width: 768px) {
  .tova-search-wrap { display: none; }
  .hero-decoration { display: none; }
  .tova-hero { min-height: auto; padding: 100px 24px 60px; }
  .categories-grid { grid-template-columns: repeat(3, 1fr); }
  .products-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .feature-item { padding: 24px; }
}

@media (max-width: 480px) {
  .categories-grid { grid-template-columns: repeat(2, 1fr); }
  .products-grid { grid-template-columns: 1fr; }
}
</style>
