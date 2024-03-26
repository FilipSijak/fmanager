<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ClubRepository
{
    public function getAverageAttributesByPosition(int $clubId, string $position, array $attributes): array
    {
        if (!$attributes) {
            return [];
        }

        $sql = "SELECT";

        foreach ($attributes as $attribute) {
            $sql .= " FLOOR(AVG(" . $attribute .")) AS " . $attribute .",";
        }

        $sql = rtrim($sql, ", ");
        $sql .= " FROM players";
        $sql .= " WHERE club_id = :club_id";
        $sql .= " AND position = :position";

        $result = DB::select($sql, [
            'club_id' => $clubId,
            'position' =>$position
        ]);

        return json_decode(json_encode($result), true);
    }
}
