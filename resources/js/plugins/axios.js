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
    // 1. Intentar leer la cookie XSRF-TOKEN actualizada por Laravel/Sanctum
    const xsrfCookie = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (xsrfCookie) {
      const decodedToken = decodeURIComponent(xsrfCookie[1]);
      config.headers['X-XSRF-TOKEN'] = decodedToken;
      config.headers['X-CSRF-TOKEN'] = decodedToken;
    } else {
      // 2. Respaldo: meta tag inyectado por Blade
      const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (metaToken) {
        config.headers['X-CSRF-TOKEN'] = metaToken;
        config.headers['X-XSRF-TOKEN'] = metaToken;
      }
    }
    
    // Asegurar que las cookies de sesión se envíen
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

let isRefreshingCsrf = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
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
  async (error) => {
    const originalRequest = error.config;

    // Manejo transparente de error 419 (CSRF Token Mismatch)
    if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
      if (isRefreshingCsrf) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        }).then(() => {
          return axiosInstance(originalRequest);
        }).catch(err => {
          return Promise.reject(err);
        });
      }

      originalRequest._retry = true;
      isRefreshingCsrf = true;

      try {
        // Renovar cookie CSRF desde Sanctum/Laravel
        await axios.get('/sanctum/csrf-cookie', { baseURL: '' });
        
        // Extraer y actualizar el token en el meta tag del DOM
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (match) {
          const newToken = decodeURIComponent(match[1]);
          const metaCsrf = document.querySelector('meta[name="csrf-token"]');
          if (metaCsrf) {
            metaCsrf.setAttribute('content', newToken);
          }
          if (originalRequest.headers) {
            originalRequest.headers['X-CSRF-TOKEN'] = newToken;
          }
        }

        processQueue(null);
        return axiosInstance(originalRequest);
      } catch (csrfError) {
        processQueue(csrfError, null);
        console.warn('Error renovando CSRF token:', csrfError);
        window.location.reload();
        return Promise.reject(csrfError);
      } finally {
        isRefreshingCsrf = false;
      }
    }

    // Manejo de sesión expirada (401 Unauthenticated)
    if (error.response?.status === 401 && !window.location.pathname.includes('/login')) {
      window.location.href = '/login';
      return Promise.reject(error);
    }

    // Guardar el último error de Axios de forma global para el sistema de Toasts
    window.lastAxiosError = error;
    
    // Limpiar después de 5 segundos para evitar asociaciones incorrectas
    setTimeout(() => {
      if (window.lastAxiosError === error) {
        window.lastAxiosError = null;
      }
    }, 5000);

    return Promise.reject(error);
  }
)

export default axiosInstance
