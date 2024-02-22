import axios, {AxiosError, AxiosInstance, AxiosRequestConfig, AxiosResponse} from 'axios';

export interface IHttpClientRequestParameters<T> {
    url: string
    requiresToken: boolean
    payload?: T
}

export interface IHttpClient {
    get<T = never, R = AxiosResponse<T>>(path:string): Promise<T>
    post<D, T, R = AxiosResponse<T>>(path: string, payload: D): Promise<R>
    put<T>(parameters: IHttpClientRequestParameters<T>): Promise<T>
}

export class HttpClient implements IHttpClient
{
    private baseURL = '/api/';
    private axiosInstance!: AxiosInstance;

    constructor() {
        this.axiosInstance = axios.create({
            baseURL: this.baseURL,
            withCredentials: true,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
    }

    get<T = never, R = AxiosResponse<T>>(
        path: string
    ): Promise<R> {
        return this.axiosInstance.request<T, R>({
            method: 'GET',
            url: path,
            baseURL:this.baseURL
        });
    }

    post<D, T, R = AxiosResponse<T>>(
        path: string,
        payload: D
    ): Promise<R> {
        return this.axiosInstance.request<T, R>({
            method: 'POST',
            url: path,
            responseType: 'json',
            data: payload,
            baseURL: this.baseURL
        });
    }

    put<T>(parameters): Promise<any> {
        const { url } = parameters;

        return this.axiosInstance.request<T>({
            method: 'PATCH',
            url: url,
            baseURL:this.baseURL
        });
    }
}

