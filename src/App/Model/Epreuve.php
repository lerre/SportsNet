<?php

namespace App\Model;

class Epreuve extends Model{
	protected $table = 'epreuve';
    protected $primaryKey = 'epreuve_id';

    public $incrementing=false; 
    protected $fillable = [
        'epreuve_name',
        'epreuve_date_debut',
        'epreuve_date_fin',
        'epreuve_capacite',
        'epreuve_prix'
    ];
}

?>