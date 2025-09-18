// src/plugins/axios.js
import axios from 'axios';

const axiosInstance = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
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
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

export default axiosInstance
