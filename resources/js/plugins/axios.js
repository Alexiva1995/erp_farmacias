// src/plugins/axios.js
import axios from 'axios'

const axiosInstance = axios.create({
  // La URL base de tu API en Laravel
  baseURL: '/api', // O http://localhost:8000/api si estás en desarrollo con servidores separados
})

export default axiosInstance
