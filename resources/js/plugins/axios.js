// src/plugins/axios.js
import axios from 'axios'

const axiosInstance = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default axiosInstance
