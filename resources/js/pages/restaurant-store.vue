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

// --- Estados del Menú ---
const products = ref([]) // Representará platos
const categories = ref([])
const searchQuery = ref('')
const loading = ref(false)
const orderSuccess = ref(false)
const lastOrderId = ref(null)
const selectedCategory = ref(null)

// Monedas y Checkout
const binanceRate = ref(45.50)
const copRate = ref(4000.00)
const selectedCurrency = ref('COP')
const cartDrawer = ref(false)
const orderSubmitting = ref(false)
const paymentProof = ref(null)

const orderForm = ref({
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  shipping_address: '',
  notes: '',
  payment_method: 'contraentrega',
  customer_document_type: 'V-',
  customer_document_number: '',
})

// Reactividad del Carrito
const cart = ref([])

const cartTotalItems = computed(() => cart.value.reduce((acc, i) => acc + i.quantity, 0))
const cartTotalPrice = computed(() =>
  cart.value.reduce((acc, i) => acc + Number(i.product.sale_price || 0) * i.quantity, 0)
)

const orderFormValid = computed(() =>
  orderForm.value.customer_name.trim() &&
  orderForm.value.customer_phone.trim() &&
  orderForm.value.customer_document_number.trim() &&
  selectedCurrency.value &&
  orderForm.value.payment_method
)

// --- Colores y Estilos Dinámicos ---
const themeStyles = computed(() => {
  const primary = brandingStore.settings?.primary_color || '#0c382e'
  const secondary = brandingStore.settings?.secondary_color || '#07241e'
  return {
    '--sg-primary': primary,
    '--sg-hover': secondary,
  }
})

// --- Búsqueda de cliente ---
const clientLookupState = ref('idle')
const clientLookupMessage = ref('')
let _documentDebounceTimer = null

const lookupClientByDocument = async (docNumber) => {
  const num = (docNumber || '').trim()
  if (!num || num.length < 4) {
    clientLookupState.value = 'idle'
    clientLookupMessage.value = ''
    return
  }

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
    console.warn('Cliente no en BD:', err)
  }

  if (orderForm.value.customer_document_type === 'V-') {
    clientLookupState.value = 'searching'
    clientLookupMessage.value = 'Buscando en CNE...'
    try {
      const cneResp = await axios.post('/public/clients/cne-verify', { identification: num })
      if (cneResp.data && cneResp.data.data) {
        const cne = cneResp.data.data
        orderForm.value.customer_name = `${cne.name || ''} ${cne.last_name || ''}`.trim()
        clientLookupState.value = 'new'
        clientLookupMessage.value = `✓ Datos CNE: ${orderForm.value.customer_name}`
        return
      }
    } catch (cneErr) {
      console.log('CNE sin resultados:', cneErr)
    }
  }

  clientLookupState.value = 'new'
  clientLookupMessage.value = 'Cliente nuevo. Complete los datos.'
}

watch(
  () => orderForm.value.customer_document_number,
  (val) => {
    clearTimeout(_documentDebounceTimer)
    clientLookupState.value = 'idle'
    clientLookupMessage.value = ''
    _documentDebounceTimer = setTimeout(() => lookupClientByDocument(val), 600)
  }
)

// --- Fetch de Datos ---
const fetchCategories = async () => {
  try {
    const { data } = await axios.get('/public/ecommerce/categories')
    if (data.success) {
      categories.value = data.data
      if (categories.value.length > 0) {
        selectedCategory.value = categories.value[0].slug
      }
    }
  } catch (e) {
    console.error(e)
  }
}

const fetchExchangeRates = async () => {
  try {
    const { data } = await axios.get('/public/exchange-rates')
    if (Array.isArray(data)) {
      const binance = data.find(r => r.currency_code === 'BINANCE')
      if (binance?.rate) binanceRate.value = parseFloat(binance.rate)
      const cop = data.find(r => r.currency_code === 'COP')
      if (cop?.rate) copRate.value = parseFloat(cop.rate)
    }
  } catch (e) {
    console.error(e)
  }
}

const fetchProducts = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/public/ecommerce/products')
    if (data.success) {
      products.value = data.data?.data || data.data || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// Agrupación por categorías
const groupedProducts = computed(() => {
  if (!products.value.length) return []
  return categories.value.map(cat => {
    const items = products.value.filter(p => p.category_id === cat.id)
    return {
      ...cat,
      items
    }
  }).filter(group => group.items.length > 0)
})

// --- Carrito ---
const addToCart = (product) => {
  const existing = cart.value.find(i => i.product.id === product.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({ product, quantity: 1 })
  }
}

const updateQuantity = (product, change) => {
  const existing = cart.value.find(i => i.product.id === product.id)
  if (!existing) return
  existing.quantity += change
  if (existing.quantity <= 0) {
    cart.value = cart.value.filter(i => i.product.id !== product.id)
  }
}

const clearCart = () => {
  cart.value = []
}

const handleFileChange = (e) => {
  const files = e.target.files
  if (files && files.length > 0) {
    paymentProof.value = files[0]
  }
}

const submitOrder = async () => {
  if (!cart.value.length || !orderFormValid.value) return
  orderSubmitting.value = true
  try {
    let data
    if (paymentProof.value) {
      const fd = new FormData()
      Object.entries(orderForm.value).forEach(([k, v]) => fd.append(k, v ?? ''))
      fd.append('payment_currency', selectedCurrency.value)
      cart.value.forEach((i, idx) => {
        fd.append(`items[${idx}][product_id]`, i.product.id)
        fd.append(`items[${idx}][quantity]`, i.quantity)
      })
      fd.append('payment_proof', paymentProof.value)
      ;({ data } = await axios.post('/public/ecommerce/checkout', fd, { headers: { 'Content-Type': 'multipart/form-data' } }))
    } else {
      const payload = {
        ...orderForm.value,
        payment_currency: selectedCurrency.value,
        items: cart.value.map(i => ({
          product_id: i.product.id,
          quantity: i.quantity,
        })),
      }
      ;({ data } = await axios.post('/public/ecommerce/checkout', payload))
    }
    if (data.success) {
      lastOrderId.value = data.order_id
      orderSuccess.value = true
      clearCart()
      cartDrawer.value = false
      orderForm.value = {
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        shipping_address: '',
        notes: '',
        payment_method: 'contraentrega',
        customer_document_type: 'V-',
        customer_document_number: '',
      }
      paymentProof.value = null
      clientLookupState.value = 'idle'
      clientLookupMessage.value = ''
    }
  } catch (e) {
    console.error('Error en checkout:', e)
  } finally {
    orderSubmitting.value = false
  }
}

const formatPrice = (n) => `$${Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2 })}`

const scrollToCategory = (slug) => {
  selectedCategory.value = slug
  const el = document.getElementById(`category-${slug}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

onMounted(async () => {
  try {
    await brandingStore.fetchSettings()
  } catch (err) {
    console.warn(err)
  }
  await fetchCategories()
  await fetchProducts()
  await fetchExchangeRates()
})
</script>

<template>
  <div class="sg-store-wrapper min-h-screen" :style="themeStyles">
    <!-- Header Minimalista Superior -->
    <header class="sg-header border-b border-gray-200 bg-white sticky top-0 z-20">
      <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
        <div class="flex items-center gap-6">
          <template v-if="brandingStore.settings?.app_logo">
            <img :src="brandingStore.settings.app_logo" alt="Logo" class="max-h-12 object-contain" />
          </template>
          <template v-else>
            <span class="sg-logo-txt font-black text-2xl tracking-tight">
              {{ brandingStore.settings?.app_name || 'sweetgreen' }}
            </span>
          </template>
          <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-gray-600">
            <a href="#" class="hover:text-emerald-900">Gift</a>
            <a href="#" class="hover:text-emerald-900">Rewards • 0 points</a>
            <a href="#" class="text-emerald-900 border-b-2 border-emerald-900 pb-1">Menu</a>
          </nav>
        </div>

        <div class="flex items-center gap-4">
          <!-- Campo de búsqueda integrado en header -->
          <div class="relative hidden sm:block">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search menu..."
              class="bg-gray-100 border-0 rounded-full text-xs px-4 py-2 w-48 outline-none text-emerald-950"
            />
          </div>

          <button @click="cartDrawer = true" class="sg-cart-btn relative flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-white transition-all shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <span>Order</span>
            <span v-if="cartTotalItems > 0" class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold bg-amber-400 text-emerald-950 shadow">
              {{ cartTotalItems }}
            </span>
          </button>
        </div>
      </div>
    </header>

    <!-- Sub-header con Ubicación y Estado de Tienda -->
    <div class="bg-gray-50 border-b border-gray-100 py-3 px-6 text-left">
      <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-gray-500 font-semibold">
        <div class="flex items-center gap-2 flex-wrap">
          <span>Pickup from Wynwood • Open until 11:00pm</span>
          <button class="text-emerald-800 underline hover:text-emerald-950">Change location</button>
          <button class="text-emerald-800 underline hover:text-emerald-950">More info</button>
        </div>
      </div>
    </div>

    <!-- Banner verde claro de preferencias alimenticias -->
    <div class="sg-diet-banner py-3.5 px-6 text-left border-b border-gray-200">
      <div class="max-w-7xl mx-auto flex items-center justify-between text-sm text-emerald-950 font-medium">
        <span>Set your dietary preferences here or in your account.</span>
        <button class="bg-emerald-900 text-white rounded-full p-1.5 flex items-center justify-center shadow-sm btn-diet-circle">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </div>
    </div>

    <!-- Navegación de Categorías Estilo Sweetgreen (Píldoras Verdes) -->
    <nav class="sg-category-nav sticky top-20 bg-white z-10 border-b border-gray-100 py-4 px-6 overflow-x-auto no-scrollbar">
      <div class="max-w-7xl mx-auto flex gap-2">
        <button
          v-for="cat in categories"
          :key="cat.id"
          @click="scrollToCategory(cat.slug)"
          class="sg-cat-pill px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all border"
          :class="selectedCategory === cat.slug ? 'active-pill' : 'inactive-pill'"
        >
          {{ cat.name }}
        </button>
      </div>
    </nav>

    <!-- Catálogo de Platos con Estética Aireada y Limpia -->
    <main class="max-w-7xl mx-auto px-6 py-12 text-left">
      <div v-if="loading" class="text-center py-20">
        <div class="spinner border-4 border-emerald-900 border-t-transparent rounded-full w-12 h-12 mx-auto animate-spin"></div>
        <p class="mt-4 text-gray-500 font-medium">Loading delicious options...</p>
      </div>

      <div v-else class="space-y-20">
        <section
          v-for="group in groupedProducts"
          :key="group.id"
          :id="`category-${group.slug}`"
          class="scroll-mt-40 animate-fade-in-up"
        >
          <div class="flex items-center gap-3 mb-8">
            <h2 class="text-4xl font-extrabold tracking-tight text-emerald-950 title-heading">
              {{ group.name }}
            </h2>
            <span class="bg-amber-100 text-amber-900 text-[10px] font-black px-2 py-0.5 rounded-md">NEW</span>
          </div>

          <!-- Grilla de Tarjetas Minimalistas (Sweetgreen Style exacto) -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div
              v-for="dish in group.items"
              :key="dish.id"
              class="sg-dish-card flex flex-col justify-between p-6 bg-white rounded-3xl transition-all duration-300 relative border border-transparent"
            >
              <!-- Imagen del plato arriba (Proporción perfecta de la foto del plato centrado) -->
              <div class="relative w-full aspect-square mb-6 flex items-center justify-center rounded-2xl overflow-hidden bg-transparent">
                <img
                  v-if="dish.image_url"
                  :src="dish.image_url"
                  class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
                  :alt="dish.name"
                />
                <div v-else class="text-6xl text-emerald-900/10 font-bold select-none">
                  🥗
                </div>
              </div>

              <!-- Contenido e Info -->
              <div class="flex-1 flex flex-col justify-between">
                <div>
                  <span class="text-[9px] uppercase font-bold text-emerald-800 tracking-widest block mb-2 card-pretitle">SUPPORTS THE EDIBLE SCHOOLYARD PROJECT</span>
                  <h3 class="text-lg font-extrabold text-emerald-950 mb-2 leading-snug title-heading">{{ dish.name }}</h3>
                  <p class="text-xs text-gray-500 mb-4 leading-relaxed font-medium line-clamp-3">
                    {{ dish.description || 'Our signature recipe prepared with fresh, locally-sourced ingredients and dressings.' }}
                  </p>

                  <!-- Fila de macros simulada estilo Sweetgreen -->
                  <div class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-400 font-bold mb-6 border-b border-gray-100 pb-4">
                    <span>43g <span class="font-normal">Protein</span></span>
                    <span>53g <span class="font-normal">Carbs</span></span>
                    <span>27g <span class="font-normal">Fat</span></span>
                    <span>7g <span class="font-normal">Fiber</span></span>
                    <span class="text-emerald-900/80 font-extrabold">730 <span class="font-normal">Cal</span></span>
                  </div>
                </div>

                <!-- Footer de la Tarjeta: Precio y Botón de Añadir -->
                <div class="flex items-center justify-between mt-auto">
                  <span class="text-lg font-black text-emerald-950 card-price">{{ formatPrice(dish.sale_price) }}</span>

                  <button
                    @click="addToCart(dish)"
                    class="sg-add-btn px-6 py-2.5 rounded-full text-xs font-bold transition-all shadow-sm flex items-center gap-1.5"
                  >
                    <span>+ Add</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>

    <!-- Drawer del Carrito / Checkout Integrado -->
    <div
      class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm transition-opacity"
      v-if="cartDrawer"
      @click="cartDrawer = false"
    ></div>

    <aside
      class="sg-cart-drawer fixed top-0 right-0 h-full w-full max-w-md z-50 bg-white shadow-2xl flex flex-col transition-transform duration-300 text-left"
      :class="cartDrawer ? 'translate-x-0' : 'translate-x-full'"
    >
      <header class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50">
        <h2 class="text-lg font-bold text-emerald-950 flex items-center gap-2 title-heading">
          <span>🥗</span> My Order
        </h2>
        <button @click="cartDrawer = false" class="text-gray-500 hover:text-gray-900 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </header>

      <!-- Contenido del Carrito -->
      <div class="flex-1 overflow-y-auto p-6 space-y-6">
        <div v-if="!cart.length" class="text-center py-16">
          <span class="text-6xl select-none">🥗</span>
          <p class="mt-4 text-emerald-950 font-bold text-lg title-heading">Your bag is empty</p>
          <p class="text-sm text-gray-500 mt-1">Add items from our fresh menu to get started.</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="item in cart"
            :key="item.product.id"
            class="flex items-center justify-between p-4 rounded-2xl border border-gray-100 bg-gray-50/50"
          >
            <div class="flex-1 pr-3">
              <h4 class="font-bold text-emerald-950 text-sm leading-snug title-heading">{{ item.product.name }}</h4>
              <p class="text-xs text-emerald-800 font-semibold mt-1 card-price">{{ formatPrice(item.product.sale_price) }}</p>
            </div>

            <!-- Cantidad -->
            <div class="flex items-center gap-2 bg-white px-2.5 py-1 rounded-full border border-gray-200">
              <button @click="updateQuantity(item.product, -1)" class="text-gray-400 hover:text-gray-900 font-bold px-1.5">-</button>
              <span class="text-emerald-950 font-bold text-sm min-w-4 text-center title-heading">{{ item.quantity }}</span>
              <button @click="updateQuantity(item.product, 1)" class="text-gray-400 hover:text-gray-900 font-bold px-1.5">+</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Checkout e Importes -->
      <footer class="p-6 border-t border-gray-100 bg-white space-y-4" v-if="cart.length">
        <div class="space-y-2">
          <div class="flex justify-between text-sm text-gray-500 font-semibold">
            <span>Subtotal</span>
            <span>{{ formatPrice(cartTotalPrice) }}</span>
          </div>
          <div class="flex justify-between text-lg font-black text-emerald-950 pt-2 border-t border-gray-100 title-heading">
            <span>Total</span>
            <span>{{ formatPrice(cartTotalPrice) }}</span>
          </div>
        </div>

        <!-- Formulario Integrado Minimalista -->
        <div class="pt-4 border-t border-gray-100 space-y-3">
          <h4 class="text-xs font-bold text-emerald-950 uppercase tracking-wider mb-2 title-heading">Delivery Information</h4>

          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="text-[9px] text-gray-400 uppercase font-black">Type</label>
              <select v-model="orderForm.customer_document_type" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-2.5 py-2 rounded-xl outline-none title-heading">
                <option value="V-">V-</option>
                <option value="J-">J-</option>
                <option value="E-">E-</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="text-[9px] text-gray-400 uppercase font-black">ID / Document</label>
              <input
                v-model="orderForm.customer_document_number"
                type="text"
                placeholder="Ej. 25123456"
                class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-3 py-2 rounded-xl outline-none"
              />
            </div>
          </div>
          <p v-if="clientLookupMessage" class="text-[10px] font-bold text-emerald-800">{{ clientLookupMessage }}</p>

          <div>
            <label class="text-[9px] text-gray-400 uppercase font-black">Full Name</label>
            <input v-model="orderForm.customer_name" type="text" placeholder="Your name" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-3 py-2 rounded-xl outline-none" />
          </div>

          <div>
            <label class="text-[9px] text-gray-400 uppercase font-black">Phone Number</label>
            <input v-model="orderForm.customer_phone" type="text" placeholder="Ej. 04141234567" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-3 py-2 rounded-xl outline-none" />
          </div>

          <div>
            <label class="text-[9px] text-gray-400 uppercase font-black">Delivery Address</label>
            <textarea v-model="orderForm.shipping_address" rows="2" placeholder="Detail address for delivery" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-3 py-2 rounded-xl outline-none resize-none"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[9px] text-gray-400 uppercase font-black">Pay In</label>
              <select v-model="selectedCurrency" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-2.5 py-2 rounded-xl outline-none">
                <option value="COP">COP</option>
                <option value="USD">USD</option>
                <option value="VES">VES</option>
              </select>
            </div>
            <div>
              <label class="text-[9px] text-gray-400 uppercase font-black">Method</label>
              <select v-model="orderForm.payment_method" class="w-full bg-gray-50 border border-gray-200 text-emerald-950 text-xs px-2.5 py-2 rounded-xl outline-none">
                <option value="contraentrega">Cash on Delivery</option>
                <option value="pago_movil">Pago Móvil</option>
                <option value="transferencia">Bank Transfer</option>
              </select>
            </div>
          </div>

          <div v-if="orderForm.payment_method !== 'contraentrega'" class="pt-2">
            <label class="text-[9px] text-gray-400 uppercase font-black">Attach Payment Proof</label>
            <input type="file" @change="handleFileChange" accept="image/*,application/pdf" class="w-full text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-emerald-100 file:text-emerald-900 hover:file:bg-emerald-200" />
          </div>
        </div>

        <button
          @click="submitOrder"
          :disabled="!orderFormValid || orderSubmitting"
          class="w-full py-4 rounded-full font-bold uppercase tracking-wider text-white transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed checkout-btn"
        >
          <span v-if="orderSubmitting">Processing...</span>
          <span v-else>Place Order</span>
        </button>
      </footer>
    </aside>

    <!-- Modal de Pedido Exitoso -->
    <div v-if="orderSuccess" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-white max-w-md w-full p-8 rounded-3xl text-center shadow-2xl border border-gray-100">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 text-emerald-900 text-3xl mb-4 font-bold">
          ✓
        </div>
        <h3 class="text-2xl font-bold text-emerald-950 mb-2 title-heading">Order Confirmed!</h3>
        <p class="text-sm text-gray-600 mb-6">Your order #{{ lastOrderId }} has been received and is being prepared with care.</p>
        <button
          @click="orderSuccess = false"
          class="w-full py-3.5 rounded-full font-bold text-white transition-colors checkout-btn"
        >
          Great, thanks!
        </button>
      </div>
    </div>
  </div>
</template>

<style>
/* --- Estilos Minimalistas Aireados de Sweetgreen --- */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap');

.sg-store-wrapper {
  background-color: #f6f5f0; /* Fondo beige sólido y suave exacto */
  color: var(--sg-primary);
  font-family: 'Outfit', sans-serif;
  -webkit-font-smoothing: antialiased;
}

.sg-header {
  border-color: #e5e4de;
  background-color: #ffffff;
}

.sg-logo-txt {
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.04em;
  color: var(--sg-primary) !important;
}

.sg-cart-btn {
  background-color: var(--sg-primary) !important;
}
.sg-cart-btn:hover {
  background-color: var(--sg-hover) !important;
}

.sg-diet-banner {
  background-color: #e2ede5; /* Verde oliva muy claro y suave */
  border-color: #d1ded4;
}
.btn-diet-circle {
  background-color: var(--sg-primary) !important;
}

.sg-category-nav {
  border-color: #e5e4de;
  background-color: #ffffff;
}

.sg-cat-pill {
  border-color: #d3cfc5;
}
.active-pill {
  background-color: var(--sg-primary) !important;
  color: #ffffff !important;
  border-color: var(--sg-primary) !important;
}
.inactive-pill {
  background-color: transparent !important;
  color: var(--sg-primary) !important;
  border-color: #d3cfc5 !important;
}
.inactive-pill:hover {
  background-color: #f2f0e8 !important;
}

.title-heading {
  color: var(--sg-primary) !important;
}

.card-pretitle {
  color: var(--sg-primary) !important;
  opacity: 0.65;
}

.sg-dish-card {
  background-color: #eae8e1; /* Fondo gris/crema suave de la tarjeta en la imagen */
  border-radius: 24px; /* Esquinas muy redondeadas */
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.015);
}
.sg-dish-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(12, 56, 46, 0.05);
}

.card-price {
  color: var(--sg-primary) !important;
}

.sg-add-btn {
  border-color: var(--sg-primary) !important;
  color: var(--sg-primary) !important;
  background-color: transparent !important;
  border-width: 1.5px;
}
.sg-add-btn:hover {
  background-color: var(--sg-primary) !important;
  color: #ffffff !important;
}

.sg-cart-drawer {
  border-left: 1px solid #e5e4de;
}

.checkout-btn {
  background-color: var(--sg-primary) !important;
}
.checkout-btn:hover {
  background-color: var(--sg-hover) !important;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in-up {
  animation: fadeInUp 0.4s ease-out both;
}
</style>
