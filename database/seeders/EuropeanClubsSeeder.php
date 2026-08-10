<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EuropeanClubsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->leagueData() as $competitionId => [$country, $baseRank, $clubs]) {
            foreach ($clubs as $index => $value) {
                [$name, $city] = explode('|', $value, 2);
                $rank = max(8, $baseRank - intdiv($index, 5));
                $existingClub = DB::table('base_clubs')
                    ->where('name', $name)
                    ->where('country_code', $country)
                    ->first();

                $cityId = $existingClub->city_id ?? DB::table('cities')->where('name', $city)
                    ->where('country_code', $country)->value('id');

                if (! $cityId) {
                    $cityId = DB::table('cities')->insertGetId([
                        'name' => $city, 'population' => 0, 'country_code' => $country,
                    ]);
                }

                $stadiumName = $name.' Stadium';
                $stadiumId = $existingClub->stadium_id ?? DB::table('base_stadiums')->where('name', $stadiumName)
                    ->where('country_code', $country)->value('id');

                if (! $stadiumId) {
                    $stadiumId = DB::table('base_stadiums')->insertGetId([
                        'name' => $stadiumName, 'country_code' => $country,
                        'city_id' => $cityId, 'capacity' => 20000,
                    ]);
                }

                DB::table('base_clubs')->updateOrInsert(
                    ['name' => $name, 'country_code' => $country],
                    [
                        'city_id' => $cityId, 'stadium_id' => $stadiumId,
                        'rank' => $rank, 'rank_academy' => max(8, $rank - 1),
                        'rank_training' => $rank, 'financial_rank' => max(8, $rank - 1),
                        'starting_balance' => $rank * 8000000,
                        'allowed_debt' => $rank * 6000000,
                        'transfer_budget' => $rank * 5000000,
                        'salaries_yearly_budget' => $rank * 7000000,
                        'competition_id' => $competitionId,
                    ]
                );
            }
        }
    }

    private function leagueData(): array
    {
        return [
            1 => ['GBR', 20, [
                'Arsenal|London', 'Liverpool|Liverpool', 'Manchester City|Manchester', 'Chelsea|London', 'Manchester United|Manchester',
                'Newcastle United|Newcastle upon Tyne', 'Tottenham Hotspur|London', 'Aston Villa|Birmingham', 'Brighton & Hove Albion|Brighton', 'Crystal Palace|London',
                'Nottingham Forest|Nottingham', 'Everton|Liverpool', 'Fulham|London', 'AFC Bournemouth|Bournemouth', 'Brentford|London',
                'Sunderland|Sunderland', 'Leeds United|Leeds', 'Coventry City|Coventry', 'Ipswich Town|Ipswich', 'Hull City|Kingston upon Hull',
            ]],
            2 => ['DEU', 19, [
                'Bayern Munich|Munich', 'Borussia Dortmund|Dortmund', 'Bayer Leverkusen|Leverkusen', 'RB Leipzig|Leipzig', 'Eintracht Frankfurt|Frankfurt',
                'VfB Stuttgart|Stuttgart', 'SC Freiburg|Freiburg im Breisgau', 'Borussia Mönchengladbach|Mönchengladbach', 'Werder Bremen|Bremen', 'Hamburger SV|Hamburg',
                'Schalke 04|Gelsenkirchen', 'Mainz 05|Mainz', 'Union Berlin|Berlin', 'TSG Hoffenheim|Sinsheim', 'FC Augsburg|Augsburg',
                'SC Paderborn|Paderborn', 'SV Elversberg|Spiesen-Elversberg', '1. FC Köln|Cologne',
            ]],
            10 => ['DEU', 13, [
                'VfL Wolfsburg|Wolfsburg', 'FC St. Pauli|Hamburg', '1. FC Heidenheim|Heidenheim', 'Hertha BSC|Berlin', 'Hannover 96|Hanover',
                '1. FC Kaiserslautern|Kaiserslautern', '1. FC Nürnberg|Nuremberg', 'VfL Bochum|Bochum', 'Karlsruher SC|Karlsruhe', 'Holstein Kiel|Kiel',
                'Darmstadt 98|Darmstadt', '1. FC Magdeburg|Magdeburg', 'Arminia Bielefeld|Bielefeld', 'Dynamo Dresden|Dresden', 'Greuther Fürth|Fürth',
                'Eintracht Braunschweig|Braunschweig', 'VfL Osnabrück|Osnabrück', 'Energie Cottbus|Cottbus',
            ]],
            3 => ['ESP', 20, [
                'FC Barcelona|Barcelona', 'Real Madrid|Madrid', 'Atlético de Madrid|Madrid', 'Villarreal|Villarreal', 'Real Betis|Seville',
                'Real Sociedad|San Sebastián', 'Athletic Club|Bilbao', 'Celta Vigo|Vigo', 'Valencia|Valencia', 'Sevilla|Seville',
                'Getafe|Getafe', 'Rayo Vallecano|Madrid', 'Osasuna|Pamplona', 'Espanyol|Cornellà de Llobregat', 'Alavés|Vitoria-Gasteiz',
                'Levante|Valencia', 'Elche|Elche', 'Racing Santander|Santander', 'Deportivo La Coruña|A Coruña', 'Málaga|Málaga',
            ]],
            11 => ['ESP', 13, [
                'Mallorca|Palma', 'Girona|Girona', 'Real Oviedo|Oviedo', 'Almería|Almería', 'Las Palmas|Las Palmas',
                'Granada|Granada', 'Real Valladolid|Valladolid', 'Eibar|Eibar', 'Leganés|Leganés', 'Cádiz|Cádiz',
                'Sporting Gijón|Gijón', 'Burgos|Burgos', 'Albacete|Albacete', 'Córdoba|Córdoba', 'Castellón|Castellón de la Plana',
                'FC Andorra|Encamp', 'AD Ceuta|Ceuta', 'Real Sociedad B|San Sebastián', 'Tenerife|Santa Cruz de Tenerife', 'Eldense|Elda',
                'Celta Fortuna|Vigo', 'Sabadell|Sabadell',
            ]],
            4 => ['FRA', 19, [
                'Paris Saint-Germain|Paris', 'Marseille|Marseille', 'Monaco|Monaco', 'Lyon|Décines-Charpieu', 'Lille|Villeneuve-d\'Ascq',
                'Lens|Lens', 'Strasbourg|Strasbourg', 'Nice|Nice', 'Rennes|Rennes', 'Brest|Brest',
                'Toulouse|Toulouse', 'Auxerre|Auxerre', 'Lorient|Lorient', 'Paris FC|Paris', 'Angers|Angers',
                'Le Havre|Le Havre', 'Troyes|Troyes', 'Le Mans|Le Mans',
            ]],
            12 => ['FRA', 13, [
                'Nantes|Nantes', 'Metz|Metz', 'Saint-Étienne|Saint-Étienne', 'Reims|Reims', 'Montpellier|Montpellier',
                'Guingamp|Guingamp', 'Clermont|Clermont-Ferrand', 'Grenoble|Grenoble', 'Nancy|Tomblaine', 'Dijon|Dijon',
                'Laval|Laval', 'Annecy|Annecy', 'Dunkerque|Dunkirk', 'Red Star|Saint-Ouen-sur-Seine', 'Pau|Pau',
                'Rodez|Rodez', 'Boulogne|Boulogne-sur-Mer', 'Sochaux|Montbéliard',
            ]],
        ];
    }
}
