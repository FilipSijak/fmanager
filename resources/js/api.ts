import axios from 'axios';

const api = axios.create({ headers: { Accept: 'application/json' } });

api.interceptors.request.use((config) => {
    const instanceHash = window.localStorage.getItem('instanceHash');
    if (instanceHash) config.headers.set('instanceHash', instanceHash);
    return config;
});

export default api;
