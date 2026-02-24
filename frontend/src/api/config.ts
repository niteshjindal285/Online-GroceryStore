import axios from 'axios';

// Use same base URL logic as config
const baseURL = import.meta.env.MODE === 'production'
    ? 'https://grocery-backend-s54s.onrender.com/api'
    : 'http://localhost:5000/api';

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
