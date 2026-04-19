import axios from 'axios';

// Use local backend for development, fallback to render for production
const baseURL = import.meta.env.DEV
    ? 'http://localhost:10000/api'
    : 'https://your-render-backend.onrender.com/api';

const api = axios.create({
    baseURL,
    headers: {
        'Content-Type': 'application/json'
    }
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, (error) => {
    return Promise.reject(error);
});

export default api;

