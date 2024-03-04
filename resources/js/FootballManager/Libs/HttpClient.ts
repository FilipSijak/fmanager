import axios, {AxiosInstance, AxiosResponse} from 'axios';

export interface IHttpClient {
    get<T = never, R = AxiosResponse<T>>(path:string): Promise<T>
    post<D, T, R = AxiosResponse<T>>(path: string, payload: D): Promise<R>
    put<D, T, R = AxiosResponse<T>>(path: string, payload: D): Promise<R>
    delete<T, R>(path, payload): Promise<R>
}

export type GameHeaders = {
    instanceId: number,
    seasonId?: number,
}

export class HttpClient implements IHttpClient
{
    private baseURL: string = '/api/';
    private axiosInstance!: AxiosInstance;

    constructor(gameHeaders: GameHeaders) {
        this.axiosInstance = axios.create({
            baseURL: this.baseURL,
            withCredentials: true,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'instanceId': gameHeaders.instanceId,
                'seasonId': gameHeaders.seasonId?? 0
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

    put<D, T, R = AxiosResponse<T>>(
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

    delete<T, R = AxiosResponse<T>>(path): Promise<R> {
        return this.axiosInstance.request<T, R>({
            method: 'POST',
            url: path,
            responseType: 'json',
            baseURL: this.baseURL
        });
    }
}

