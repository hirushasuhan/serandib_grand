<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Amenity extends Model
{
    protected static string $table = 'amenities';
    protected static array  $fillable = ['name', 'icon', 'category'];

    public function rules(): array
    {
        return [
            'name'     => 'required|min:2|max:60|unique:amenities,name,' . ($this->id ?? ''),
            'icon'     => 'required|max:40',
            'category' => 'in:room,bathroom,media,services'
        ];
    }
}
