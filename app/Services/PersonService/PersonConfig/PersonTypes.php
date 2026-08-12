<?php

namespace App\Services\PersonService\PersonConfig;

abstract class PersonTypes
{
    const PLAYER = 'PLAYER';

    const MANAGER = 'MANAGER';

    const COACH = 'COACH';

    const PHYSIO = 'PHYSIO';

    const YOUTH_PHYSIO = 'YOUTH_PHYSIO';

    const SCOUT = 'SCOUT';

    const OWNER = 'OWNER';

    const YOUTH_COACH = 'YOUTH_COACH';

    const ASSISTANT_MANAGER = 'ASSISTANT_MANAGER';

    const COACHING_ROLES = [
        self::MANAGER,
        self::ASSISTANT_MANAGER,
        self::COACH,
        self::YOUTH_COACH,
    ];
}
