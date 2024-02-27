<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    public $timestamps = false;
    use HasFactory;

    public function positions()
    {
        return $this->belongsToMany('App\Models\Position');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function getTableColumns()
    {
        $columns = $this->getConnection()->select(
            (new \Illuminate\Database\Schema\Grammars\MySqlGrammar)->compileColumnListing()
            .' order by ordinal_position',
            [$this->getConnection()->getDatabaseName(), $this->getTable()]
        );

        return array_map(function ($value) {
            return $value->column_name;
        }, $columns);
        //return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    public function setPositions(array $positions)
    {
        $this->generatedPosition = $positions;
    }

    public function getPositions()
    {
        return $this->generatedPosition;
    }

    public function setAttributesCategoriesPotential(array $categories)
    {
        $this->attributesCategories = $categories;
    }

    public function getAttributeCategoriesPotential()
    {
        return $this->attributesCategories["potentialByCategory"];
    }
}
