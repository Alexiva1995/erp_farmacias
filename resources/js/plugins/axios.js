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

// Interceptor de respuesta
axiosInstance.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    return Promise.reject(error);
  }
)

export default axiosInstance
