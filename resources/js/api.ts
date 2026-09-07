import axios from 'axios';
import { login } from '@/routes';

const api = axios.create({
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (
            axios.isAxiosError(error) &&
            [401, 419].includes(error.response?.status ?? 0)
        ) {
            window.location.assign(login.url());
        }
        return Promise.reject(error);
    },
);

export default api;
