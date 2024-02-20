import axios, { AxiosError, AxiosInstance, AxiosResponse } from 'axios';

export interface IHttpClientRequestParameters<T> {
    url: string
    requiresToken: boolean
    payload?: T
}

export interface IHttpClient {
    get<T>(parameters: IHttpClientRequestParameters<T>): Promise<T>
    post<T>(parameters: IHttpClientRequestParameters<T>): Promise<T>
}

export class HttpClient
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

    get<T>(parameters): Promise<AxiosResponse<T>> {
        const { url } = parameters;

        return this.axiosInstance.request<T>({
            method: 'GET',
            url: url,
            baseURL:this.baseURL
        });
    }

    post<T>(
        path: string,
        payload: any,
        baseURL?: string
    ): Promise<AxiosResponse<any>> {
        return this.axiosInstance.request<T>({
            method: 'POST',
            url: path,
            responseType: 'json',
            data: payload,
            baseURL: baseURL || this.baseURL
        });
    }
}
