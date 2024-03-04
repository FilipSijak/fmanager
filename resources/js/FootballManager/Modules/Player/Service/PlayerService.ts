import {Service} from "../../../Libs/Service";

export class PlayerService extends Service
{
    fetchPlayer = () => {
        return this.httpClient.get('player/1').then((response) => {
            console.log(response);
        })
    }
}
