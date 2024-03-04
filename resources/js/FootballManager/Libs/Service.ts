import {GameHeaders, HttpClient} from "./HttpClient";

export class Service
{
    protected httpClient: HttpClient;

    constructor() {
        let headers: GameHeaders = {
            instanceId: 1,
            seasonId: 1
        };

        this.httpClient = new HttpClient(headers);
    }
}
