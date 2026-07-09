// src/plugins/axios.js
import axios from 'axios';

const axiosInstance = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Interceptor para autenticación por sesión
// Laravel Sanctum usa cookies de sesión, no tokens Bearer
axiosInstance.interceptors.request.use(
  (config) => {
    // Agregar CSRF token si está disponible
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token;
    }
    
    // Asegurar que las cookies se envíen
    config.withCredentials = true;
    
    return config
  },
  (error) => {
    console.error('Axios Request Error:', error);
    return Promise.reject(error)
  }
)

const roundQuantitiesAndStock = (obj) => {
  if (obj === null || typeof obj !== 'object') {
    return obj;
  }
  
  if (Array.isArray(obj)) {
    return obj.map(item => roundQuantitiesAndStock(item));
  }
  
  const keysToRound = [
    'quantity', 'stock', 'stock_calculado', 'counted_quantity', 'lote_quantity',
    'system_quantity', 'final_quantity', 'target_quantity', 'actual_quantity',
    'available_quantity', 'selected_quantity', 'requested_quantity', 'min_volume',
    'max_volume', 'presentation'
  ];
  
  const newObj = {};
  for (const key in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, key)) {
      const val = obj[key];
      const isKeyMatch = keysToRound.includes(key) || 
                         key.includes('quantity') || 
                         key.includes('stock');
                         
      if (isKeyMatch && (typeof val === 'number' || (typeof val === 'string' && !isNaN(Number(val)) && val.trim() !== ''))) {
        newObj[key] = Math.round(Number(val));
      } else {
        newObj[key] = roundQuantitiesAndStock(val);
      }
    }
  }
  return newObj;
};

// Interceptor de respuesta
axiosInstance.interceptors.response.use(
  (response) => {
    const isMiniMarket = localStorage.getItem('business_type') === 'minimarket';
    if (isMiniMarket && response.data) {
      response.data = roundQuantitiesAndStock(response.data);
    }
    return response;
  },
  (error) => {
    return Promise.reject(error);
  }
)

export default axiosInstance
