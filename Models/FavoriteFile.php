<?php

namespace Aurora\Modules\Files\Models;

use Aurora\System\Classes\Model;

class FavoriteFile extends Model
{
    protected $table = 'files_favorites';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'IdUser',
        'Type',
        'FullPath',
        'DisplayName',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    protected $casts = [
    ];

    protected $attributes = [
    ];

    protected $appends = [
    ];

    public $timestamps = false;
}
