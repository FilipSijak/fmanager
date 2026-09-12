<?php

namespace App;

enum StadiumStandPosition: string
{
    case NORTH = 'north';
    case EAST = 'east';
    case SOUTH = 'south';
    case WEST = 'west';
    case NORTH_EAST = 'north_east';
    case SOUTH_EAST = 'south_east';
    case SOUTH_WEST = 'south_west';
    case NORTH_WEST = 'north_west';
}
