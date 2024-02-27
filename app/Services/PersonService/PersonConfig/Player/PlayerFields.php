<?php

namespace App\Services\PersonService\PersonConfig\Player;

abstract class PlayerFields
{
    const TECHNICAL_FIELDS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick', 'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking', 'tackling', 'technique',
    ];

    const MENTAL_FIELDS = [
        'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership', 'of_the_ball', 'positioning', 'teamwork', 'workrate',
    ];

    const PHYSICAL_FIELDS = [
        'acceleration', 'agility', 'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength',
    ];

    const PERSON_ATTRIBUTE_CATEGORIES = ['technical', 'mental', 'physical'];
}
