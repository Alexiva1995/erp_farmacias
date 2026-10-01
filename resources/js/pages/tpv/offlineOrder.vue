<script setup>
import { ref, computed, onMounted } from 'vue'

// ─── Referencias Reactivas ───────────────────────────────────────────────────
const searchQuery = ref('')
const cart = ref([])
const isProcessing = ref(false)
const syncPendingCount = ref(0)
const localProducts = ref([])
const dbInstance = ref(null)

// ─── Configuración de IndexedDB ──────────────────────────────────────────────
const DB_NAME = 'ErpFarmaciasOfflineDB'
const DB_VERSION = 1
const STORE_PRODUCTS = 'products'
const STORE_ORDERS = 'offline_orders'

// Inicializa la base de datos local
const initIndexedDB = () => {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onerror = (event) => {
      console.error('IndexedDB Error:', event.target.error)
      reject(event.target.error)
    }

    request.onupgradeneeded = (event) => {
      const db = event.target.result

      // Almacén de catálogo local
      if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
        db.createObjectStore(STORE_PRODUCTS, { keyPath: 'id' })
      }

      // Almacén de ventas de contingencia
      if (!db.objectStoreNames.contains(STORE_ORDERS)) {
        db.createObjectStore(STORE_ORDERS, { keyPath: 'uuid' })
      }
    }

    request.onsuccess = (event) => {
      dbInstance.value = event.target.result
      resolve(dbInstance.value)
    }
  })
}

// ─── Operaciones del Catálogo Local ──────────────────────────────────────────
const loadLocalCatalog = () => {
  if (!dbInstance.value) return

  const transaction = dbInstance.value.transaction([STORE_PRODUCTS], 'readonly')
  const store = transaction.objectStore(STORE_PRODUCTS)
  const request = store.getAll()

  request.onsuccess = () => {
    // Si la base de datos está vacía, cargamos productos de prueba (mock)
    // En producción, esto se llena mediante un Service Worker al haber internet.
    if (request.result.length === 0) {
      const mockCatalog = [
        { id: 1, barcode: '123456789', name: 'Paracetamol 500mg', price: 2.50, stock: 100 },
        { id: 2, barcode: '987654321', name: 'Ibuprofeno 400mg', price: 3.00, stock: 50 },
        { id: 3, barcode: '111222333', name: 'Amoxicilina 500mg', price: 5.50, stock: 20 },
      ]
      
      const writeTx = dbInstance.value.transaction([STORE_PRODUCTS], 'readwrite')
      const writeStore = writeTx.objectStore(STORE_PRODUCTS)
      mockCatalog.forEach(product => writeStore.put(product))
      
      localProducts.value = mockCatalog
    } else {
      localProducts.value = request.result
    }
  }
}

// ─── Lógica del Carrito y Búsqueda ───────────────────────────────────────────
const searchProduct = () => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return

  const foundProduct = localProducts.value.find(p => 
    p.barcode === query || p.name.toLowerCase().includes(query)
  )

  if (foundProduct) {
    addToCart(foundProduct)
    searchQuery.value = ''
  } else {
    // Reemplazar con sistema de notificaciones del proyecto (ej. toast)
    alert('Producto no encontrado en caché local.')
  }
}

const addToCart = (product) => {
  const existingItem = cart.value.find(item => item.id === product.id)
  
  if (existingItem) {
    if (existingItem.quantity < product.stock) {
      existingItem.quantity++
    }
  } else {
    cart.value.push({ ...product, quantity: 1 })
  }
}

const removeFromCart = (index) => {
  cart.value.splice(index, 1)
}

const cartTotal = computed(() => {
  return cart.value.reduce((total, item) => total + (item.price * item.quantity), 0)
})

// ─── Operaciones de Venta Offline ────────────────────────────────────────────
const generateUUID = () => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

const processOfflineOrder = () => {
  if (cart.value.length === 0) return
  
  isProcessing.value = true

  // Simulamos un breve retardo de procesamiento local
  setTimeout(() => {
    const newOfflineOrder = {
      uuid: generateUUID(),
      timestamp: new Date().toISOString(),
      items: JSON.parse(JSON.stringify(cart.value)),
      total: cartTotal.value,
      status: 'pending_sync'
    }

    const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readwrite')
    const store = transaction.objectStore(STORE_ORDERS)
    
    store.put(newOfflineOrder)

    transaction.oncomplete = () => {
      cart.value = []
      isProcessing.value = false
      updatePendingSyncCount()
    }

    transaction.onerror = (error) => {
      console.error('Error al guardar la orden local:', error)
      isProcessing.value = false
    }
  }, 500)
}

const updatePendingSyncCount = () => {
  if (!dbInstance.value) return

  const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readonly')
  const store = transaction.objectStore(STORE_ORDERS)
  const request = store.count()

  request.onsuccess = () => {
    syncPendingCount.value = request.result
  }
}

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    await initIndexedDB()
    loadLocalCatalog()
    updatePendingSyncCount()
  } catch (error) {
    console.error('No se pudo inicializar el modo offline', error)
  }
})
</script>

<template>
  <v-container fluid>
    <!-- Alerta visual crítica para informar el modo de operación -->
    <v-alert
      type="warning"
      variant="tonal"
      class="mb-6"
      border="start"
      icon="mdi-wifi-off"
    >
      <div class="text-h6">PUNTO DE VENTA OFFLINE (MODO CONTINGENCIA)</div>
      <div class="text-body-2">
        Sin conexión al servidor central. Las ventas se guardarán localmente y se sincronizarán al regresar la red.
        No se aplican reglas complejas de convenios ni descuentos especiales.
      </div>
    </v-alert>

    <v-row>
      <!-- Panel Izquierdo: Catálogo y Búsqueda -->
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-title>
            Búsqueda de Productos (Caché Local)
          </v-card-title>
          <v-card-text>
            <v-text-field
              v-model="searchQuery"
              label="Escanear Código de Barras o Buscar"
              variant="outlined"
              append-inner-icon="mdi-barcode-scan"
              @keyup.enter="searchProduct"
              hide-details
              class="mb-4"
              autofocus
            />
          </v-card-text>
        </v-card>

        <!-- Tabla del Carrito -->
        <v-card>
          <v-table>
            <thead>
              <tr>
                <th class="text-left">Código</th>
                <th class="text-left">Descripción</th>
                <th class="text-right">Precio Unit.</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Subtotal</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <!-- Estado vacío -->
              <tr v-if="cart.length === 0">
                <td colspan="6" class="text-center py-8 text-grey">
                  <v-icon icon="mdi-cart-off" size="large" class="mb-2" />
                  <br>
                  No hay productos en la orden de contingencia
                </td>
              </tr>
              <!-- Productos en carrito -->
              <tr v-for="(item, index) in cart" :key="item.id">
                <td>{{ item.barcode }}</td>
                <td>{{ item.name }}</td>
                <td class="text-right">${{ item.price.toFixed(2) }}</td>
                <td class="text-center">
                  <v-btn
                    icon="mdi-minus"
                    size="x-small"
                    variant="text"
                    @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)"
                  />
                  <span class="mx-2">{{ item.quantity }}</span>
                  <v-btn
                    icon="mdi-plus"
                    size="x-small"
                    variant="text"
                    :disabled="item.quantity >= item.stock"
                    @click="item.quantity++"
                  />
                </td>
                <td class="text-right font-weight-bold">
                  ${{ (item.price * item.quantity).toFixed(2) }}
                </td>
                <td class="text-center">
                  <v-btn
                    icon="mdi-delete"
                    color="error"
                    size="small"
                    variant="text"
                    @click="removeFromCart(index)"
                  />
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>

      <!-- Panel Derecho: Totales y Cobro -->
      <v-col cols="12" md="4">
        <v-card color="grey-lighten-4" class="h-100 d-flex flex-column">
          <v-card-title class="bg-primary text-white">
            Resumen de Contingencia
          </v-card-title>
          
          <v-card-text class="flex-grow-1 pt-4">
            <div class="d-flex justify-space-between mb-2">
              <span class="text-body-1">Subtotal:</span>
              <span class="text-body-1">${{ cartTotal.toFixed(2) }}</span>
            </div>
            <v-divider class="my-2" />
            <div class="d-flex justify-space-between align-center">
              <span class="text-h6 font-weight-bold">TOTAL:</span>
              <span class="text-h5 font-weight-black text-primary">
                ${{ cartTotal.toFixed(2) }}
              </span>
            </div>

            <!-- Indicador de cola de sincronización -->
            <v-sheet
              color="warning-lighten-4"
              class="pa-3 mt-6 rounded d-flex align-center"
              v-if="syncPendingCount > 0"
            >
              <v-icon icon="mdi-cloud-sync" color="warning-darken-2" class="mr-2" />
              <span class="text-caption text-warning-darken-2 font-weight-medium">
                {{ syncPendingCount }} orden(es) en cola esperando sincronización.
              </span>
            </v-sheet>
          </v-card-text>

          <v-card-actions class="pa-4">
            <v-btn
              color="success"
              variant="elevated"
              block
              size="x-large"
              :disabled="cart.length === 0 || isProcessing"
              :loading="isProcessing"
              @click="processOfflineOrder"
            >
              <v-icon icon="mdi-cash-register" start />
              Procesar Pago Offline
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
